# agents/register_agents.py
# DriftWatch — Azure AI Foundry Agent Registration Script
#
# Registers all 6 DriftWatch agents as Foundry agent definitions.
# Run this script once to set up agents in Azure AI Foundry portal.
#
# Usage:
#   python register_agents.py
#
# Prerequisites:
#   - Azure CLI logged in (az login)
#   - FOUNDRY_ENDPOINT env var set to your AI Foundry project endpoint
#   - azure-ai-projects SDK installed (pip install azure-ai-projects)

import os
import sys
import json
import logging

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("driftwatch.register")


# ---------------------------------------------------------------------------
# Agent Definitions — matches function_app.py SK plugins
# ---------------------------------------------------------------------------

AGENTS = [
    {
        "name": "Archaeologist",
        "description": "Blast Radius Mapper — Analyzes PR diffs to map affected files, services, endpoints, and dependency graphs. Uses file-level scoring rubric for risk classification.",
        "instructions": """You are The Archaeologist, a code analysis agent for DriftWatch.
Your job is to analyze a pull request diff and map its REAL blast radius — which files,
services, API endpoints, and downstream dependencies are affected by this change.

CRITICAL: You must READ the actual diff content, not just file names. Classify every changed
file using the scoring rubric and sum the scores.

Return structured JSON with: affected_files, affected_services, affected_endpoints,
dependency_graph, change_classifications (with risk_score per file), total_blast_radius_score,
risk_indicators, and summary.""",
        "model": "gpt-4.1-mini",
        "tools": ["read_pr_diff", "read_file", "list_files", "get_pr"],
    },
    {
        "name": "Historian",
        "description": "Risk Score Calculator — Correlates blast radius with 90-day incident history using 3-layer matching (file, service, change-type). Produces risk_score 0-100.",
        "instructions": """You are The Historian, a Site Reliability Engineer agent for DriftWatch.
Your job is to assess deployment risk by correlating the current PR's blast radius with
historical incident data using THREE layers of matching:

LAYER 1 — Direct File Match (25 pts each)
LAYER 2 — Service Match (10 pts each)
LAYER 3 — Change Type Match (15 pts each)

CAP historical contribution at 40 points. Add blast_radius_score for final score.
Return JSON with: risk_score (0-100), risk_level, historical_incidents, match_summary,
contributing_factors, and recommendation.""",
        "model": "gpt-4.1-mini",
        "tools": ["query_incidents", "query_outcomes"],
    },
    {
        "name": "Negotiator",
        "description": "Deploy Gatekeeper — Makes approve/block/pending_review decisions based on risk score. Posts PR comments and creates GitHub Issues for Copilot Agent Mode remediation.",
        "instructions": """You are The Negotiator, a deployment gatekeeper agent for DriftWatch.
Your job is to make the final deploy/block/review decision and craft a clear GitHub PR comment.

Decision rules:
- risk_score >= 75: BLOCK
- risk_score >= 50: PENDING REVIEW
- risk_score < 50: APPROVE

Return JSON with: decision, pr_comment (markdown), notification_message.
For high-risk PRs, a GitHub Issue will be created for Copilot Agent Mode to pick up.""",
        "model": "gpt-4.1-mini",
        "tools": ["post_comment", "create_check_run", "create_issue"],
    },
    {
        "name": "Chronicler",
        "description": "Feedback Loop Recorder (Learning Agent) — Compares predicted risk with actual deployment outcomes. Feeds accuracy data back to Historian for continuous improvement.",
        "instructions": """You are The Chronicler, a post-deployment analysis agent for DriftWatch.
Your job is to compare what was predicted with what actually happened after deployment,
and record whether the prediction was accurate.

This data feeds back to the Historian agent to improve future predictions — you are the
LEARNING LOOP that makes DriftWatch a truly agentic system.

Return JSON with: prediction_accurate, accuracy_delta, calibration_suggestion, post_mortem_notes.""",
        "model": "gpt-4.1-mini",
        "tools": [],
    },
    {
        "name": "Navigator",
        "description": "Interactive Impact Chat — Conversational AI that helps engineers explore PR impact. Answers questions about blast radius, dependencies, and risk with context-aware responses.",
        "instructions": """You are the DriftWatch Navigator — an AI assistant that helps DevOps engineers
understand the impact of a pull request.

Answer questions clearly and concisely. Reference specific files, services, and risk scores.
Explain WHY things are risky, not just THAT they are risky.

Return JSON with: response (markdown), highlight_nodes (file paths), suggested_followups.""",
        "model": "gpt-4.1-mini",
        "tools": ["read_file", "read_pr_diff"],
    },
    {
        "name": "SREAgent",
        "description": "Incident Auto-Response & Self-Healing — Monitors deployment health, correlates incidents with deployments, and triggers automated rollbacks via GitHub Actions workflow dispatch.",
        "instructions": """You are the DriftWatch SRE Agent — an automated Site Reliability Engineering agent
that monitors deployment health and triggers self-healing actions.

Your responsibilities:
1. ASSESS deployment health by correlating risk scores, weather conditions, and active incidents
2. RECOMMEND actions: rollback, page-oncall, scale-up, increase-monitoring, or no-action
3. CORRELATE incidents with recent deployments to identify root causes
4. PRIORITIZE remediation actions by blast radius and severity

Return JSON with: health_status, actions (with type/service/priority/reason), incident_correlations,
monitoring_recommendations, summary.""",
        "model": "gpt-4.1-mini",
        "tools": ["query_incidents"],
    },
]


# ---------------------------------------------------------------------------
# RAI Safety Policy Definition
# ---------------------------------------------------------------------------

RAI_POLICY = {
    "name": "DriftWatch-Safety",
    "description": "Responsible AI guardrails for DriftWatch agent outputs. Filters harmful content while allowing technical security analysis.",
    "categories": {
        "Hate": {"severity_threshold": 4, "action": "block"},
        "Violence": {"severity_threshold": 4, "action": "block"},
        "SelfHarm": {"severity_threshold": 4, "action": "block"},
        "Sexual": {"severity_threshold": 4, "action": "block"},
    },
    "custom_blocklists": [],
    "notes": "Allows technical security terminology (e.g., 'SQL injection', 'vulnerability') which is expected in DevOps risk analysis."
}


def register_with_foundry():
    """
    Registers agents with Azure AI Foundry using the azure-ai-projects SDK.
    Falls back to REST API registration if SDK is unavailable.
    """
    foundry_endpoint = os.environ.get("FOUNDRY_ENDPOINT", "")
    if not foundry_endpoint:
        logger.error("FOUNDRY_ENDPOINT environment variable not set.")
        logger.info("Set it to your AI Foundry project endpoint, e.g.:")
        logger.info("  export FOUNDRY_ENDPOINT=https://<project>.services.ai.azure.com")
        sys.exit(1)

    logger.info(f"Foundry endpoint: {foundry_endpoint}")

    # Try azure-ai-projects SDK
    try:
        from azure.ai.projects import AIProjectClient
        from azure.identity import DefaultAzureCredential

        credential = DefaultAzureCredential()
        project = AIProjectClient(endpoint=foundry_endpoint, credential=credential)
        logger.info("Connected to Azure AI Foundry via azure-ai-projects SDK.")

        # Register each agent
        for agent_def in AGENTS:
            try:
                agent = project.agents.create_agent(
                    model=agent_def["model"],
                    name=f"DriftWatch-{agent_def['name']}",
                    instructions=agent_def["instructions"],
                    description=agent_def["description"],
                )
                logger.info(f"  Registered: DriftWatch-{agent_def['name']} (ID: {agent.id})")
            except Exception as e:
                logger.warning(f"  Failed to register {agent_def['name']}: {e}")
                logger.info(f"  You can register this agent manually in the Foundry portal.")

        logger.info("\nAgent registration complete.")
        logger.info("Next steps:")
        logger.info("  1. Go to Azure AI Foundry portal → Agents tab")
        logger.info("  2. Verify all 6 agents appear")
        logger.info("  3. Assign DriftWatch-Safety RAI policy to each agent")
        logger.info("  4. Test with the /health endpoint")

    except ImportError:
        logger.warning("azure-ai-projects SDK not installed. Using manual registration output.")
        _print_manual_registration()

    except Exception as e:
        logger.error(f"Foundry registration failed: {e}")
        logger.info("Falling back to manual registration guide...")
        _print_manual_registration()


def _print_manual_registration():
    """Prints agent definitions for manual portal registration."""
    logger.info("\n" + "=" * 70)
    logger.info("MANUAL REGISTRATION — Copy these to Azure AI Foundry Portal")
    logger.info("=" * 70)

    for agent_def in AGENTS:
        logger.info(f"\n--- Agent: DriftWatch-{agent_def['name']} ---")
        logger.info(f"  Name: DriftWatch-{agent_def['name']}")
        logger.info(f"  Model: {agent_def['model']}")
        logger.info(f"  Description: {agent_def['description'][:100]}...")
        logger.info(f"  Tools: {', '.join(agent_def['tools']) if agent_def['tools'] else 'None'}")
        logger.info(f"  Instructions: (see AGENTS list in this file)")

    logger.info(f"\n--- RAI Policy: {RAI_POLICY['name']} ---")
    logger.info(f"  Create in: Azure AI Foundry → Safety → Content Filters")
    logger.info(f"  Categories: {json.dumps(RAI_POLICY['categories'], indent=4)}")

    # Write a JSON file for easy import
    output = {
        "agents": [
            {
                "name": f"DriftWatch-{a['name']}",
                "model": a["model"],
                "description": a["description"],
                "instructions": a["instructions"],
                "tools": a["tools"],
            }
            for a in AGENTS
        ],
        "rai_policy": RAI_POLICY,
    }
    output_path = os.path.join(os.path.dirname(__file__), "foundry_agents.json")
    with open(output_path, "w") as f:
        json.dump(output, f, indent=2)
    logger.info(f"\nAgent definitions exported to: {output_path}")


if __name__ == "__main__":
    logger.info("DriftWatch — Azure AI Foundry Agent Registration")
    logger.info("-" * 50)
    register_with_foundry()
