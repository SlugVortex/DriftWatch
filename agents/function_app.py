# agents/function_app.py
# DriftWatch AI Agent Pipeline - 4 Azure Functions powered by Azure OpenAI.
# Orchestrated via Microsoft Semantic Kernel SDK for agentic AI patterns.
#
# Architecture:
#   Semantic Kernel Orchestrator
#   ├── Archaeologist Plugin  → Blast Radius Mapper
#   ├── Historian Plugin      → Risk Score Calculator
#   ├── Negotiator Plugin     → Deployment Gatekeeper
#   └── Chronicler Plugin     → Feedback Loop Recorder
#
# Each agent is a Semantic Kernel plugin with a specialized kernel function.
# Azure AI Content Safety is applied to agent outputs before returning.

import azure.functions as func
import json
import os
import logging
from datetime import datetime

# Semantic Kernel imports
from semantic_kernel import Kernel
from semantic_kernel.connectors.ai.open_ai import AzureChatCompletion
from semantic_kernel.connectors.ai.open_ai.prompt_execution_settings import (
    AzureChatPromptExecutionSettings,
)
from semantic_kernel.contents import ChatHistory
from semantic_kernel.functions import kernel_function
from semantic_kernel.connectors.ai.function_choice_behavior import FunctionChoiceBehavior

app = func.FunctionApp()


# ---------------------------------------------------------------------------
# Semantic Kernel: Kernel Factory
# ---------------------------------------------------------------------------

def create_kernel() -> Kernel:
    """
    Creates and configures a Semantic Kernel instance with Azure OpenAI.
    Uses the Planner → Skills → Memory pattern from the SK architecture.
    """
    kernel = Kernel()

    # Add Azure OpenAI chat completion service
    service = AzureChatCompletion(
        deployment_name=os.environ.get("AZURE_OPENAI_DEPLOYMENT", "gpt-4.1-mini"),
        endpoint=os.environ["AZURE_OPENAI_ENDPOINT"],
        api_key=os.environ["AZURE_OPENAI_API_KEY"],
        api_version="2025-03-01-preview",
    )
    kernel.add_service(service)

    logging.info("[DriftWatch SK] Kernel created with Azure OpenAI service.")
    return kernel


async def sk_json_call(system_prompt: str, user_prompt: str, max_tokens: int = 2000) -> dict:
    """
    Makes a structured JSON call via Semantic Kernel's chat completion.
    Uses SK ChatHistory for multi-turn context management.
    Returns parsed dict or error dict.
    """
    try:
        kernel = create_kernel()

        # Configure execution settings for structured JSON output
        settings = AzureChatPromptExecutionSettings(
            max_tokens=max_tokens,
            temperature=0.1,
            response_format={"type": "json_object"},
        )

        # Build chat history (SK's conversation memory pattern)
        chat_history = ChatHistory()
        chat_history.add_system_message(system_prompt)
        chat_history.add_user_message(user_prompt)

        # Get the chat completion service and invoke
        chat_service = kernel.get_service(type=AzureChatCompletion)
        response = await chat_service.get_chat_message_contents(
            chat_history=chat_history,
            settings=settings,
            kernel=kernel,
        )

        result_text = str(response[0])
        result = json.loads(result_text)
        logging.info(f"[DriftWatch SK] Chat completion succeeded.")
        return result

    except Exception as e:
        logging.error(f"[DriftWatch SK] Call failed: {e}")
        # Fallback to direct OpenAI client if SK fails
        return await _fallback_ai_call(system_prompt, user_prompt, max_tokens)


async def _fallback_ai_call(system_prompt: str, user_prompt: str, max_tokens: int) -> dict:
    """Fallback: direct Azure OpenAI call if Semantic Kernel fails."""
    try:
        from openai import AzureOpenAI
        client = AzureOpenAI(
            azure_endpoint=os.environ["AZURE_OPENAI_ENDPOINT"],
            api_key=os.environ["AZURE_OPENAI_API_KEY"],
            api_version="2025-03-01-preview",
        )
        response = client.chat.completions.create(
            model=os.environ.get("AZURE_OPENAI_DEPLOYMENT", "gpt-4.1-mini"),
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            temperature=0.1,
            max_tokens=max_tokens,
            response_format={"type": "json_object"},
        )
        result = json.loads(response.choices[0].message.content)
        logging.info(f"[DriftWatch Fallback] Direct OpenAI call succeeded.")
        return result
    except Exception as e:
        logging.error(f"[DriftWatch Fallback] Call also failed: {e}")
        return {"error": str(e)}


# ---------------------------------------------------------------------------
# Azure AI Content Safety: Output Filtering
# ---------------------------------------------------------------------------

def content_safety_check(text: str) -> bool:
    """
    Checks text against Azure AI Content Safety.
    Returns True if content is safe, False if flagged.
    Falls back to True (allow) if Content Safety is not configured.
    """
    endpoint = os.environ.get("AZURE_CONTENT_SAFETY_ENDPOINT", "")
    key = os.environ.get("AZURE_CONTENT_SAFETY_KEY", "")

    if not endpoint or not key:
        # Content Safety not configured — allow by default
        return True

    try:
        import httpx
        with httpx.Client(timeout=5) as client:
            resp = client.post(
                f"{endpoint}/contentsafety/text:analyze?api-version=2024-09-01",
                headers={"Ocp-Apim-Subscription-Key": key, "Content-Type": "application/json"},
                json={"text": text[:5000]},
            )
            if resp.status_code == 200:
                result = resp.json()
                # Check if any category severity >= 4 (harmful)
                categories = result.get("categoriesAnalysis", [])
                for cat in categories:
                    if cat.get("severity", 0) >= 4:
                        logging.warning(f"[Content Safety] Flagged content: {cat['category']} severity {cat['severity']}")
                        return False
                return True
    except Exception as e:
        logging.warning(f"[Content Safety] Check failed (allowing): {e}")

    return True


# ---------------------------------------------------------------------------
# Semantic Kernel Plugin: Archaeologist Agent
# ---------------------------------------------------------------------------

class ArchaeologistPlugin:
    """SK Plugin: Analyzes PR diffs to map blast radius of code changes."""

    @kernel_function(
        name="analyze_blast_radius",
        description="Analyzes a pull request diff to map affected files, services, and endpoints."
    )
    async def analyze_blast_radius(self, repo: str, pr_number: int, diff_text: str, changed_files: list) -> dict:
        """Executes blast radius analysis via Azure OpenAI."""
        system_prompt = ARCHAEOLOGIST_SYSTEM
        file_list = json.dumps([f.get("filename", "unknown") if isinstance(f, dict) else str(f) for f in changed_files[:30]])
        diff_truncated = diff_text[:8000]

        user_prompt = f"""Analyze this pull request and produce a blast radius assessment.

REPOSITORY: {repo}
PR NUMBER: #{pr_number}
CHANGED FILES: {file_list}

DIFF (first 8000 chars):
{diff_truncated}

Return a complete JSON blast radius assessment."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=2000)


class HistorianPlugin:
    """SK Plugin: Correlates blast radius with historical incidents for risk scoring."""

    @kernel_function(
        name="calculate_risk",
        description="Calculates deployment risk score by correlating blast radius with incident history."
    )
    async def calculate_risk(self, pr_number: int, affected_services: list, risk_indicators: list, incidents: list) -> dict:
        """Executes risk assessment via Azure OpenAI."""
        system_prompt = HISTORIAN_SYSTEM
        user_prompt = f"""Assess deployment risk for PR #{pr_number}.

BLAST RADIUS:
- Affected Services: {json.dumps(affected_services)}
- Risk Indicators: {json.dumps(risk_indicators)}

HISTORICAL INCIDENTS (last 90 days):
{json.dumps(incidents[:15], indent=2, default=str)}

Produce a JSON risk assessment with score 0-100, level, related incidents, contributing factors, and recommendation."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=1500)


class NegotiatorPlugin:
    """SK Plugin: Makes deploy/block/review decisions and posts GitHub PR comments."""

    @kernel_function(
        name="make_decision",
        description="Makes deployment decision based on risk score and generates PR comment."
    )
    async def make_decision(self, pr_number: int, risk_score: int, risk_level: str, summary: str, recommendation: str) -> dict:
        """Executes deployment decision via Azure OpenAI."""
        system_prompt = NEGOTIATOR_SYSTEM
        user_prompt = f"""Make a deployment decision for PR #{pr_number}.

RISK SCORE: {risk_score}/100 ({risk_level})
SUMMARY: {summary}
RECOMMENDATION: {recommendation}

Generate the decision JSON and a well-formatted GitHub PR comment in markdown."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=1500)


class ChroniclerPlugin:
    """SK Plugin: Records post-deployment outcomes and evaluates prediction accuracy."""

    @kernel_function(
        name="record_outcome",
        description="Records deployment outcome and evaluates prediction accuracy."
    )
    async def record_outcome(self, predicted_score: int, affected_services: list, incident_occurred: bool, actual_severity: int = None) -> dict:
        """Executes outcome analysis via Azure OpenAI."""
        system_prompt = CHRONICLER_SYSTEM
        user_prompt = f"""Evaluate deployment outcome and prediction accuracy.

PREDICTION:
- Predicted risk score: {predicted_score}/100
- Expected affected services: {json.dumps(affected_services)}

ACTUAL OUTCOME:
- Incident occurred: {incident_occurred}
- Actual severity: {actual_severity or 'N/A (no incident)'}

Assess whether the prediction was accurate and provide post-mortem analysis."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=1000)


# ---------------------------------------------------------------------------
# Agent System Prompts
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


# ---------------------------------------------------------------------------
# Azure Function Endpoints (HTTP Triggers)
# ---------------------------------------------------------------------------

import asyncio


def _run_async(coro):
    """Helper to run async SK functions from sync Azure Function handlers."""
    try:
        loop = asyncio.get_event_loop()
        if loop.is_running():
            import concurrent.futures
            with concurrent.futures.ThreadPoolExecutor() as pool:
                return pool.submit(asyncio.run, coro).result()
        return loop.run_until_complete(coro)
    except RuntimeError:
        return asyncio.run(coro)


@app.route(route="archaeologist", methods=["POST"])
def archaeologist(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 1: Blast Radius Mapper (Semantic Kernel ArchaeologistPlugin)
    Input: repo_full_name, pr_number, base_branch, head_branch
    Output: affected_files, affected_services, affected_endpoints, dependency_graph, summary
    """
    logging.info("[Archaeologist] Received analysis request via SK pipeline.")

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
                files_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}/files",
                    headers=headers,
                )
                if files_resp.status_code == 200:
                    changed_files = files_resp.json()
                    logging.info(f"[Archaeologist] Fetched {len(changed_files)} changed files from GitHub.")

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

    # Execute via SK plugin
    plugin = ArchaeologistPlugin()
    result = _run_async(plugin.analyze_blast_radius(
        repo=repo,
        pr_number=pr_number,
        diff_text=diff_text,
        changed_files=changed_files,
    ))

    # Ensure required fields exist
    result.setdefault("affected_files", [f.get("filename", "") for f in changed_files if isinstance(f, dict)])
    result.setdefault("affected_services", [])
    result.setdefault("affected_endpoints", [])
    result.setdefault("dependency_graph", {})
    result.setdefault("risk_indicators", [])
    result.setdefault("summary", "Analysis complete.")

    # Content Safety check on summary
    if not content_safety_check(result.get("summary", "")):
        result["summary"] = "Analysis complete. (Content filtered by Azure AI Content Safety)"

    logging.info(f"[Archaeologist] SK analysis complete. Services: {len(result['affected_services'])}")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="historian", methods=["POST"])
def historian(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 2: Risk Score Calculator (Semantic Kernel HistorianPlugin)
    Input: affected_services, risk_indicators, pr_number
    Output: risk_score, risk_level, historical_incidents, contributing_factors, recommendation
    """
    logging.info("[Historian] Received risk assessment request via SK pipeline.")

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
                    logging.info(f"[Historian] Fetched {len(incidents)} related incidents.")
        except Exception as e:
            logging.warning(f"[Historian] Failed to fetch incidents: {e}")

    # Execute via SK plugin
    plugin = HistorianPlugin()
    result = _run_async(plugin.calculate_risk(
        pr_number=pr_number,
        affected_services=affected_services,
        risk_indicators=risk_indicators,
        incidents=incidents,
    ))

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

    logging.info(f"[Historian] SK risk assessment complete. Score: {score}/100 ({result['risk_level']})")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="negotiator", methods=["POST"])
def negotiator(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 3: Deployment Gatekeeper (Semantic Kernel NegotiatorPlugin)
    Input: risk_score, risk_level, repo_full_name, pr_number, recommendation, summary
    Output: decision, notification details, pr_comment
    """
    logging.info("[Negotiator] Received deployment decision request via SK pipeline.")

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

    # Execute via SK plugin
    plugin = NegotiatorPlugin()
    result = _run_async(plugin.make_decision(
        pr_number=pr_number,
        risk_score=risk_score,
        risk_level=risk_level,
        summary=summary,
        recommendation=recommendation,
    ))

    # Enforce decision rules based on score
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

    # Build fallback PR comment if needed
    pr_comment = result.get("pr_comment", "")
    if not pr_comment:
        emoji = {"critical": "\U0001f534", "high": "\U0001f7e0", "medium": "\U0001f7e1", "low": "\U0001f7e2"}.get(risk_level, "\u26aa")
        pr_comment = f"""## \U0001f3af DriftWatch Risk Assessment

**Risk Score: {risk_score}/100** {emoji} {risk_level.upper()}

### Summary
{summary or 'Analysis complete.'}

### Recommendation
{recommendation or 'No specific recommendation.'}

---
*Assessed by DriftWatch — Powered by Semantic Kernel + Azure OpenAI*"""
        result["pr_comment"] = pr_comment

    # Content Safety check on PR comment before posting
    if not content_safety_check(pr_comment):
        pr_comment = "## DriftWatch Risk Assessment\n\nContent filtered by Azure AI Content Safety. Please review manually."
        result["pr_comment"] = pr_comment
        logging.warning("[Negotiator] PR comment flagged by Content Safety — replaced.")

    # Post the PR comment to GitHub
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
                    logging.warning(f"[Negotiator] Failed to post PR comment: {resp.status_code}")
        except Exception as e:
            logging.warning(f"[Negotiator] GitHub comment post failed: {e}")

    logging.info(f"[Negotiator] SK decision: {result['decision']} for PR #{pr_number}")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="chronicler", methods=["POST"])
def chronicler(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 4: Feedback Loop Recorder (Semantic Kernel ChroniclerPlugin)
    Input: predicted_risk_score, affected_services, incident_occurred, actual_severity
    Output: prediction accuracy assessment and post-mortem notes
    """
    logging.info("[Chronicler] Received post-deployment outcome via SK pipeline.")

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    predicted_score = body.get("predicted_risk_score", 0)
    affected_services = body.get("affected_services", [])
    incident_occurred = body.get("incident_occurred", False)
    actual_severity = body.get("actual_severity", None)

    # Execute via SK plugin
    plugin = ChroniclerPlugin()
    result = _run_async(plugin.record_outcome(
        predicted_score=predicted_score,
        affected_services=affected_services,
        incident_occurred=incident_occurred,
        actual_severity=actual_severity,
    ))

    # Ensure required fields
    result.setdefault("predicted_risk_score", predicted_score)
    result.setdefault("incident_occurred", incident_occurred)
    result.setdefault("actual_severity", actual_severity)
    result.setdefault("actual_affected_services", affected_services)

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

    logging.info(f"[Chronicler] SK outcome recorded. Prediction accurate: {result['prediction_accurate']}")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


# ---------------------------------------------------------------------------
# Health check endpoint
# ---------------------------------------------------------------------------

@app.route(route="health", methods=["GET"])
def health(req: func.HttpRequest) -> func.HttpResponse:
    """Health check — reports agent status and Azure services in use."""
    return func.HttpResponse(
        json.dumps({
            "status": "healthy",
            "service": "DriftWatch AI Agents",
            "orchestrator": "Microsoft Semantic Kernel",
            "model": os.environ.get("AZURE_OPENAI_DEPLOYMENT", "gpt-4.1-mini"),
            "agents": [
                {"name": "archaeologist", "type": "SK Plugin", "status": "active"},
                {"name": "historian", "type": "SK Plugin", "status": "active"},
                {"name": "negotiator", "type": "SK Plugin", "status": "active"},
                {"name": "chronicler", "type": "SK Plugin", "status": "active"},
            ],
            "azure_services": [
                "Azure OpenAI", "Azure Functions V2", "Azure AI Content Safety",
                "Azure MySQL", "Application Insights", "Semantic Kernel SDK",
            ],
            "content_safety": "enabled" if os.environ.get("AZURE_CONTENT_SAFETY_ENDPOINT") else "not_configured",
            "timestamp": datetime.utcnow().isoformat(),
        }),
        mimetype="application/json",
        status_code=200,
    )
