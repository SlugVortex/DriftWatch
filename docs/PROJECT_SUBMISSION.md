# Project Submission: DriftWatch

**Project Title:** DriftWatch

**Tagline:** A Multi-Agent Pre-Deployment Risk Intelligence System That Catches Dangerous Code Before It Reaches Production.

---

## 1. The Problem: The 3 AM Outage Nobody Saw Coming

A pull request passes code review. CI is green. Two senior engineers approved it. It ships at 4 PM on a Thursday. By 3 AM, PagerDuty is screaming — three services are down because that "safe" config change deleted the route file that 47 endpoints depended on. Cost: $100,000–$500,000 per incident.

These outages are almost always *predictable*. The signals were there — in the blast radius, the incident history, the deployment timing. Nobody connected the dots because no tool existed to connect them. Today's DevOps tooling tells you *what* changed, but no tool answers the only question that matters: **"If I deploy this right now, what is the probability that it breaks production — and why?"**

---

## 2. Our Solution: DriftWatch

DriftWatch is an AI-native pre-deployment risk intelligence platform that intercepts every pull request and automatically answers that question — with evidence, a score, and a clear deploy/block verdict — before any human reviewer opens the diff.

- **Multi-Agent Intelligence Pipeline:** 4 agents (Archaeologist → Historian → Negotiator → Chronicler) orchestrated by Semantic Kernel map blast radius, correlate 90 days of incident history, make a deploy/block decision, and record outcomes for continuous learning — in under 30 seconds.
- **Deployment Weather Forecasting:** Scores environmental risk *independently* from code risk. A PR can be safe code but catastrophically timed — deploying during an active incident, peak traffic, with 3 other PRs shipping simultaneously. No other tool does this.
- **A Learning System:** The Chronicler records whether predictions matched reality and feeds accuracy back to the Historian. DriftWatch self-calibrates with every PR it analyzes.

---

## 3. Key Features Implemented

**6-Agent AI Pipeline on Azure AI Foundry** — All agents are Semantic Kernel plugins with DriftWatch-Safety RAI policy enforced:
- **Archaeologist** — Blast radius mapping via GitHub MCP tools. Per-file verdicts (safe/warning/critical) with concrete findings from actual source code.
- **Historian** — Risk score (0–100) using Azure AI Search (RAG) for semantic incident retrieval + Azure AI Language for key phrase correlation.
- **Negotiator** — Deploy/block decision with PR comment, GitHub Check Run, Teams Adaptive Card (Approve/Block), and Copilot Agent Mode issue creation.
- **Chronicler** — Learning agent feeding accuracy data back to Historian. Indexes outcomes into AI Search for growing RAG knowledge.
- **Navigator** — Interactive Impact Chat AI with OWASP-focused Security Agent mode.
- **SRE Agent** — Post-deployment auto-response with automated rollback via GitHub Actions `workflow_dispatch`.

**Deployment Weather System** — 5 environmental checks (concurrent deploys, active incidents, infra health, traffic windows, recent deploys). Weather ≥ 40 auto-escalates to `pending_review`.

**Interactive Visualization** — dagre-d3 DAG tree + SVG blast map with verdict-colored nodes (green/amber/red).

**Model Router** — gpt-4.1-mini for small PRs, auto-upgrades to gpt-4.1 for complex PRs.

**Human-in-the-Loop** — Teams Adaptive Cards with HMAC-signed callbacks. MRP audit trail archived to Blob Storage.

---

## 4. Technology Stack

**Backend:** Laravel 11.x (PHP 8.3+) on Azure App Service · **Agent Runtime:** Python 3.10 · Azure Functions V2 · Semantic Kernel SDK

**Azure AI Services (18 total):**
- **Azure AI Foundry** — Agent registration, Responses API, RAI enforcement, Operate tab monitoring
- **Semantic Kernel SDK** — Planner → Skills → Memory orchestration, 6 `@kernel_function` plugins
- **Azure OpenAI (GPT-4.1-mini + GPT-4.1)** — via Foundry + Model Router
- **Azure MCP Server** — 10 tools (GitHub read/write + DB query) via FastMCP
- **Azure AI Content Safety** — RAI guardrails on all outputs
- **Azure AI Search** — RAG semantic incident retrieval + outcome indexing
- **Azure AI Language** — Key phrase extraction + entity recognition
- **Azure Speech** — Neural TTS for accessibility
- **App Insights + OpenTelemetry** — `gen_ai.*` distributed tracing
- **Azure Service Bus** · **MySQL Flexible Server** · **Key Vault** · **Blob Storage** · **Monitor + Log Analytics**

**GitHub:** Copilot Agent Mode (remediation) · GitHub Action `driftwatch-analyze@v1` (Full + zero-infra Lightweight mode)

**Frontend:** Bootstrap 5 · ApexCharts · dagre-d3 · SVG blast map · vis.js · **Docker:** 4-service compose

---

## 5. Vision and Impact

The software industry loses $7 trillion annually to poor software quality. The shift-left movement pushed testing earlier. DriftWatch pushes *intelligence* earlier — to the moment a developer opens a pull request.

DriftWatch is not a linter. It is not a CI check. It is a risk intelligence system — the deployment equivalent of a weather forecast. It doesn't just tell you the temperature. It tells you whether to carry an umbrella.

DriftWatch is deployed, live, and running on Azure — end-to-end — today.
