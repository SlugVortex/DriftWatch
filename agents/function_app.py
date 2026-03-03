# agents/function_app.py
# DriftWatch AI Agent Pipeline - 4 Azure Functions powered by Azure OpenAI.
#
# Agent 1: Archaeologist - Maps blast radius from PR diffs
# Agent 2: Historian    - Scores risk based on incident history
# Agent 3: Negotiator   - Makes deploy/block decision, posts GitHub PR comment
# Agent 4: Chronicler   - Records post-deploy outcome for feedback loop
#
# Each agent receives structured input, calls Azure OpenAI with a specialized
# system prompt, and returns structured JSON output.

import azure.functions as func
import json
import os
import logging
from datetime import datetime

app = func.FunctionApp()


# ---------------------------------------------------------------------------
# Shared: Azure OpenAI client
# ---------------------------------------------------------------------------

def get_ai_client():
    """
    Returns an Azure OpenAI client configured from environment variables.
    Uses the openai library's AzureOpenAI class.
    """
    from openai import AzureOpenAI
    return AzureOpenAI(
        azure_endpoint=os.environ["AZURE_OPENAI_ENDPOINT"],
        api_key=os.environ["AZURE_OPENAI_API_KEY"],
        api_version="2025-03-01-preview",
    )


def get_deployment():
    """Returns the Azure OpenAI deployment name (model)."""
    return os.environ.get("AZURE_OPENAI_DEPLOYMENT", "gpt-4.1-mini")


def ai_json_call(system_prompt: str, user_prompt: str, max_tokens: int = 2000) -> dict:
    """
    Makes a structured JSON call to Azure OpenAI.
    Returns parsed dict or error dict.
    """
    try:
        client = get_ai_client()
        response = client.chat.completions.create(
            model=get_deployment(),
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            temperature=0.1,
            max_tokens=max_tokens,
            response_format={"type": "json_object"},
        )
        result = json.loads(response.choices[0].message.content)
        logging.info(f"[DriftWatch AI] Call succeeded. Tokens used: {response.usage.total_tokens}")
        return result
    except Exception as e:
        logging.error(f"[DriftWatch AI] Call failed: {e}")
        return {"error": str(e)}


# ---------------------------------------------------------------------------
# Agent 1: The Archaeologist — Blast Radius Mapper
# ---------------------------------------------------------------------------

ARCHAEOLOGIST_SYSTEM = """You are The Archaeologist, a code analysis agent for DriftWatch.
Your job is to analyze a pull request diff and map its blast radius — which files,
services, API endpoints, and downstream dependencies are affected by this change.

You must return ONLY valid JSON with this exact structure:
{
    "affected_files": ["list of directly changed files"],
    "affected_services": ["list of services/modules likely impacted — infer from file paths, imports, module names"],
    "affected_endpoints": ["list of API endpoints whose behavior may change"],
    "dependency_graph": {"changed_file": ["list of files that depend on this changed file"]},
    "risk_indicators": ["specific technical concerns about this change"],
    "summary": "2-3 sentence plain English blast radius summary"
}

Be thorough but realistic. Infer service names from directory structure (e.g. src/services/payment/ → payment-service).
Flag high-risk patterns: database migrations, config changes, shared library modifications, auth/payment code."""


@app.route(route="archaeologist", methods=["POST"])
def archaeologist(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 1: Analyzes PR diff to produce blast radius assessment.
    Input: repo_full_name, pr_number, base_branch, head_branch
    Output: affected_files, affected_services, affected_endpoints, dependency_graph, summary
    """
    logging.info("[Archaeologist] Received analysis request.")

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    repo = body.get("repo_full_name", "")
    pr_number = body.get("pr_number", 0)

    # Fetch PR data from GitHub
    changed_files = []
    diff_text = "No diff available"

    token = os.environ.get("GITHUB_TOKEN", "")
    if token and repo and pr_number:
        import httpx
        headers = {"Authorization": f"token {token}", "Accept": "application/json"}

        try:
            with httpx.Client(timeout=15) as client:
                # Get changed files list
                files_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}/files",
                    headers=headers,
                )
                if files_resp.status_code == 200:
                    changed_files = files_resp.json()
                    logging.info(f"[Archaeologist] Fetched {len(changed_files)} changed files from GitHub.")

                # Get the diff
                diff_headers = {**headers, "Accept": "application/vnd.github.v3.diff"}
                diff_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}",
                    headers=diff_headers,
                )
                if diff_resp.status_code == 200:
                    diff_text = diff_resp.text
                    logging.info(f"[Archaeologist] Fetched diff ({len(diff_text)} chars).")
        except Exception as e:
            logging.warning(f"[Archaeologist] GitHub API call failed: {e}")

    # Build the analysis prompt
    file_list = json.dumps([f.get("filename", "unknown") for f in changed_files[:30]])
    # Truncate diff to avoid token limits
    diff_truncated = diff_text[:8000]

    user_prompt = f"""Analyze this pull request and produce a blast radius assessment.

REPOSITORY: {repo}
PR NUMBER: #{pr_number}
CHANGED FILES: {file_list}

DIFF (first 8000 chars):
{diff_truncated}

Return a complete JSON blast radius assessment."""

    result = ai_json_call(ARCHAEOLOGIST_SYSTEM, user_prompt, max_tokens=2000)

    # Ensure required fields exist
    result.setdefault("affected_files", [f.get("filename", "") for f in changed_files])
    result.setdefault("affected_services", [])
    result.setdefault("affected_endpoints", [])
    result.setdefault("dependency_graph", {})
    result.setdefault("risk_indicators", [])
    result.setdefault("summary", "Analysis complete.")

    logging.info(f"[Archaeologist] Analysis complete. Services affected: {len(result['affected_services'])}")

    return func.HttpResponse(
        json.dumps(result),
        mimetype="application/json",
        status_code=200,
    )


# ---------------------------------------------------------------------------
# Agent 2: The Historian — Risk Score Calculator
# ---------------------------------------------------------------------------

HISTORIAN_SYSTEM = """You are The Historian, a Site Reliability Engineer agent for DriftWatch.
Your job is to assess deployment risk by correlating the current PR's blast radius with
historical incident data from the last 90 days.

You must return ONLY valid JSON with this exact structure:
{
    "risk_score": 0-100 integer,
    "risk_level": "low" (0-25) / "medium" (26-50) / "high" (51-75) / "critical" (76-100),
    "historical_incidents": [
        {"id": "INC-XXX", "title": "...", "severity": 1-5, "days_ago": N, "relevance": "why this incident is relevant"}
    ],
    "contributing_factors": ["list of factors driving the risk score"],
    "recommendation": "paragraph with specific deploy/delay/block advice"
}

Risk scoring guidelines:
- 0-25 (Low): No related incidents, documentation/config-only changes, isolated scope
- 26-50 (Medium): Minor related incidents, limited blast radius, some downstream dependencies
- 51-75 (High): Recent related incidents (< 30 days), multiple services affected, critical path changes
- 76-100 (Critical): Multiple P1/P2 incidents in same area, core infrastructure changes, wide blast radius

Be specific about WHY the score is what it is. Reference actual incident IDs and patterns."""


@app.route(route="historian", methods=["POST"])
def historian(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 2: Scores risk based on blast radius + historical incident data.
    Input: affected_services, risk_indicators, pr_number
    Output: risk_score, risk_level, historical_incidents, contributing_factors, recommendation
    """
    logging.info("[Historian] Received risk assessment request.")

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    affected_services = body.get("affected_services", [])
    risk_indicators = body.get("risk_indicators", [])
    pr_number = body.get("pr_number", 0)

    # Fetch historical incidents from Laravel API
    incidents = []
    laravel_url = os.environ.get("LARAVEL_APP_URL", "http://localhost:8000")
    if affected_services:
        import httpx
        try:
            with httpx.Client(timeout=10) as client:
                resp = client.get(
                    f"{laravel_url}/api/incidents",
                    params={"services": ",".join(affected_services)},
                )
                if resp.status_code == 200:
                    incidents = resp.json()
                    logging.info(f"[Historian] Fetched {len(incidents)} related incidents from Laravel.")
        except Exception as e:
            logging.warning(f"[Historian] Failed to fetch incidents: {e}")

    user_prompt = f"""Assess deployment risk for PR #{pr_number}.

BLAST RADIUS:
- Affected Services: {json.dumps(affected_services)}
- Risk Indicators: {json.dumps(risk_indicators)}

HISTORICAL INCIDENTS (last 90 days):
{json.dumps(incidents[:15], indent=2, default=str)}

Produce a JSON risk assessment with score 0-100, level, related incidents, contributing factors, and recommendation."""

    result = ai_json_call(HISTORIAN_SYSTEM, user_prompt, max_tokens=1500)

    # Ensure required fields
    result.setdefault("risk_score", 50)
    result.setdefault("risk_level", "medium")
    result.setdefault("historical_incidents", [])
    result.setdefault("contributing_factors", [])
    result.setdefault("recommendation", "Review recommended before deployment.")

    # Validate risk_level matches score
    score = result["risk_score"]
    if score >= 76:
        result["risk_level"] = "critical"
    elif score >= 51:
        result["risk_level"] = "high"
    elif score >= 26:
        result["risk_level"] = "medium"
    else:
        result["risk_level"] = "low"

    logging.info(f"[Historian] Risk assessment complete. Score: {score}/100 ({result['risk_level']})")

    return func.HttpResponse(
        json.dumps(result),
        mimetype="application/json",
        status_code=200,
    )


# ---------------------------------------------------------------------------
# Agent 3: The Negotiator — Deployment Gatekeeper
# ---------------------------------------------------------------------------

NEGOTIATOR_SYSTEM = """You are The Negotiator, a deployment gatekeeper agent for DriftWatch.
Your job is to make the final deploy/block/review decision and craft a clear GitHub PR comment
summarizing the risk assessment for the development team.

Decision rules:
- risk_score >= 75: BLOCK (decision = "blocked")
- risk_score >= 50: PENDING REVIEW (decision = "pending_review")
- risk_score < 50: APPROVE (decision = "approved")

You must return ONLY valid JSON with this structure:
{
    "decision": "approved" | "blocked" | "pending_review",
    "has_concurrent_deploys": false,
    "in_freeze_window": false,
    "notified_oncall": true/false,
    "notification_message": "message for on-call team or null",
    "pr_comment": "full markdown comment to post on the GitHub PR"
}

The pr_comment should be a well-formatted markdown comment including:
- Risk score with emoji indicator
- Summary of blast radius
- Key contributing factors
- Clear recommendation
- DriftWatch branding footer"""


@app.route(route="negotiator", methods=["POST"])
def negotiator(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 3: Makes deploy decision and posts GitHub PR comment.
    Input: risk_score, risk_level, repo_full_name, pr_number, recommendation, summary
    Output: decision, notification details, pr_comment
    """
    logging.info("[Negotiator] Received deployment decision request.")

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    risk_score = body.get("risk_score", 0)
    risk_level = body.get("risk_level", "low")
    repo = body.get("repo_full_name", "")
    pr_number = body.get("pr_number", 0)
    recommendation = body.get("recommendation", "")
    summary = body.get("summary", "")

    user_prompt = f"""Make a deployment decision for PR #{pr_number}.

RISK SCORE: {risk_score}/100 ({risk_level})
SUMMARY: {summary}
RECOMMENDATION: {recommendation}

Generate the decision JSON and a well-formatted GitHub PR comment in markdown."""

    result = ai_json_call(NEGOTIATOR_SYSTEM, user_prompt, max_tokens=1500)

    # Enforce decision rules based on score (override AI if needed)
    if risk_score >= 75:
        result["decision"] = "blocked"
    elif risk_score >= 50:
        result["decision"] = "pending_review"
    else:
        result["decision"] = "approved"

    result.setdefault("has_concurrent_deploys", False)
    result.setdefault("in_freeze_window", False)
    result.setdefault("notified_oncall", risk_score >= 50)
    result.setdefault("notification_message",
                      f"Risk score {risk_score}/100 for PR #{pr_number}" if risk_score >= 50 else None)

    # Post the PR comment to GitHub if we have a token and pr_comment
    pr_comment = result.get("pr_comment", "")
    if not pr_comment:
        # Build a fallback comment
        emoji = {"critical": "\U0001f534", "high": "\U0001f7e0", "medium": "\U0001f7e1", "low": "\U0001f7e2"}.get(risk_level, "\u26aa")
        pr_comment = f"""## \U0001f3af DriftWatch Risk Assessment

**Risk Score: {risk_score}/100** {emoji} {risk_level.upper()}

### Summary
{summary or 'Analysis complete.'}

### Recommendation
{recommendation or 'No specific recommendation.'}

---
*Assessed by DriftWatch — Pre-deployment risk intelligence*"""
        result["pr_comment"] = pr_comment

    token = os.environ.get("GITHUB_TOKEN", "")
    if token and repo and pr_number:
        import httpx
        try:
            with httpx.Client(timeout=10) as client:
                resp = client.post(
                    f"https://api.github.com/repos/{repo}/issues/{pr_number}/comments",
                    headers={"Authorization": f"token {token}", "Accept": "application/json"},
                    json={"body": pr_comment},
                )
                if resp.status_code == 201:
                    logging.info(f"[Negotiator] Posted PR comment to {repo}#{pr_number}")
                else:
                    logging.warning(f"[Negotiator] Failed to post PR comment: {resp.status_code} {resp.text}")
        except Exception as e:
            logging.warning(f"[Negotiator] GitHub comment post failed: {e}")

    logging.info(f"[Negotiator] Decision: {result['decision']} for PR #{pr_number}")

    return func.HttpResponse(
        json.dumps(result),
        mimetype="application/json",
        status_code=200,
    )


# ---------------------------------------------------------------------------
# Agent 4: The Chronicler — Feedback Loop Recorder
# ---------------------------------------------------------------------------

CHRONICLER_SYSTEM = """You are The Chronicler, a post-deployment analysis agent for DriftWatch.
Your job is to compare what was predicted (risk score, affected services) with what actually
happened after deployment, and record whether the prediction was accurate.

You must return ONLY valid JSON with this structure:
{
    "predicted_risk_score": integer,
    "incident_occurred": true/false,
    "actual_severity": integer 1-5 or null,
    "actual_affected_services": ["list of actually affected services"],
    "prediction_accurate": true/false,
    "post_mortem_notes": "analysis of prediction accuracy and lessons learned"
}

A prediction is considered accurate if:
- Low risk (< 26) and no incident → accurate
- High/critical risk (> 50) and incident occurred → accurate
- Any other combination → inaccurate

Provide insightful post_mortem_notes about what can be learned."""


@app.route(route="chronicler", methods=["POST"])
def chronicler(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 4: Records post-deployment outcome and evaluates prediction accuracy.
    Input: predicted_risk_score, affected_services, incident_occurred, actual_severity
    Output: prediction accuracy assessment and post-mortem notes
    """
    logging.info("[Chronicler] Received post-deployment outcome.")

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    predicted_score = body.get("predicted_risk_score", 0)
    affected_services = body.get("affected_services", [])
    incident_occurred = body.get("incident_occurred", False)
    actual_severity = body.get("actual_severity", None)

    user_prompt = f"""Evaluate deployment outcome and prediction accuracy.

PREDICTION:
- Predicted risk score: {predicted_score}/100
- Expected affected services: {json.dumps(affected_services)}

ACTUAL OUTCOME:
- Incident occurred: {incident_occurred}
- Actual severity: {actual_severity or 'N/A (no incident)'}

Assess whether the prediction was accurate and provide post-mortem analysis."""

    result = ai_json_call(CHRONICLER_SYSTEM, user_prompt, max_tokens=1000)

    # Ensure required fields
    result.setdefault("predicted_risk_score", predicted_score)
    result.setdefault("incident_occurred", incident_occurred)
    result.setdefault("actual_severity", actual_severity)
    result.setdefault("actual_affected_services", affected_services)

    # Determine accuracy if AI didn't
    if "prediction_accurate" not in result:
        if predicted_score < 26 and not incident_occurred:
            result["prediction_accurate"] = True
        elif predicted_score > 50 and incident_occurred:
            result["prediction_accurate"] = True
        elif 26 <= predicted_score <= 50 and not incident_occurred:
            result["prediction_accurate"] = True
        else:
            result["prediction_accurate"] = False

    result.setdefault("post_mortem_notes", "Outcome recorded.")

    logging.info(f"[Chronicler] Outcome recorded. Prediction accurate: {result['prediction_accurate']}")

    return func.HttpResponse(
        json.dumps(result),
        mimetype="application/json",
        status_code=200,
    )


# ---------------------------------------------------------------------------
# Health check endpoint
# ---------------------------------------------------------------------------

@app.route(route="health", methods=["GET"])
def health(req: func.HttpRequest) -> func.HttpResponse:
    """Simple health check to verify agents are running."""
    return func.HttpResponse(
        json.dumps({
            "status": "healthy",
            "service": "DriftWatch AI Agents",
            "agents": ["archaeologist", "historian", "negotiator", "chronicler"],
            "timestamp": datetime.utcnow().isoformat(),
        }),
        mimetype="application/json",
        status_code=200,
    )
