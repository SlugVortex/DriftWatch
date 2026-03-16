<p align="center">
  <img src="https://i.ibb.co/8DcBjft0/driftwatch-logo.png" height="80" valign="middle"/>
  &nbsp;&nbsp;
</p>

<h1 align="center">DriftWatch</h1>

<div align="center">


**DriftWatch is a multi-agent pre-deployment risk intelligence system that catches dangerous code before it reaches production.**

[![Live Demo](https://img.shields.io/badge/🌐_Live_Demo-driftwatch.azurewebsites.net-0078D4?style=for-the-badge)](https://driftwatch.azurewebsites.net)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Azure AI Foundry](https://img.shields.io/badge/Azure_AI_Foundry-Agent_Service-0078D4?style=for-the-badge&logo=microsoftazure&logoColor=white)](https://azure.microsoft.com/products/ai-foundry)
[![Semantic Kernel](https://img.shields.io/badge/Semantic_Kernel-Orchestration-605DFF?style=for-the-badge&logo=microsoft&logoColor=white)](https://github.com/microsoft/semantic-kernel)
[![GPT-4.1](https://img.shields.io/badge/GPT--4.1-Azure_OpenAI-412991?style=for-the-badge&logo=openai&logoColor=white)](https://azure.microsoft.com/products/ai-services/openai-service)
[![Python](https://img.shields.io/badge/Python-3.10-3776AB?style=for-the-badge&logo=python&logoColor=white)](https://python.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

> *Built for the **Microsoft AI Dev Days Hackathon** — Challenge 2: Agentic DevOps*

</div>

---

## 📋 Table of Contents

1. [The Problem](#-the-problem)
2. [Our Solution](#-our-solution)
3. [Key Features](#-key-features)
4. [System Architecture](#️-system-architecture)
5. [The Six AI Agents](#-the-six-ai-agents)
6. [Deployment Weather System](#️-deployment-weather-system)
7. [The Chronicler Feedback Loop](#-the-chronicler-feedback-loop)
8. [Technology Stack](#️-technology-stack)
9. [Azure Services Used](#-azure-services-used-18)
10. [Project Screenshots](#-project-screenshots)
11. [Quick Start](#-quick-start)
12. [GitHub Action Integration](#-github-action-integration)
13. [Data Models](#-data-models)
14. [Responsible AI](#-responsible-ai)
15. [Team](#-team)

---

## 🧠 The Problem

A pull request passes code review. CI is green. Two senior engineers approved it. It ships at 4 PM on a Thursday. By 3 AM, PagerDuty is screaming — three services are down because that "safe" config change deleted the route file that 47 endpoints depended on. **Cost: $100,000–$500,000 per incident.**

These outages are almost always *predictable*. The signals were there — in the blast radius, the incident history, the deployment timing. Nobody connected the dots because no tool existed to connect them.

**Today's DevOps tooling tells you *what* changed. No tool answers the only question that matters:**

> *"If I deploy this right now, what is the probability that it breaks production — and why?"*

---

## 💡 Our Solution

DriftWatch is an **AI-native pre-deployment risk intelligence platform** that intercepts every pull request and automatically answers that question — with evidence, a score, and a clear deploy/block verdict — before any human reviewer opens the diff.

It achieves three things no other tool does:

- **Multi-Agent Intelligence:** 6 agents (Archaeologist → Historian → Negotiator → Chronicler → Navigator → SRE Agent) orchestrated by Semantic Kernel map blast radius, correlate 90 days of incident history, make a deploy/block decision, and record outcomes for continuous learning — in under 30 seconds.
- **Deployment Weather Forecasting:** Scores environmental risk *independently* from code risk. A PR can be safe code but catastrophically timed — deploying during an active incident, peak traffic, with 3 other PRs shipping simultaneously. No other tool does this.
- **A Learning System:** The Chronicler records whether predictions matched reality and feeds accuracy back to the Historian. DriftWatch self-calibrates with every PR it analyzes.

---

## ✨ Key Features

### 🔍 Blast Radius Mapping
The **Archaeologist** agent reads every changed file's full diff and source code, classifies each change by risk type (CSS = 2pts, auth middleware = 30pts, database migration = 25pts), follows import chains to build a dependency graph, and detects affected API endpoints and services — all in a single agent pass.

### 📊 Historical Risk Scoring
The **Historian** runs a 3-layer matching algorithm against 90 days of incident data: file-level matches (+25pts), service-level matches (+10pts), and change-type matches (+15pts). Enhanced with **Azure AI Search RAG** for semantic retrieval and **Azure AI Language** for key phrase correlation — it catches matches even when naming conventions differ.

### ⚖️ Intelligent Deploy Gating
The **Negotiator** makes the final approve/block/pending decision, posts a formatted risk assessment directly to the GitHub PR, creates a GitHub Check Run, sends a Microsoft Teams Adaptive Card with human approve/block buttons, and optionally creates GitHub Issues for Copilot Agent Mode to auto-remediate.

### 📝 Feedback Learning Loop
The **Chronicler** is DriftWatch's learning agent — it records predicted vs. actual outcomes and feeds accuracy data back to the Historian. Every PR makes the next prediction better. The RAG knowledge base grows over time as new outcomes are indexed to **Azure AI Search**.

### 🧭 Interactive Impact Chat
The **Navigator** powers a full conversational AI sidebar on the PR detail page. Ask natural language questions, get AI responses with highlighted dependency nodes, file cards with one-click actions, voice input (Web Speech API), and text-to-speech output via **Azure Speech Neural TTS**.

### 🛡️ SRE Auto-Response Agent
The **SRE Agent** monitors post-deployment health, correlates incidents with recent deployments, and can trigger automated rollbacks via GitHub Actions `workflow_dispatch` — closing the loop from pre-deployment risk prediction to post-deployment incident response.

### 🌤️ Deployment Weather System
5 independent environmental checks scored separately from code risk. Prevents "code is safe but the environment isn't" deployments. Scores ≥ 40 auto-escalate to `pending_review`.

### 📦 Model Router
Automatically selects `gpt-4.1-mini` for small PRs and upgrades to `gpt-4.1` for large or complex PRs (15+ files, 20k+ diff chars, 10+ correlated incidents). Cost-efficient without sacrificing quality when it matters.

### 🔗 GitHub Action (`driftwatch-analyze@v1`)
Works in two modes: **Full** (calls DriftWatch API, polls for results, creates GitHub Check) and **Lightweight** (zero infrastructure — runs in-runner with direct Azure OpenAI, no backend required).

### 📋 Merge-Readiness Pack (MRP)
Every PR gets a versioned, auditable MRP artifact: evidence chain (blast radius, incident history, CI/bot findings), decision audit trail, and archive to **Azure Blob Storage**. Re-analysis creates version 2, 3, ... — nothing is overwritten.

---

## 🏛️ System Architecture



<div align="center">
  <img src="https://i.ibb.co/9kQ9W1qK/final-archietcure-driftwatch.jpgE" alt="DriftWatch Architecture Diagram" width="800"/>
</div>


---

## 🤖 The Six AI Agents

All agents are **Semantic Kernel plugins** registered with **Azure AI Foundry**, with the **DriftWatch-Safety RAI policy** enforced on every call.

### 🔍 Archaeologist — Blast Radius Mapper

Reads the full PR diff and source code for every changed file. Classifies each file by change type, builds a dependency graph from import chains, detects affected services and API endpoints, and checks GitHub CI check runs and security bot findings (Snyk, CodeQL, Dependabot).

| Change Type | Risk Points |
|-------------|-------------|
| CSS / view-only | 2 |
| New standalone function | 5 |
| New API endpoint | 15 |
| Function signature change | 20 |
| Config file change | 20 |
| Database migration | 25 |
| Shared utility modification | 25 |
| Auth / middleware change | 30 |
| Failing CI checks | +25 |

**Output:** `BlastRadiusResult` — affected files, services, endpoints, dependency graph, change classifications, total blast radius score.

---

### 📊 Historian — Risk Scoring Engine

Correlates the blast radius with 90 days of historical incidents using a 3-layer matching algorithm, enhanced with **Azure AI Search RAG** for semantic retrieval and **Azure AI Language** for key phrase overlap detection.

| Layer | Match Type | Points |
|-------|-----------|--------|
| Layer 1 | File match — same file caused a past incident | +25 |
| Layer 2 | Service match — same service had a past incident | +10 |
| Layer 3 | Change type match — same type caused a past incident | +15 |

History score is capped at 40 points. Combined with the Archaeologist's blast radius score, the final risk score is clamped to 0–100.

**Output:** `RiskAssessment` — risk score, risk level (low/medium/high/critical), contributing factors, correlated incidents.

---

### ⚖️ Negotiator — Deploy Gatekeeper

Makes the final deployment decision, generates a formatted GitHub PR comment filtered through **Azure AI Content Safety**, creates a GitHub Check Run (pass/fail), sends a Teams Adaptive Card, and creates GitHub Issues for Copilot Agent Mode to auto-remediate flagged items.

| Risk Score | Decision |
|-----------|----------|
| 0–49 | ✅ APPROVED |
| 50–74 | ⏳ PENDING REVIEW |
| 75–100 | ❌ BLOCKED |

---

### 📝 Chronicler — Feedback Learning Agent

Records whether predictions matched reality. Compares predicted risk vs. actual incident occurrence. Feeds accuracy data back to the Historian and indexes new outcomes into **Azure AI Search** — so the RAG knowledge base grows with every PR analyzed. This is what makes DriftWatch a genuinely agentic, self-improving system.

---

### 🧭 Navigator — Impact Chat AI

Powers the conversational PR analysis sidebar. Accepts natural language questions, responds with markdown-rich answers, highlights dependency tree nodes, provides file cards with one-click actions (Explain, View Code, Why Risky?, Dependencies), and supports voice input/output via **Azure Speech Neural TTS** (en-US-JennyNeural).

---

### 🛡️ SRE Agent — Incident Auto-Response

Monitors post-deployment health, correlates incidents with recent deployments, and triggers automated rollback via GitHub Actions `workflow_dispatch` when confidence is high. Implements the **Azure SRE Agent pattern** — closing the loop from prediction to auto-healing.

---

## 🌤️ Deployment Weather System

DriftWatch scores **environmental deployment risk** independently from code risk — a pattern no other DevOps tool implements:

```
Deployment Weather Score (0–100)
├── Concurrent Deploys Check    (+20 pts) — Other PRs approved in last 30 min
├── Active Incidents Check      (+30 pts) — Unresolved incidents on same services
├── Infrastructure Health Check (+20 pts) — App Insights error rates / recent incidents
├── High Traffic Window Check   (+15 pts) — Peak hour detection
└── Recent Related Deploy Check (+10 pts) — Same services deployed in last 2h
```

| Weather Score | Conditions |
|--------------|-----------|
| 0–10 | ☀️ Clear Skies — good to deploy |
| 11–30 | 🌤️ Partly Cloudy — acceptable, proceed with monitoring |
| 31–50 | ⛈️ Storm Warning — conditions unfavorable, consider waiting |
| 51+ | 🌪️ Severe Storm — strongly recommend delaying |

Weather score ≥ 40 with an AI-approved PR automatically escalates to `pending_review` — because safe code deployed into an unsafe environment still causes outages.

---

## 🔄 The Chronicler Feedback Loop

DriftWatch's learning agent implements a genuine self-improvement cycle:

```
Pipeline Run #1
    Historian predicts: risk_score = 72 (high)
    Negotiator decides: BLOCK
    Chronicler records: predicted=72, incident_occurred=false
    Chronicler learns: "Over-predicted risk for config changes"
                           │
                           ▼
Pipeline Run #2 (similar PR)
    Historian receives: Chronicler accuracy data
    Historian adjusts: risk_score = 45 (medium) ← calibrated
    Negotiator decides: APPROVED
    Chronicler records: predicted=45, incident_occurred=false
    Chronicler learns: "Calibration improved — prediction accurate"
```

New outcomes are indexed to **Azure AI Search** by the Chronicler — the RAG knowledge base grows automatically, making every future prediction richer.

---

## 🛠️ Technology Stack

| Layer | Technology |
|-------|-----------|
| **Agent Service** | Azure AI Foundry (Responses API, RAI policy, Operate tab) |
| **Agent Framework** | Microsoft Semantic Kernel SDK (Planner → Skills → Memory) |
| **Model Router** | Intelligent gpt-4.1-mini ↔ gpt-4.1 selection by PR complexity |
| **MCP Server** | Azure MCP Server (10 GitHub + DB tools, FastMCP + HTTP fallback) |
| **AI Model** | Azure OpenAI GPT-4.1-mini + GPT-4.1 (via Foundry + Model Router) |
| **RAG** | Azure AI Search (semantic incident retrieval for Historian) |
| **Language** | Azure AI Language (key phrase extraction + entity recognition) |
| **Artifact Storage** | Azure Blob Storage (MRP audit trail archiving) |
| **AI Safety** | Azure AI Content Safety + DriftWatch-Safety RAI policy |
| **Backend** | Laravel 11.x (PHP 8.3+) on Azure App Service |
| **Agent Runtime** | Python Azure Functions V2 (6 Semantic Kernel plugins) |
| **Frontend** | Bootstrap 5 · Material Symbols · ApexCharts |
| **Visualizations** | dagre-d3 (DAG tree) · SVG (blast map) · vis.js (network) |
| **Database** | Azure Database for MySQL Flexible Server |
| **Observability** | OpenTelemetry · Application Insights (`gen_ai.*` spans) · Azure Monitor |
| **Notifications** | Microsoft Teams Adaptive Cards with HMAC-signed decision callbacks |
| **Accessibility** | Azure Speech Neural TTS (en-US-JennyNeural) |
| **CI/CD** | GitHub Action (`driftwatch-analyze@v1`) + GitHub Copilot Agent Mode |
| **Containers** | Docker multi-stage build + docker-compose (4 services) |

---

## ☁️ Azure Services Used (18)

| # | Service | Purpose |
|---|---------|---------|
| 1 | **Azure AI Foundry** | Agent registration, Responses API routing, RAI policy enforcement, Operate tab monitoring |
| 2 | **Azure OpenAI** | GPT-4.1-mini + GPT-4.1 for all 6 agents via Model Router |
| 3 | **Semantic Kernel SDK** | Agent orchestration framework — Planner → Skills → Memory |
| 4 | **Azure Functions V2** | Serverless Python hosting for all 6 SK plugins |
| 5 | **Azure MCP Server** | 10-tool registry (GitHub read/write + DB queries) for agent tool access |
| 6 | **Azure AI Content Safety** | RAI guardrails on all agent outputs before storage or posting |
| 7 | **Azure Database for MySQL** | All models, incidents, agent runs, pipeline configs |
| 8 | **Application Insights** | `gen_ai.*` telemetry, pipeline traces, error rates → Foundry Operate tab |
| 9 | **Azure Monitor** | Alerts, SLAs, diagnostics, Log Analytics workspace |
| 10 | **Azure Key Vault** | All production secrets injected at boot — never in env vars |
| 11 | **Azure Service Bus** | Async agent message queue for pipeline decoupling |
| 12 | **Azure Speech Services** | Neural TTS (en-US-JennyNeural) for chat and section narration |
| 13 | **Microsoft Teams** | Adaptive Cards + HMAC-signed approve/block decision callbacks |
| 14 | **GitHub Copilot** | Agent Mode — auto-remediates issues created by the Negotiator |
| 15 | **Azure SRE Agent** | Post-deployment health monitoring + automated rollback triggers |
| 16 | **Azure AI Language** | Key phrase extraction + entity recognition for semantic incident correlation |
| 17 | **Azure AI Search** | RAG vector search — Historian retrieves semantically relevant past incidents; Chronicler indexes outcomes |
| 18 | **Azure Blob Storage** | MRP artifact archiving — timestamped JSON audit trail per agent per PR |

---

## 📸 Project Screenshots

### Main Dashboard

<div align="center">
  <img src="https://hackboxpmeproduction.blob.core.windows.net/proj-6d54e0a1-efde-43de-95f0-271c463cc789-1773377836617/8f0487eb-f78a-4143-8d7e-9fe09e80d85c_1773640895359?sv=2026-02-06&st=2026-03-16T06%3A10%3A24Z&se=2026-03-16T06%3A20%3A24Z&skoid=4fec1579-ad60-4829-98b1-c37115c9dd25&sktid=975f013f-7f24-47e8-a7d3-abc4752bf346&skt=2026-03-16T06%3A10%3A24Z&ske=2026-03-16T06%3A20%3A24Z&sks=b&skv=2026-02-06&sr=b&sp=r&sig=TBvPO0wKJRTJ6K8IAcl%2FiLirB52C4o%2F%2FkOIlW9U48NA%3D" alt="DriftWatch Main Dashboard" width="800"/>
</div>

### Feature Showcase

<table>
  <tr>
    <td width="50%">
      <img src="https://hackboxpmeproduction.blob.core.windows.net/proj-6d54e0a1-efde-43de-95f0-271c463cc789-1773377836617/f7e71d48-ba11-493e-a5b6-a1c844186e70_1773640923469?sv=2026-02-06&st=2026-03-16T06%3A10%3A24Z&se=2026-03-16T06%3A20%3A24Z&skoid=4fec1579-ad60-4829-98b1-c37115c9dd25&sktid=975f013f-7f24-47e8-a7d3-abc4752bf346&skt=2026-03-16T06%3A10%3A24Z&ske=2026-03-16T06%3A20%3A24Z&sks=b&skv=2026-02-06&sr=b&sp=r&sig=hgXmcDmyfWlkkIskkHSo0M1x8LcyLAnVKV1QdXDrVW0%3D" alt="PR Detail — Deploy Verdict Banner" width="100%"/>
      <p align="center"><strong>Deploy Verdict Banner + Risk Score</strong></p>
    </td>
    <td width="50%">
      <img src="https://hackboxpmeproduction.blob.core.windows.net/proj-6d54e0a1-efde-43de-95f0-271c463cc789-1773377836617/08e9ede8-a0f0-4602-94eb-8041699ce8d7_1773641037412?sv=2026-02-06&st=2026-03-16T06%3A10%3A24Z&se=2026-03-16T06%3A20%3A24Z&skoid=4fec1579-ad60-4829-98b1-c37115c9dd25&sktid=975f013f-7f24-47e8-a7d3-abc4752bf346&skt=2026-03-16T06%3A10%3A24Z&ske=2026-03-16T06%3A20%3A24Z&sks=b&skv=2026-02-06&sr=b&sp=r&sig=6uhpyRlQVCdwcWDOthDBlj%2B9BrzwPhfTZ0qIgFuVIms%3D" alt=Incident History" width="100%"/>
      <p align="center"><strong>Incident History</strong></p>
    </td>
  </tr>
  <tr>
    <td width="50%">
      <img src="https://hackboxpmeproduction.blob.core.windows.net/proj-6d54e0a1-efde-43de-95f0-271c463cc789-1773377836617/6e886f59-efba-498f-960c-4825321dd8a1_1773641012437?sv=2026-02-06&st=2026-03-16T06%3A10%3A24Z&se=2026-03-16T06%3A20%3A24Z&skoid=4fec1579-ad60-4829-98b1-c37115c9dd25&sktid=975f013f-7f24-47e8-a7d3-abc4752bf346&skt=2026-03-16T06%3A10%3A24Z&ske=2026-03-16T06%3A20%3A24Z&sks=b&skv=2026-02-06&sr=b&sp=r&sig=1Zv5Dqv03FiOCxOca4EAfsXv4Mf7R1jX%2FNIoQFkLu2s%3D" alt="Dependency Tree DAG" width="100%"/>
      <p align="center"><strong>Dependecy Tree</strong></p>
    </td>
    <td width="50%">
      <img src="https://hackboxpmeproduction.blob.core.windows.net/proj-6d54e0a1-efde-43de-95f0-271c463cc789-1773377836617/edf0a89e-8471-4751-b66b-74a8332221a7_1773640969203?sv=2026-02-06&st=2026-03-16T06%3A10%3A24Z&se=2026-03-16T06%3A20%3A24Z&skoid=4fec1579-ad60-4829-98b1-c37115c9dd25&sktid=975f013f-7f24-47e8-a7d3-abc4752bf346&skt=2026-03-16T06%3A10%3A24Z&ske=2026-03-16T06%3A20%3A24Z&sks=b&skv=2026-02-06&sr=b&sp=r&sig=VQPYI5kJDrUrcDO7arcoVckocTWFu1sySrMrdmaOcVg%3D" alt="Animated Blast Map" width="100%"/>
      <p align="center"><strong>Responsible AI & Governance</strong></p>
    </td>
  </tr>
</table>

---

## 🚀 Quick Start

### Prerequisites

| Tool | Version |
|------|---------|
| PHP | 8.3+ |
| Composer | 2.x |
| MySQL | 8.0+ |
| Python | 3.10+ |
| Azure Functions Core Tools | v4 |

### Installation

1. **Clone the repository:**
    ```bash
    git clone https://github.com/your-org/DriftWatch.git
    cd DriftWatch
    ```

2. **Install PHP dependencies:**
    ```bash
    composer install
    ```

3. **Configure environment:**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4. **Set up the database:**
    ```bash
    php artisan migrate
    php artisan db:seed
    ```

5. **Start the server:**
    ```bash
    php artisan serve
    ```

6. **Start the agent runtime (separate terminal):**
    ```bash
    cd agents
    python -m venv .venv && source .venv/bin/activate
    pip install -r requirements.txt
    func start
    ```

7. **Start the MCP server (separate terminal):**
    ```bash
    cd agents && func start --port 8100
    ```

8. **Access:** Navigate to `http://localhost:8000`

### Docker (One Command)

```bash
cp .env.example .env          # Configure Azure keys
docker-compose up -d           # Start all 4 services (app, mysql, agents, mcp)
docker-compose exec app php artisan migrate --seed

# Dashboard:    http://localhost:8000
# Agents API:   http://localhost:7071
# MCP Server:   http://localhost:8100
```

### Demo Login Accounts

| Name | Email | Password | Role |
|------|-------|----------|------|
| Admin User | `admin@driftwatch.dev` | `password` | Full access |
| Sarah Chen | `sarah@driftwatch.dev` | `password` | Reviewer |
| James Wilson | `james@driftwatch.dev` | `password` | Reviewer |
| Demo Viewer | `viewer@driftwatch.dev` | `password` | Read-only |

### Environment Variables

```env
# App
APP_URL=http://localhost:8000

# Database
DB_HOST=your-mysql-host
DB_DATABASE=driftwatch
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Azure OpenAI (Model Router)
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com/
AZURE_OPENAI_API_KEY=your-key
AZURE_OPENAI_DEPLOYMENT=gpt-4.1-mini

# GitHub
GITHUB_TOKEN=ghp_...
GITHUB_WEBHOOK_SECRET=your-webhook-secret

# Azure Agent Functions
AGENT_ARCHAEOLOGIST_URL=https://driftwatch-agents.azurewebsites.net/api/archaeologist
AGENT_HISTORIAN_URL=https://driftwatch-agents.azurewebsites.net/api/historian
AGENT_NEGOTIATOR_URL=https://driftwatch-agents.azurewebsites.net/api/negotiator
AGENT_CHRONICLER_URL=https://driftwatch-agents.azurewebsites.net/api/chronicler
AGENT_SECURITY_URL=https://driftwatch-agents.azurewebsites.net/api/security

# Azure Speech (Neural TTS)
AZURE_SPEECH_ENDPOINT=https://eastus.api.cognitive.microsoft.com/
AZURE_SPEECH_KEY=your-key
AZURE_SPEECH_REGION=eastus

# Azure AI Search (RAG)
AZURE_AI_SEARCH_ENDPOINT=https://driftwatch-search.search.windows.net
AZURE_AI_SEARCH_KEY=your-admin-key
AZURE_AI_SEARCH_INDEX=driftwatch-incidents

# Microsoft Teams
TEAMS_WEBHOOK_URL=https://...
TEAMS_NOTIFY_ABOVE_SCORE=60

# Observability
APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=...
```

---

## 🔧 GitHub Action Integration

Add DriftWatch risk analysis to any repository with a single workflow file:

```yaml
# .github/workflows/driftwatch.yml
name: DriftWatch Risk Analysis
on:
  pull_request:
    types: [opened, synchronize]

jobs:
  analyze:
    runs-on: ubuntu-latest
    steps:
      - uses: driftwatch-analyze@v1
        with:
          driftwatch-url: ${{ secrets.DRIFTWATCH_URL }}
          risk-threshold: 75
          block-on-critical: true
          azure-openai-endpoint: ${{ secrets.AZURE_OPENAI_ENDPOINT }}
          azure-openai-key: ${{ secrets.AZURE_OPENAI_KEY }}
```

**Two modes:**
- **Full Mode** — Calls the DriftWatch API, polls for results, creates a native GitHub Check Run (pass/fail) with the full risk report.
- **Lightweight Mode** — Zero infrastructure. Runs entirely in the GitHub Actions runner using direct Azure OpenAI. No DriftWatch backend required.

---

## 📁 Project Structure

```
DriftWatch/
├── app/
│   ├── Http/Controllers/
│   │   ├── DriftWatchController.php      # All dashboard pages (26 methods)
│   │   └── GitHubWebhookController.php   # Webhook + 6-agent pipeline
│   └── Models/
│       ├── PullRequest.php               # Core entity with relationships
│       ├── BlastRadiusResult.php         # Archaeologist output
│       ├── RiskAssessment.php            # Historian output
│       ├── DeploymentDecision.php        # Negotiator output (MRP, weather)
│       ├── DeploymentOutcome.php         # Chronicler output
│       ├── Incident.php                  # Historical incidents (90-day)
│       ├── AgentRun.php                  # Full observability trace per agent
│       ├── Repository.php                # Connected GitHub repos
│       └── PipelineConfig.php            # Custom pipeline templates
│
├── agents/
│   ├── function_app.py                   # Azure Functions V2 — 6 SK plugins
│   └── mcp_server.py                     # MCP Server — 10 GitHub + DB tools
│
├── resources/views/driftwatch/
│   ├── index.blade.php                   # Dashboard
│   ├── show.blade.php                    # PR detail (6000+ lines)
│   ├── pull-requests.blade.php           # PR list
│   ├── incidents.blade.php               # Incident history
│   ├── analytics.blade.php               # Analytics
│   ├── settings.blade.php                # Pipeline configuration
│   ├── explainability.blade.php          # Scoring explainer
│   ├── governance.blade.php              # Responsible AI
│   ├── agent-map.blade.php               # Agent pipeline visualization
│   └── repositories.blade.php            # Repository management
│
├── routes/
│   ├── web.php                           # 26 web routes (auth-protected)
│   └── api.php                           # Impact chat, TTS, file preview, CI/CD
│
├── .github/
│   └── workflows/                        # GitHub Action (driftwatch-analyze@v1)
│
└── docker-compose.yml                    # 4-service stack (app, mysql, agents, mcp)
```

---

## 📊 Data Models

| Model | Purpose |
|-------|---------|
| `PullRequest` | Core entity — state machine: `pending → analyzing → scored → approved / blocked` |
| `BlastRadiusResult` | Archaeologist output — files, services, endpoints, dependency graph, change classifications |
| `RiskAssessment` | Historian output — risk score (0–100), level, contributing factors, correlated incidents |
| `DeploymentDecision` | Negotiator output — decision, MRP payload, weather checks, stacked PR detection |
| `DeploymentOutcome` | Chronicler output — predicted vs. actual, accuracy boolean, post-mortem notes |
| `Incident` | Historical incident log — used by Historian for 3-layer matching |
| `AgentRun` | Full observability trace — tokens, duration, cost, model, full I/O payload per agent |
| `Repository` | Connected GitHub repos — webhook config, auto-analyze toggle |
| `PipelineConfig` | Custom pipeline templates — per-environment thresholds, approval gates, retry policy |

---

## 🔐 Responsible AI

DriftWatch is built with responsible AI as a first-class engineering concern — not a checkbox:

- **Guardrails on Every Output:** Every agent's response is filtered through **Azure AI Content Safety** (hate, violence, sexual, self-harm — severity threshold 4) before storage, GitHub posting, or Teams delivery. The `DriftWatch-Safety` RAI policy is enforced at the Foundry level on every LLM call.
- **Grounding over Hallucination:** Scoring is deterministic and rule-based (the Archaeologist's point rubric, the Historian's 3-layer algorithm). LLM reasoning enriches the analysis; it does not determine the numbers. Pricing, scores, and thresholds are always explainable.
- **Explainability by Design:** The `/explainability` page shows every scoring rubric, formula, and threshold. Every agent decision has a full audit trail in `AgentRun` records with complete I/O payloads, token counts, duration, and cost.
- **Human-in-the-Loop:** Blocking decisions above the threshold generate Teams Adaptive Cards requiring human sign-off via HMAC-signed callbacks. Humans can always override — approve, block, or resume a paused pipeline.
- **Secrets Management:** All production secrets stored in **Azure Key Vault**, injected at boot time. Never in environment variables or source control.
- **Observability:** All `gen_ai.*` OpenTelemetry spans are captured in **Application Insights** and visible in the **Foundry Operate tab** — full end-to-end tracing from webhook to agent decision.

---

## 🏆 Hackathon Alignment

| Judging Criterion | Our Implementation |
|---|---|
| **Technological Implementation** | 18 Azure services · Semantic Kernel Planner → Skills → Memory · `gen_ai.*` OTel spans · Foundry Responses API + RAI policy · Model Router · RAG via AI Search · AI Language semantic matching |
| **Agentic Design & Innovation** | 6 specialized agents · Chronicler learning loop (self-calibrating) · Deployment Weather (unique) · MRP versioned audit trail · Human-in-the-loop with Teams callbacks · SRE auto-rollback |
| **Real-World Impact** | $100K–$500K outage prevention per incident · 30-second end-to-end pipeline · Zero-infra Lightweight GitHub Action mode · Works on any public GitHub repo |
| **UX & Presentation** | dagre-d3 DAG + SVG blast map · Impact Chat with voice I/O · Azure Speech TTS on all sections · Full-screen code editor with push-to-GitHub · Dark/light mode |
| **Microsoft Platform Adherence** | Azure AI Foundry (primary) · Semantic Kernel (framework) · Azure MCP Server · GitHub Copilot Agent Mode · App Insights Foundry Operate tab · Azure SRE Agent pattern |

---

## 👥 Team

| Member | GitHub |
|---|---|
| **Gary Bryan** | [![GitHub](https://img.shields.io/badge/GitHub-100000?style=flat-square&logo=github&logoColor=white)](https://github.com/SlugVortex) |
| **Adrian Tennant** | [![GitHub](https://img.shields.io/badge/GitHub-100000?style=flat-square&logo=github&logoColor=white)](https://github.com/10ANT) |
| **Malik Christopher** | [![GitHub](https://img.shields.io/badge/GitHub-100000?style=flat-square&logo=github&logoColor=white)](https://github.com/Mal-chris) |
| **Humani Fagan** | [![GitHub](https://img.shields.io/badge/GitHub-100000?style=flat-square&logo=github&logoColor=white)](https://github.com/cyanidealbedo) |

---

<div align="center">

**Built with ❤️ for the Microsoft AI Dev Days Hackathon**

*Powered by Azure AI Foundry · Semantic Kernel · GitHub Copilot · Azure OpenAI*

[![Azure](https://img.shields.io/badge/Microsoft_Azure-0078D4?style=flat-square&logo=microsoft-azure&logoColor=white)](https://azure.microsoft.com)
[![Semantic Kernel](https://img.shields.io/badge/Semantic_Kernel-605DFF?style=flat-square&logo=microsoft&logoColor=white)](https://github.com/microsoft/semantic-kernel)
[![OpenAI](https://img.shields.io/badge/Azure_OpenAI-412991?style=flat-square&logo=openai&logoColor=white)](https://azure.microsoft.com/products/ai-services/openai-service)
[![GitHub Actions](https://img.shields.io/badge/GitHub_Actions-2088FF?style=flat-square&logo=github-actions&logoColor=white)](https://github.com/features/actions)
[![Teams](https://img.shields.io/badge/Microsoft_Teams-6264A7?style=flat-square&logo=microsoftteams&logoColor=white)](https://teams.microsoft.com)

</div>
