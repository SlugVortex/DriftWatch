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
        """Executes blast radius analysis via Azure OpenAI with full file contents."""
        system_prompt = ARCHAEOLOGIST_SYSTEM

        # Build detailed file info including full content
        file_sections = []
        for f in changed_files[:30]:
            if isinstance(f, dict):
                section = f"### {f.get('filename', 'unknown')} ({f.get('status', 'modified')}, +{f.get('additions', 0)}/-{f.get('deletions', 0)})"
                if f.get("full_file_content"):
                    section += f"\n#### Full File Content:\n```\n{f['full_file_content']}\n```"
                if f.get("patch"):
                    section += f"\n#### Patch/Diff:\n```diff\n{f['patch']}\n```"
                file_sections.append(section)

        files_text = "\n\n".join(file_sections)

        user_prompt = f"""Analyze this pull request and produce a blast radius assessment.
READ THE ACTUAL CODE BELOW — do not just look at file names.

REPOSITORY: {repo}
PR NUMBER: #{pr_number}
TOTAL FILES CHANGED: {len(changed_files)}

=== CHANGED FILES WITH FULL CONTENT ===

{files_text}

=== FULL DIFF ===
{diff_text[:10000]}

Classify each file using the scoring rubric. Read the actual code to determine:
1. What functions/classes were modified and how
2. What imports/dependencies connect this file to others
3. Whether the change affects public APIs, database queries, auth, or config

Return a complete JSON blast radius assessment with change_classifications for each file."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=3000)


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
Your job is to analyze a pull request diff and map its REAL blast radius — which files,
services, API endpoints, and downstream dependencies are affected by this change.

CRITICAL: You must READ the actual diff content, not just file names. Classify every changed
file using this scoring rubric and sum the scores:

SCORING TABLE (assign the HIGHEST matching score per file):
- CSS or view-only change (no logic, only .blade.php layout or .css): 2 points
- New standalone function added, not called by existing code: 5 points
- New API endpoint or new controller action: 15 points
- Existing function signature modified (parameters changed, return type changed): 20 points
- Middleware, auth, token handling, session handling, guards: 30 points
- SQL query added/modified, Eloquent query builder changes, migration file: 25 points
- Config file changed (.env, config/, any .json config): 20 points
- Shared utility or base class imported by 3+ other files: 25 points

For any file scoring 20+, read the full file context to determine if the change is
isolated or connects to many system parts. Follow imports (use/require/include for PHP,
import/require for JS) to find downstream files in the blast radius.

FAILURE STATES:
- If the diff is empty or PR has no changed files, return {"status": "insufficient_data"}
- If an error occurs, return {"status": "error", "error_message": "description"}
- NEVER return a fake low score when data is missing — that causes false "Clear Skies"

You must return ONLY valid JSON with this exact structure:
{
    "status": "scored",
    "affected_files": ["list of directly changed files"],
    "affected_services": ["list of services/modules impacted — infer from file paths, imports"],
    "affected_endpoints": ["list of API endpoints whose behavior may change"],
    "dependency_graph": {"changed_file": ["files that depend on this changed file"]},
    "change_classifications": [
        {
            "file": "filename.php",
            "change_type": "changed_function_signature",
            "risk_score": 20,
            "reasoning": "one sentence explanation",
            "full_file_read": true
        }
    ],
    "total_blast_radius_score": 0,
    "total_affected_files": 0,
    "total_affected_services": 0,
    "risk_indicators": ["specific technical concerns"],
    "summary": "plain English paragraph explaining what the PR changes and why it is risky or safe"
}

Be thorough but realistic. Infer service names from directory structure.
Flag high-risk patterns: database migrations, config changes, shared library modifications, auth/payment code."""

HISTORIAN_SYSTEM = """You are The Historian, a Site Reliability Engineer agent for DriftWatch.
Your job is to assess deployment risk by correlating the current PR's blast radius with
historical incident data. You use THREE layers of matching:

LAYER 1 — Direct File Match (25 pts each):
Check if any file in affected_files exactly matches a file in any incident's affected_files.

LAYER 2 — Service Match (10 pts each):
Check if any service in affected_services matches a service in any incident's affected_services.

LAYER 3 — Change Type Match (15 pts each):
Check if any change_type from the Archaeologist's change_classifications matches a
change_type field in past incidents (e.g., "changed_function_signature", "new_api_endpoint").

CAP your total contribution at 40 points maximum from historical matching alone.
Then add the blast_radius_score from the Archaeologist (passed to you) to get the final score.

IMPORTANT: If no incidents exist in the database at all, return:
{"status": "insufficient_data", "message": "No incident history available — deploy to a
real environment and accumulate incident data to enable historical scoring."}
Do NOT return zero as if the PR is safe. Be honest that scoring is unavailable.

Also factor in CI status and bot findings if provided by the Archaeologist.

You must return ONLY valid JSON with this exact structure:
{
    "status": "scored",
    "risk_score": 0-100 integer,
    "risk_level": "low" (0-25) / "medium" (26-50) / "high" (51-75) / "critical" (76-100),
    "historical_incidents": [
        {"id": "INC-XXX", "title": "...", "severity": 1-5, "days_ago": N, "relevance": "why relevant",
         "match_type": "file|service|change_type", "match_score": 25}
    ],
    "match_summary": {
        "file_matches": 0,
        "service_matches": 0,
        "change_type_matches": 0,
        "history_score": 0,
        "blast_radius_score": 0,
        "ci_risk_addition": 0,
        "bot_risk_addition": 0
    },
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
    Steps: 1) Fetch diff  2) Classify changes  3) Read high-risk files
           4) Follow imports  5) Check CI status  6) Check bot findings
    """
    logging.info("[Archaeologist] Received analysis request via SK pipeline.")
    start_time = datetime.utcnow()

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    repo = body.get("repo_full_name", "")
    pr_number = body.get("pr_number", 0)

    # Step 1: Fetch PR diff and changed files from GitHub
    changed_files = []
    diff_text = ""
    pr_head_sha = ""

    token = os.environ.get("GITHUB_TOKEN", "")
    if token and repo and pr_number:
        import httpx
        headers = {"Authorization": f"token {token}", "Accept": "application/json"}

        try:
            with httpx.Client(timeout=30) as client:
                # Get PR metadata for head SHA
                pr_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}",
                    headers=headers,
                )
                if pr_resp.status_code == 200:
                    pr_data = pr_resp.json()
                    pr_head_sha = pr_data.get("head", {}).get("sha", "")

                # Get changed files with patch data
                files_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}/files?per_page=100",
                    headers=headers,
                )
                if files_resp.status_code == 200:
                    changed_files = files_resp.json()
                    logging.info(f"[Archaeologist] Fetched {len(changed_files)} changed files from GitHub.")

                # Get full diff
                diff_headers = {**headers, "Accept": "application/vnd.github.v3.diff"}
                diff_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}",
                    headers=diff_headers,
                )
                if diff_resp.status_code == 200:
                    diff_text = diff_resp.text
                    logging.info(f"[Archaeologist] Fetched diff ({len(diff_text)} chars).")

                # Step 3: Fetch full file contents for ALL changed files
                # This gives the AI the actual code context, not just patch hunks
                import base64
                files_read = 0
                for f in changed_files[:30]:  # Cap at 30 files to stay within API limits
                    fname = f.get("filename", "")
                    if not fname or not pr_head_sha:
                        continue
                    # Skip binary/image/vendor files
                    skip_exts = [".png", ".jpg", ".jpeg", ".gif", ".ico", ".svg", ".woff",
                                 ".woff2", ".ttf", ".eot", ".mp4", ".zip", ".tar", ".gz",
                                 ".lock", ".min.js", ".min.css"]
                    if any(fname.lower().endswith(ext) for ext in skip_exts):
                        continue
                    if "/vendor/" in fname or "/node_modules/" in fname or "/dist/" in fname:
                        continue
                    try:
                        content_resp = client.get(
                            f"https://api.github.com/repos/{repo}/contents/{fname}?ref={pr_head_sha}",
                            headers=headers,
                        )
                        if content_resp.status_code == 200:
                            content_data = content_resp.json()
                            if content_data.get("encoding") == "base64" and content_data.get("size", 0) < 100000:
                                decoded = base64.b64decode(content_data["content"]).decode("utf-8", errors="replace")
                                f["full_content"] = decoded[:8000]
                                files_read += 1
                    except Exception:
                        pass
                logging.info(f"[Archaeologist] Read full contents of {files_read}/{len(changed_files)} files.")

                # Step 5: Check CI status
                ci_status = "unknown"
                failing_checks = []
                ci_risk_addition = 0
                if pr_head_sha:
                    try:
                        checks_resp = client.get(
                            f"https://api.github.com/repos/{repo}/commits/{pr_head_sha}/check-runs?per_page=50",
                            headers=headers,
                        )
                        if checks_resp.status_code == 200:
                            check_runs = checks_resp.json().get("check_runs", [])
                            required_keywords = ["unit test", "required", "backend", "integration",
                                                  "build", "lint", "ci"]
                            for check in check_runs:
                                if check.get("conclusion") == "failure":
                                    check_name = check.get("name", "")
                                    if any(kw in check_name.lower() for kw in required_keywords):
                                        failing_checks.append(check_name)

                            if failing_checks:
                                ci_status = "failing"
                                ci_risk_addition = 25
                                logging.info(f"[Archaeologist] CI failures detected: {failing_checks}")
                            elif any(c.get("conclusion") == "success" for c in check_runs):
                                ci_status = "passing"
                            else:
                                ci_status = "pending"
                    except Exception as e:
                        logging.warning(f"[Archaeologist] CI check failed: {e}")

                # Step 6: Check bot findings (security bots)
                bot_findings = []
                bot_risk_addition = 0
                security_bots = ["aikido", "snyk", "codeql", "dependabot", "semgrep",
                                  "sonarcloud", "sonarqube", "checkmarx", "mend", "renovate"]
                try:
                    comments_resp = client.get(
                        f"https://api.github.com/repos/{repo}/issues/{pr_number}/comments?per_page=100",
                        headers=headers,
                    )
                    if comments_resp.status_code == 200:
                        comments = comments_resp.json()
                        for comment in comments:
                            author = (comment.get("user", {}).get("login", "") or "").lower()
                            body_text = (comment.get("body", "") or "")[:500]
                            for bot in security_bots:
                                if bot in author:
                                    severity = "medium"
                                    risk_add = 10
                                    body_lower = body_text.lower()
                                    if "critical" in body_lower:
                                        severity = "critical"
                                        risk_add = 30
                                    elif "high" in body_lower:
                                        severity = "high"
                                        risk_add = 20
                                    bot_findings.append({
                                        "bot": author,
                                        "severity": severity,
                                        "summary": body_text[:200],
                                        "risk_addition": risk_add,
                                    })
                                    bot_risk_addition += risk_add
                                    break
                except Exception as e:
                    logging.warning(f"[Archaeologist] Bot findings check failed: {e}")

        except Exception as e:
            logging.warning(f"[Archaeologist] GitHub API call failed: {e}")

    # Handle empty diff / no data
    if not changed_files and not diff_text:
        return func.HttpResponse(json.dumps({
            "status": "insufficient_data",
            "error_message": "No changed files or diff content available for this PR.",
            "affected_files": [],
            "affected_services": [],
            "affected_endpoints": [],
            "dependency_graph": {},
            "change_classifications": [],
            "total_blast_radius_score": 0,
            "total_affected_files": 0,
            "total_affected_services": 0,
            "summary": "Insufficient data to analyze this PR.",
        }), mimetype="application/json", status_code=200)

    # Build enriched file info for the AI prompt — include full file contents
    file_details = []
    total_content_chars = 0
    max_total_chars = 60000  # Stay within token limits
    for f in changed_files[:30]:
        if isinstance(f, dict):
            detail = {
                "filename": f.get("filename", "unknown"),
                "status": f.get("status", "modified"),
                "additions": f.get("additions", 0),
                "deletions": f.get("deletions", 0),
                "patch": (f.get("patch", "") or "")[:3000],
            }
            # Include full file content so the AI can analyze actual code
            if f.get("full_content") and total_content_chars < max_total_chars:
                content = f["full_content"]
                budget = min(len(content), max_total_chars - total_content_chars, 6000)
                detail["full_file_content"] = content[:budget]
                total_content_chars += budget
            file_details.append(detail)

    # Step 2: Execute AI classification via SK plugin with full file data
    plugin = ArchaeologistPlugin()
    result = _run_async(plugin.analyze_blast_radius(
        repo=repo,
        pr_number=pr_number,
        diff_text=diff_text[:15000],
        changed_files=file_details,
    ))

    # Ensure required fields exist
    result.setdefault("status", "scored")
    result.setdefault("affected_files", [f.get("filename", "") for f in changed_files if isinstance(f, dict)])
    result.setdefault("affected_services", [])
    result.setdefault("affected_endpoints", [])
    result.setdefault("dependency_graph", {})
    result.setdefault("change_classifications", [])
    result.setdefault("risk_indicators", [])
    result.setdefault("summary", "Analysis complete.")
    result.setdefault("total_affected_files", len(result["affected_files"]))
    result.setdefault("total_affected_services", len(result["affected_services"]))

    # Calculate total blast radius score from classifications
    classification_score = sum(
        c.get("risk_score", 0) for c in result.get("change_classifications", [])
    )
    # Add CI and bot risk
    total_score = classification_score + ci_risk_addition + bot_risk_addition
    # Cap at 100
    result["total_blast_radius_score"] = min(total_score, 100)

    # Add CI and bot data to result
    result["ci_status"] = ci_status
    result["failing_checks"] = failing_checks
    result["ci_risk_addition"] = ci_risk_addition
    result["bot_findings"] = bot_findings
    result["bot_risk_addition"] = bot_risk_addition

    # Content Safety check on summary
    if not content_safety_check(result.get("summary", "")):
        result["summary"] = "Analysis complete. (Content filtered by Azure AI Content Safety)"

    # Record timing
    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[Archaeologist] SK analysis complete. Score: {result['total_blast_radius_score']}, "
                 f"Services: {len(result['affected_services'])}, CI: {ci_status}, "
                 f"Bot findings: {len(bot_findings)}, Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="historian", methods=["POST"])
def historian(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 2: Risk Score Calculator (Semantic Kernel HistorianPlugin)
    Multi-layer matching: file match (25pts), service match (10pts), change-type match (15pts).
    Combines historical risk with blast_radius_score from Archaeologist.
    """
    logging.info("[Historian] Received risk assessment request via SK pipeline.")
    start_time = datetime.utcnow()

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    affected_services = body.get("affected_services", [])
    affected_files = body.get("affected_files", [])
    risk_indicators = body.get("risk_indicators", [])
    pr_number = body.get("pr_number", 0)
    blast_radius_score = body.get("blast_radius_score", 0)
    change_classifications = body.get("change_classifications", [])
    ci_status = body.get("ci_status", "unknown")
    ci_risk_addition = body.get("ci_risk_addition", 0)
    bot_findings = body.get("bot_findings", [])
    bot_risk_addition = body.get("bot_risk_addition", 0)

    # Fetch ALL historical incidents from Laravel API (not just service-filtered)
    incidents = []
    laravel_url = os.environ.get("LARAVEL_APP_URL", "http://localhost:8000")
    import httpx
    try:
        with httpx.Client(timeout=10) as client:
            # Fetch all incidents for multi-layer matching
            resp = client.get(f"{laravel_url}/api/incidents")
            if resp.status_code == 200:
                incidents = resp.json()
                logging.info(f"[Historian] Fetched {len(incidents)} total incidents for multi-layer matching.")
    except Exception as e:
        logging.warning(f"[Historian] Failed to fetch incidents: {e}")

    # If no incidents exist at all, return insufficient_data
    if not incidents:
        result = {
            "status": "insufficient_data",
            "risk_score": blast_radius_score,
            "risk_level": "unknown",
            "message": "No incident history available — deploy to a real environment and accumulate incident data to enable historical scoring.",
            "historical_incidents": [],
            "match_summary": {
                "file_matches": 0,
                "service_matches": 0,
                "change_type_matches": 0,
                "history_score": 0,
                "blast_radius_score": blast_radius_score,
                "ci_risk_addition": ci_risk_addition,
                "bot_risk_addition": bot_risk_addition,
            },
            "contributing_factors": ["No incident history available for historical correlation"],
            "recommendation": "Historical scoring unavailable. Blast radius score is {}/100. Proceed with standard review process.".format(blast_radius_score),
        }
        # Still assign risk_level based on blast_radius_score alone
        score = blast_radius_score
        if score >= 76:
            result["risk_level"] = "critical"
        elif score >= 51:
            result["risk_level"] = "high"
        elif score >= 26:
            result["risk_level"] = "medium"
        else:
            result["risk_level"] = "low"

        duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
        result["duration_ms"] = duration_ms
        return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)

    # Execute via SK plugin with enriched data
    plugin = HistorianPlugin()
    result = _run_async(plugin.calculate_risk(
        pr_number=pr_number,
        affected_services=affected_services,
        risk_indicators=risk_indicators + [
            f"Blast radius score: {blast_radius_score}",
            f"CI status: {ci_status}",
            f"CI risk addition: {ci_risk_addition}",
            f"Bot findings count: {len(bot_findings)}",
            f"Affected files: {json.dumps(affected_files[:10])}",
            f"Change classifications: {json.dumps(change_classifications[:10])}",
        ],
        incidents=incidents[:20],
    ))

    # Ensure required fields
    result.setdefault("status", "scored")
    result.setdefault("risk_score", 50)
    result.setdefault("risk_level", "medium")
    result.setdefault("historical_incidents", [])
    result.setdefault("contributing_factors", [])
    result.setdefault("recommendation", "Review recommended before deployment.")
    result.setdefault("match_summary", {
        "file_matches": 0,
        "service_matches": 0,
        "change_type_matches": 0,
        "history_score": 0,
        "blast_radius_score": blast_radius_score,
        "ci_risk_addition": ci_risk_addition,
        "bot_risk_addition": bot_risk_addition,
    })

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

    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[Historian] SK risk assessment complete. Score: {score}/100 ({result['risk_level']}), Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="negotiator", methods=["POST"])
def negotiator(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 3: Deployment Gatekeeper (Semantic Kernel NegotiatorPlugin)
    Input: risk_score, risk_level, repo_full_name, pr_number, recommendation, summary
    Output: decision, notification details, pr_comment
    """
    logging.info("[Negotiator] Received deployment decision request via SK pipeline.")
    start_time = datetime.utcnow()

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

    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[Negotiator] SK decision: {result['decision']} for PR #{pr_number}, Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="chronicler", methods=["POST"])
def chronicler(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 4: Feedback Loop Recorder (Semantic Kernel ChroniclerPlugin)
    Input: predicted_risk_score, affected_services, incident_occurred, actual_severity
    Output: prediction accuracy assessment and post-mortem notes
    """
    logging.info("[Chronicler] Received post-deployment outcome via SK pipeline.")
    start_time = datetime.utcnow()

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

    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[Chronicler] SK outcome recorded. Prediction accurate: {result['prediction_accurate']}, Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


# ---------------------------------------------------------------------------
# Health check endpoint
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Agent 5: Navigator — Interactive Impact Chat
# ---------------------------------------------------------------------------
# The Navigator is a conversational agent that helps DevOps engineers
# explore and understand the blast radius of a PR through natural language.
# It receives the full file context, dependency graph, risk scores, and
# answers questions like "what depends on auth.py?" or "why is this risky?"

class NavigatorPlugin:
    """Semantic Kernel plugin for the Navigator (Impact Chat) agent."""

    @kernel_function(name="analyze_impact_query", description="Answer natural language questions about PR impact")
    def analyze_impact_query(self, query: str, context: str) -> str:
        return f"Query: {query}\nContext length: {len(context)} chars"


NAVIGATOR_SYSTEM_PROMPT = """You are the DriftWatch Navigator — an AI assistant that helps DevOps engineers understand the impact of a pull request.

You will receive:
- A user's natural language question
- Full context about the PR including: changed files, dependency graph, risk scores, affected services, blast radius analysis, and file summaries

Your job:
1. Answer the question clearly and concisely
2. Reference specific files, services, and risk scores from the context
3. Explain WHY things are risky, not just THAT they are risky
4. When asked about dependencies, trace the full chain
5. When asked to highlight or focus on files, return their IDs in the `highlight_nodes` array
6. Keep responses short (2-4 sentences) unless the user asks for detail

Return a JSON object with:
{
    "response": "Your natural language answer (supports markdown)",
    "highlight_nodes": ["file_path1", "file_path2"],
    "suggested_followups": ["question 1", "question 2"]
}

Be conversational, helpful, and specific. Use the actual file names and scores from context."""


@app.route(route="navigator", methods=["POST"])
def navigator(req: func.HttpRequest) -> func.HttpResponse:
    """
    Navigator agent — conversational Impact Chat for PR analysis.
    Receives: { query, pr_context: { files, dependency_graph, risk_scores, services, ... } }
    Returns: { response, highlight_nodes, suggested_followups }
    """
    import asyncio
    logging.info("[Navigator] Impact chat request received.")

    try:
        body = req.get_json()
    except Exception:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON"}), mimetype="application/json", status_code=400)

    query = body.get("query", "")
    pr_context = body.get("pr_context", {})

    if not query:
        return func.HttpResponse(json.dumps({"error": "No query provided"}), mimetype="application/json", status_code=400)

    # Build the context string for the AI
    context_parts = []
    context_parts.append(f"PR: {pr_context.get('pr_title', 'Unknown')} (#{pr_context.get('pr_number', '?')})")
    context_parts.append(f"Author: {pr_context.get('pr_author', 'unknown')}")
    context_parts.append(f"Repository: {pr_context.get('repo_full_name', 'unknown')}")
    context_parts.append(f"Files Changed: {pr_context.get('files_changed', 0)}")
    context_parts.append(f"Risk Score: {pr_context.get('risk_score', 0)}/100 ({pr_context.get('risk_level', 'unknown')})")
    context_parts.append(f"Decision: {pr_context.get('decision', 'pending')}")

    if pr_context.get("affected_services"):
        context_parts.append(f"Affected Services: {', '.join(pr_context['affected_services'])}")

    if pr_context.get("changed_files"):
        context_parts.append("\n--- Changed Files ---")
        for f in pr_context["changed_files"]:
            if isinstance(f, dict):
                context_parts.append(f"  {f.get('filename', f.get('file', '?'))} (score: {f.get('risk_score', '?')}, type: {f.get('change_type', '?')})")
            else:
                context_parts.append(f"  {f}")

    if pr_context.get("dependency_graph"):
        context_parts.append("\n--- Dependency Graph ---")
        for src, deps in pr_context["dependency_graph"].items():
            if isinstance(deps, list) and len(deps) > 0:
                context_parts.append(f"  {src} → {', '.join(deps)}")

    if pr_context.get("file_summaries"):
        context_parts.append("\n--- File Summaries ---")
        for path, info in pr_context["file_summaries"].items():
            if isinstance(info, dict):
                context_parts.append(f"  {path}: {info.get('summary', '')} | Risk: {info.get('risk', '')} | Affects: {info.get('affects', '')}")

    if pr_context.get("blast_summary"):
        context_parts.append(f"\nBlast Radius Summary: {pr_context['blast_summary']}")

    if pr_context.get("recommendation"):
        context_parts.append(f"AI Recommendation: {pr_context['recommendation']}")

    context_str = "\n".join(context_parts)
    user_prompt = f"PR Context:\n{context_str}\n\nUser Question: {query}"

    # Call Azure OpenAI via Semantic Kernel
    try:
        result = asyncio.run(sk_json_call(NAVIGATOR_SYSTEM_PROMPT, user_prompt, max_tokens=1500))
    except Exception as e:
        logging.error(f"[Navigator] SK call failed: {e}")
        result = {
            "response": f"I encountered an issue processing your question. Here's what I know: the PR changes {pr_context.get('files_changed', 0)} files with a risk score of {pr_context.get('risk_score', 0)}.",
            "highlight_nodes": [],
            "suggested_followups": ["What files were changed?", "Show me the risk breakdown"],
        }

    # Ensure required fields
    if "response" not in result:
        result["response"] = "I couldn't generate a response. Try rephrasing your question."
    if "highlight_nodes" not in result:
        result["highlight_nodes"] = []
    if "suggested_followups" not in result:
        result["suggested_followups"] = []

    # Content safety check
    if not content_safety_check(result["response"]):
        result["response"] = "Response filtered by content safety. Please rephrase your question."

    logging.info(f"[Navigator] Responded with {len(result['highlight_nodes'])} highlight nodes.")

    return func.HttpResponse(
        json.dumps(result),
        mimetype="application/json",
        status_code=200,
    )


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
                {"name": "navigator", "type": "SK Plugin", "status": "active"},
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
