# DriftWatch — Comprehensive Application Guide

> **Version:** 1.0 | **Last Updated:** March 2026
> **Platform:** Microsoft AI Dev Days Hackathon — Challenge 2: Agentic DevOps

---

## Table of Contents

1. [What Is DriftWatch?](#1-what-is-driftwatch)
2. [System Architecture](#2-system-architecture)
3. [The Seven AI Agents](#3-the-seven-ai-agents)
4. [Agent Pipeline — End-to-End Flow](#4-agent-pipeline--end-to-end-flow)
5. [Database Models & Relationships](#5-database-models--relationships)
6. [Web Routes & Controllers](#6-web-routes--controllers)
7. [API Endpoints](#7-api-endpoints)
8. [Dashboard Pages](#8-dashboard-pages)
9. [PR Detail Page — Deep Dive](#9-pr-detail-page--deep-dive)
10. [Impact Chat System](#10-impact-chat-system)
11. [Code Preview & Edit System](#11-code-preview--edit-system)
12. [Blast Map Visualization](#12-blast-map-visualization)
13. [Dependency Tree (DAG)](#13-dependency-tree-dag)
14. [Code Review Session Management](#14-code-review-session-management)
15. [Text-to-Speech (TTS)](#15-text-to-speech-tts)
16. [Authentication & RBAC](#16-authentication--rbac)
17. [GitHub Integration](#17-github-integration)
18. [Azure Services Used](#18-azure-services-used)
19. [Mock Fallback Pattern](#19-mock-fallback-pattern)
20. [Configuration Reference](#20-configuration-reference)
21. [Frontend Stack & Conventions](#21-frontend-stack--conventions)
22. [File Structure](#22-file-structure)

---

## 1. What Is DriftWatch?

DriftWatch is a **multi-agent pre-deployment risk intelligence system** that analyzes GitHub Pull Requests before code is deployed. It uses 7 specialized AI agents — powered by Azure OpenAI (GPT-4.1-mini + GPT-4.1 via Model Router) and orchestrated by Microsoft Semantic Kernel through Azure AI Foundry — to answer one question:

> *"Is it safe to deploy this PR right now?"*

Each PR receives:
- A **blast radius analysis** (what files, services, and endpoints are affected)
- A **risk score from 0–100** (based on code changes + RAG-enhanced historical incident correlation + AI Language semantic matching)
- A **deployment decision** (APPROVE, BLOCK, or PENDING_REVIEW)
- A **feedback loop** (records prediction accuracy for continuous learning, indexes to RAG)
- A **security analysis** (OWASP vulnerability scanning via Security Agent)
- An **MRP artifact archive** (complete audit trail in Azure Blob Storage)

The result is posted as a comment on the GitHub PR and visible in the DriftWatch web dashboard.

### Hero Technologies (18 Azure Services)

| Technology | Purpose |
|-----------|---------|
| Azure AI Foundry | Agent registration, Responses API, RAI guardrails, Operate tab |
| Semantic Kernel SDK | Agent orchestration (Planner → Skills → Memory) |
| Model Router | Intelligent gpt-4.1-mini ↔ gpt-4.1 selection |
| Azure MCP Server | 10 GitHub + DB tools for agents |
| Azure AI Language | Key phrase extraction + entity recognition |
| Azure AI Search (RAG) | Semantic incident retrieval for Historian |
| Azure Blob Storage | MRP artifact archiving |
| Azure AI Content Safety | RAI guardrails on all outputs |
| OpenTelemetry | gen_ai.* spans → App Insights → Foundry Operate |
| GitHub Copilot Agent Mode | Automated issue remediation |
| Security Agent | OWASP vulnerability analysis in Navigator chat |
| SRE Agent | Incident auto-response + self-healing triggers |

---

## 2. System Architecture

```
GitHub PR Event
    │
    ▼
GitHub Webhook (HMAC-SHA256 verified)
    │
    ▼
Laravel 11.x Application (Azure App Service)
    │
    ├── Agent Orchestrator (Semantic Kernel Pattern)
    │       │
    │       ├── Step 1: Archaeologist Agent ──→ Azure OpenAI (GPT-4.1-mini)
    │       │       └── Returns: Blast radius, dependency graph, file classifications
    │       │
    │       ├── Step 2: Historian Agent ──→ Azure OpenAI + Historical Incidents DB
    │       │       └── Returns: Risk score (0-100), contributing factors
    │       │
    │       ├── Step 3: Negotiator Agent ──→ Azure OpenAI + Content Safety
    │       │       └── Returns: Deploy decision + PR comment
    │       │
    │       └── Step 4: Chronicler Agent ──→ Azure OpenAI
    │               └── Returns: Prediction accuracy, feedback notes
    │
    ├── Posts GitHub PR Comment (formatted markdown)
    ├── Sends Microsoft Teams Notification (Adaptive Card)
    └── Stores All Results in Azure MySQL
```

**Tech Stack:**

| Layer | Technology |
|-------|------------|
| Backend | Laravel 11.x (PHP 8.3+) |
| Frontend | Trezo Admin Template (Bootstrap 5, Material Symbols, ApexCharts) |
| AI Agents | Python Azure Functions V2 with Semantic Kernel |
| AI Model | Azure OpenAI GPT-4.1-mini |
| Database | Azure Database for MySQL Flexible Server |
| Visualizations | dagre-d3 (DAG trees), vis.js (network graphs), SVG (blast maps) |
| Observability | Application Insights + Azure Monitor + OpenTelemetry |

---

## 3. The Four AI Agents

### 3.1 Archaeologist — Blast Radius Mapper

**Role:** Analyzes every changed file in a PR to determine the full blast radius — what code, services, and API endpoints are affected.

**How It Works:**
1. Receives the PR's changed files with full diffs and source code (up to 8000 chars per file)
2. Fetches additional context from GitHub: full file contents, CI status, security bot findings
3. Classifies each file by change type and assigns a risk score:

| Change Type | Risk Points |
|-------------|-------------|
| CSS / view-only change | 2 |
| New standalone function | 5 |
| New API endpoint | 15 |
| Function signature change | 20 |
| Config file change | 20 |
| Shared utility modification | 25 |
| Database migration | 25 |
| Auth / middleware change | 30 |

4. Parses import/use statements to build a dependency graph (who depends on what)
5. Detects affected API endpoints from route files
6. Infers affected services from directory structure
7. Adds +25 points for failing CI checks, additional for security bot findings (Snyk, CodeQL)

**Output:** `BlastRadiusResult` containing affected files, services, endpoints, dependency graph, change classifications, and a total blast radius score.

**Azure Function Endpoint:** `POST /archaeologist`

---

### 3.2 Historian — Risk Scoring Engine

**Role:** Takes the blast radius and correlates it with historical incidents to calculate a final risk score from 0 to 100.

**How It Works:**
1. Receives the Archaeologist's blast radius output
2. Queries the last 90 days of historical incidents from the database
3. Runs a 3-layer matching algorithm:

| Layer | Match Type | Points Per Match |
|-------|-----------|-----------------|
| Layer 1 | **File match** — Same file caused a past incident | +25 per match |
| Layer 2 | **Service match** — Same service had a past incident | +10 per match |
| Layer 3 | **Change type match** — Same type of change caused a past incident | +15 per match |

4. Caps the history contribution at 40 points maximum
5. Adds the Archaeologist's blast radius score
6. Adds CI risk and bot risk additions
7. Final score is clamped to 0–100

**Risk Levels:**

| Score Range | Level | Color |
|-------------|-------|-------|
| 0–25 | Low | Green |
| 26–50 | Medium | Blue |
| 51–75 | High | Orange/Yellow |
| 76–100 | Critical | Red |

**Output:** `RiskAssessment` with risk score, risk level, correlated historical incidents, contributing factors, and a deployment recommendation.

**Azure Function Endpoint:** `POST /historian`

---

### 3.3 Negotiator — Deploy Gatekeeper

**Role:** Makes the final deployment decision (approve, block, or request review) and posts a formatted comment on the GitHub PR.

**How It Works:**
1. Receives the risk score, risk level, blast summary, and recommendation
2. Applies decision rules:

| Risk Score | Decision |
|-----------|----------|
| 0–49 | **APPROVED** — Safe to deploy |
| 50–74 | **PENDING_REVIEW** — Needs human review |
| 75–100 | **BLOCKED** — Too risky, do not deploy |

3. Checks for additional conditions:
   - Concurrent deployments in progress
   - Active freeze windows (e.g., holiday mornings)
   - On-call engineer availability
4. Generates a formatted markdown comment for the GitHub PR
5. Filters the comment through Azure AI Content Safety
6. Posts the comment via GitHub API

**GitHub Comment Format:**
```markdown
## 🎯 DriftWatch Risk Assessment

**Risk Score:** 65/100 (⚠️ High)

### 📊 Blast Radius
- 5 files changed across 3 services
- 2 API endpoints affected

### ⚡ Contributing Factors
- auth_middleware.php was involved in INC-042 (12 days ago)
- Database migration detected
- Shared utility file modified

### 📋 Recommendation
Manual review recommended before deploying to production.

---
*Analyzed by [DriftWatch](http://localhost:8000) — Pre-deployment Risk Intelligence*
```

**Output:** `DeploymentDecision` with decision, reasoning, weather checks, and the PR comment text.

**Azure Function Endpoint:** `POST /negotiator`

---

### 3.4 Chronicler — Feedback Loop Recorder

**Role:** Records the prediction accuracy after deployment to continuously improve the system.

**How It Works:**
1. Runs asynchronously after deployment
2. Compares predicted risk vs. actual outcome:
   - Did an incident actually occur?
   - What was the actual severity?
   - Which services were actually affected?
3. Calculates prediction accuracy:
   - **Accurate** = (Low risk predicted + no incident) OR (High risk predicted + incident occurred)
   - **Inaccurate** = Prediction didn't match reality
4. Records post-mortem notes for future model refinement
5. Feeds data back into the Historian's incident database

**Output:** `DeploymentOutcome` with predicted vs. actual comparison, accuracy boolean, and post-mortem notes.

**Azure Function Endpoint:** `POST /chronicler`

---

### 3.5 Navigator — Impact Chat Agent (5th Agent, Optional)

**Role:** Powers the conversational Impact Chat on the PR detail page. Not part of the main pipeline — it's an interactive assistant.

**How It Works:**
1. User types a natural language question about the PR (e.g., "What are the riskiest files?")
2. Navigator receives the full PR context (blast radius, risk assessment, file list)
3. Answers using GPT-4.1-mini with the PR data as context
4. Returns highlighted nodes to visually mark in the dependency tree
5. Suggests follow-up questions

**Fallback Chain:**
1. Try Navigator Azure Function → if unavailable:
2. Try Azure OpenAI direct call → if unavailable:
3. Use local keyword matching (no AI, pattern-based)

**Azure Function Endpoint:** `POST /navigator` (optional, falls back gracefully)

---

## 4. Agent Pipeline — End-to-End Flow

Here's exactly what happens when a PR is opened or updated on GitHub:

### Step 1: Webhook Reception
```
GitHub sends POST /webhooks/github
    → Laravel verifies HMAC-SHA256 signature
    → Checks event type: pull_request.opened or pull_request.synchronize
    → Creates/updates PullRequest record in database
    → Sets status to "analyzing"
    → Triggers pipeline
```

### Step 2: Archaeologist (Blast Radius)
```
Laravel sends POST to Azure Function /archaeologist
    → Receives PR diff, changed files, full file contents
    → Agent classifies each file (risk type + score)
    → Agent builds dependency graph from imports
    → Agent detects affected services and endpoints
    → Returns BlastRadiusResult
    → Laravel stores in blast_radius_results table
    → Laravel stores AgentRun record (input, output, timing, tokens)
```

### Step 3: Historian (Risk Scoring)
```
Laravel fetches historical incidents (last 90 days) from database
Laravel sends POST to Azure Function /historian
    → Receives blast radius + historical incidents
    → Agent runs 3-layer matching algorithm
    → Agent calculates final risk score (0-100)
    → Returns RiskAssessment
    → Laravel stores in risk_assessments table
    → Laravel stores AgentRun record
    → PR status updated to "scored"
```

### Step 4: Negotiator (Decision)
```
Laravel sends POST to Azure Function /negotiator
    → Receives risk score + blast summary
    → Agent makes deployment decision
    → Agent generates formatted PR comment
    → Comment filtered through Content Safety
    → Returns DeploymentDecision
    → Laravel stores in deployment_decisions table
    → Laravel stores AgentRun record
```

### Step 5: GitHub Comment
```
Laravel posts markdown comment to GitHub PR via API
    → Comment includes: risk score, blast radius, factors, recommendation
    → PR status updated to: approved, blocked, or scored (pending review)
```

### Step 6: Teams Notification
```
Laravel sends Adaptive Card to Microsoft Teams webhook
    → Card includes: risk score, decision, affected services
    → Card includes action buttons: Approve, Block, View in DriftWatch
    → Buttons link to /api/decisions/{id}/approve or /block with HMAC token
```

### Step 7: Chronicler (Feedback)
```
Laravel sends POST to Azure Function /chronicler
    → Records predicted risk score
    → Compares with actual deployment outcome (later)
    → Stores prediction accuracy
    → Laravel stores in deployment_outcomes table
```

### Total Pipeline Time
Typical: 8–15 seconds end-to-end (with live Azure Functions)
Mock mode: 1–3 seconds (using code-aware local heuristics)

---

## 5. Database Models & Relationships

### Entity Relationship Diagram

```
Repository (1) ──→ (N) PullRequest
                         │
                         ├── (1) BlastRadiusResult    ← Archaeologist output
                         ├── (1) RiskAssessment       ← Historian output
                         ├── (1) DeploymentDecision   ← Negotiator output
                         ├── (1) DeploymentOutcome    ← Chronicler output
                         └── (N) AgentRun             ← Debug/observability traces

Incident (standalone) ← Historical data for Historian correlation
PipelineConfig (standalone) ← User-customizable pipeline rules
User (standalone) ← Authentication with roles
```

### Model Details

#### PullRequest
| Field | Type | Description |
|-------|------|-------------|
| `github_pr_id` | bigint | GitHub's internal PR ID |
| `repo_full_name` | string | e.g., "owner/repo" |
| `pr_number` | integer | PR number (#123) |
| `pr_title` | string | PR title text |
| `pr_author` | string | GitHub username |
| `pr_url` | string | Full GitHub URL |
| `base_branch` | string | Target branch (e.g., main) |
| `head_branch` | string | Source branch |
| `files_changed` | integer | Number of files changed |
| `additions` | integer | Lines added |
| `deletions` | integer | Lines deleted |
| `status` | enum | pending, analyzing, scored, approved, blocked, deployed, failed |
| `target_environment` | string | dev, staging, production |
| `pipeline_template` | string | Pipeline configuration name |
| `pipeline_paused` | boolean | Whether pipeline is paused for manual review |
| `paused_at_stage` | string | Which stage paused at |

**Computed Accessors:**
- `status_color` → Bootstrap color class (primary, success, danger, warning, info)
- `risk_score_value` → Integer from related RiskAssessment
- `risk_color` → Color based on risk score (danger > 75, warning > 50, info > 25, success)

#### BlastRadiusResult
| Field | Type | Description |
|-------|------|-------------|
| `affected_files` | JSON array | List of all affected file paths |
| `affected_services` | JSON array | Services/modules impacted |
| `affected_endpoints` | JSON array | API endpoints affected |
| `dependency_graph` | JSON object | `{"changed_file": ["dependent_file_1", "dependent_file_2"]}` |
| `file_descriptions` | JSON object | AI-generated description per file |
| `change_classifications` | JSON array | Per-file risk: `{file, change_type, risk_score, reasoning}` |
| `code_analysis` | JSON | Full code analysis with file contents and diffs |
| `total_affected_files` | integer | Count |
| `total_affected_services` | integer | Count |
| `summary` | text | Plain English blast radius description |

#### RiskAssessment
| Field | Type | Description |
|-------|------|-------------|
| `risk_score` | integer | 0–100 risk score |
| `risk_level` | enum | low, medium, high, critical |
| `historical_incidents` | JSON array | Correlated past incidents with match scores |
| `contributing_factors` | JSON array | Why the score is what it is |
| `recommendation` | text | Deployment guidance text |

#### DeploymentDecision
| Field | Type | Description |
|-------|------|-------------|
| `decision` | enum | approved, blocked, pending_review |
| `decided_by` | string | "AI Agent" or "Manual Override: username" |
| `decided_at` | timestamp | When decision was made |
| `has_concurrent_deploys` | boolean | Other deployments active? |
| `in_freeze_window` | boolean | In a deploy freeze? |
| `notified_oncall` | boolean | Was on-call notified? |
| `notification_message` | text | Full notification content |
| `mrp_payload` | JSON | Modular Release Process data |
| `mrp_version` | integer | MRP schema version |
| `weather_score` | integer | 0–100 deployment conditions |
| `weather_checks` | JSON array | Individual condition checks |
| `stacked_pr_ids` | JSON array | Related PR IDs |
| `combined_blast_radius_score` | integer | Aggregate score across stacked PRs |

#### DeploymentOutcome
| Field | Type | Description |
|-------|------|-------------|
| `predicted_risk_score` | integer | What the system predicted |
| `incident_occurred` | boolean | Did an incident happen? |
| `actual_severity` | integer | Actual severity (1–5, null if no incident) |
| `actual_affected_services` | JSON array | Services actually impacted |
| `prediction_accurate` | boolean | Was the prediction correct? |
| `post_mortem_notes` | text | Lessons learned |

#### Incident
| Field | Type | Description |
|-------|------|-------------|
| `incident_id` | string | e.g., "INC-001" |
| `title` | string | Incident title |
| `description` | text | What happened |
| `severity` | integer | 1 (P1 Critical) to 5 (P5 Informational) |
| `affected_services` | JSON array | Services that went down |
| `affected_files` | JSON array | Files that caused the incident |
| `root_cause_file` | string | Primary file responsible |
| `change_type` | string | e.g., "changed_function_signature" |
| `duration_minutes` | integer | How long the incident lasted |
| `engineers_paged` | integer | How many people were paged |
| `occurred_at` | timestamp | When incident started |
| `resolved_at` | timestamp | When incident was resolved |

#### AgentRun
| Field | Type | Description |
|-------|------|-------------|
| `agent_name` | string | archaeologist, historian, negotiator, chronicler |
| `status` | enum | scored, insufficient_data, error |
| `input_payload` | JSON | What was sent to the agent |
| `output_payload` | JSON | What the agent returned |
| `score_contribution` | integer | Points this agent added |
| `reasoning` | text | Agent's explanation |
| `tokens_used` | integer | Azure OpenAI tokens consumed |
| `cost_usd` | decimal | Cost of this agent call |
| `duration_ms` | integer | Execution time in milliseconds |
| `model_used` | string | e.g., "gpt-4.1-mini" |
| `input_hash` | string | For deduplication |

#### Repository
| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Repository name |
| `full_name` | string | "owner/repo" format |
| `owner` | string | GitHub owner/org |
| `default_branch` | string | e.g., "main" |
| `github_url` | string | Full GitHub URL |
| `webhook_secret` | string | HMAC verification secret |
| `is_active` | boolean | Is monitoring enabled? |
| `auto_analyze` | boolean | Auto-analyze new PRs? |
| `last_synced_at` | datetime | Last sync timestamp |

#### PipelineConfig
| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Template name |
| `rules` | JSON | Risk thresholds per environment |
| `approval_gates` | JSON | Approval requirements |
| `retry_policy` | JSON | Per-agent retry settings |
| `is_active` | boolean | Currently active? |

#### User
| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Display name |
| `email` | string | Login email |
| `password` | string | Bcrypt hashed |
| `role` | string | admin, reviewer, or viewer |
| `avatar_color` | string | Hex color for avatar circle |

**Methods:**
- `isAdmin()` → Full access
- `isReviewer()` → Can approve/block PRs
- `isViewer()` → Read-only access
- `canApprove()` → admin or reviewer
- `canEdit()` → admin or reviewer
- `initials()` → First letter of each word in name

---

## 6. Web Routes & Controllers

### DriftWatch Routes (all require authentication)

| Method | URL | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/driftwatch` | `index()` | Main dashboard |
| GET | `/driftwatch/pull-requests` | `pullRequests()` | PR list with filters |
| GET | `/driftwatch/pr/{id}` | `show()` | PR detail page |
| POST | `/driftwatch/pr/{id}/approve` | `approve()` | Manual approve |
| POST | `/driftwatch/pr/{id}/block` | `block()` | Manual block |
| POST | `/driftwatch/analyze` | `analyzePr()` | Analyze a new PR by URL |
| POST | `/driftwatch/pr/{id}/reanalyze` | `reanalyze()` | Re-run pipeline |
| POST | `/driftwatch/pr/{id}/resume-pipeline` | `resumePipeline()` | Resume paused pipeline |
| POST | `/driftwatch/pr/{id}/update-environment` | `updateEnvironment()` | Change target env |
| POST | `/driftwatch/pr/{id}/update-template` | `updateTemplate()` | Switch pipeline template |
| GET | `/driftwatch/incidents` | `incidents()` | Incident history |
| GET | `/driftwatch/analytics` | `analytics()` | Analytics dashboard |
| GET | `/driftwatch/settings` | `settings()` | Pipeline settings |
| POST | `/driftwatch/settings/pipeline` | `savePipelineConfig()` | Save settings |
| POST | `/driftwatch/settings/pipeline/reset` | `resetPipelineConfig()` | Reset to defaults |
| GET | `/driftwatch/agents/{agent}` | `agentStatus()` | Agent detail page |
| GET | `/driftwatch/agent-map` | `agentMap()` | Agent pipeline map |
| GET | `/driftwatch/governance` | `governance()` | Responsible AI page |
| GET | `/driftwatch/explainability` | `explainability()` | How scoring works |
| GET | `/driftwatch/repositories` | `repositories()` | Connected repos |
| POST | `/driftwatch/repositories/connect` | `connectRepository()` | Add repo |
| GET | `/driftwatch/repositories/{id}` | `showRepository()` | Repo detail |
| POST | `/driftwatch/repositories/{id}/sync` | `syncRepository()` | Sync PRs |
| POST | `/driftwatch/repositories/{id}/toggle-auto-analyze` | `toggleAutoAnalyze()` | Toggle auto-analysis |
| POST | `/driftwatch/repositories/{id}/analyze-all` | `analyzeAllPrs()` | Analyze all open PRs |
| DELETE | `/driftwatch/repositories/{id}` | `disconnectRepository()` | Remove repo |

### Auth Routes (no middleware)

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/login` | Login form |
| POST | `/login` | Submit credentials |
| POST | `/logout` | Logout (invalidates session) |

### Webhook Route (no auth, signature-verified)

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/webhooks/github` | GitHub webhook receiver |

---

## 7. API Endpoints

All API routes are in `routes/api.php`.

### GET /api/incidents
**Purpose:** Fetch historical incidents for the Historian agent.
**Query Params:** `services` (comma-separated service names)
**Returns:** JSON array of incidents from the last 90 days (max 30).
**Used by:** Historian agent during pipeline execution.

### POST /api/analyze
**Purpose:** Trigger PR analysis from a GitHub Action or external tool.
**Body:** `{ pr_number, repo_full_name }`
**Returns:** `{ job_id, status, pr_id, risk_score, risk_level, decision, affected_services, summary }`
**Flow:** Fetches PR from GitHub, runs full pipeline synchronously, returns results.

### GET /api/jobs/{id}/status
**Purpose:** Poll analysis job status (for CI/CD integration).
**Returns:** `{ status, pr_id, risk_score, risk_level, decision, affected_services, summary }`

### POST /api/file-preview
**Purpose:** Fetch source code from GitHub for the code preview modal.
**Body:** `{ pr_id, file_path }`
**Returns:** `{ file_path, content, diff, language, size, sha, source }`
**Source field values:**
- `"github"` — Fetched live from GitHub API
- `"cached"` — From pipeline's code_analysis data
- `"unavailable"` — File not found

### POST /api/impact-chat
**Purpose:** Conversational AI for exploring PR impact.
**Body:** `{ pr_id, query }`
**Returns:** `{ response, highlight_nodes, suggested_followups, source }`
**Fallback chain:**
1. Navigator Azure Function agent
2. Azure OpenAI direct call (with PR context as system prompt)
3. Local keyword matching (no AI required)

**Source field values:**
- `"agent"` — Navigator Azure Function responded
- `"openai"` — Azure OpenAI direct response
- `"local"` — Local keyword matching fallback

### POST /api/file-update
**Purpose:** Push code edits back to GitHub from the UI.
**Body:** `{ pr_id, file_path, content, sha, commit_message }`
**Returns:** `{ success, new_sha, commit_sha, message }`
**How:** Uses GitHub Contents API (PUT) to commit directly to the PR's head branch.

### POST /api/review-all
**Purpose:** Trigger AI review of a single file (called sequentially for all files).
**Body:** `{ pr_id, file_path }`
**Returns:** Same format as impact-chat response (reuses the endpoint internally).
**Used by:** The "Review All" button in the UI.

### POST /api/tts
**Purpose:** Text-to-speech proxy for Azure Speech Services.
**Body:** `{ text }` (max 3000 characters)
**Returns:** MP3 audio blob (`audio/mpeg`)
**Used by:** Speaker icons on chat messages and section titles.

### GET /api/decisions/{id}/approve
**Purpose:** Approve PR from Microsoft Teams notification card.
**Query:** `token` (HMAC-verified)
**Action:** Updates decision to "approved", records in MRP audit trail.

### GET /api/decisions/{id}/block
**Purpose:** Block PR from Microsoft Teams notification card.
**Query:** `token` (HMAC-verified)
**Action:** Updates decision to "blocked", records in MRP audit trail.

---

## 8. Dashboard Pages

### Main Dashboard (`/driftwatch`)
- **Analyze PR form** — Paste a GitHub PR URL (e.g., `https://github.com/owner/repo/pull/123`) and click Analyze
- **"How It Works" pipeline visual** — Shows the 4 agents with icons and flow arrows
- **Summary stat cards:**
  - Total PRs Analyzed
  - Average Risk Score
  - Incidents Prevented (blocked deployments)
  - Prediction Accuracy (%)
- **Risk score distribution chart** — ApexCharts bar chart showing low/medium/high/critical counts
- **Decision breakdown pie chart** — Approved vs. blocked vs. pending
- **Recent PRs list** — Latest analyzed PRs with risk score, decision, author, and status

### Pull Requests (`/driftwatch/pull-requests`)
- Filterable list of all analyzed PRs
- Filters: Status dropdown (all, pending, analyzing, scored, approved, blocked) and search
- Columns: PR number, title, repo, author, risk score, decision, status, date
- Paginated (20 per page)

### Incidents (`/driftwatch/incidents`)
- Historical incident log (seeded with 14 demo incidents)
- Columns: ID, title, severity (P1–P5 with color badges), affected services, duration, engineers paged, root cause file
- Sorted by occurrence date (newest first)
- Paginated (20 per page)

### Analytics (`/driftwatch/analytics`)
- **Risk distribution chart** — Breakdown by level (low/medium/high/critical)
- **Risk score trend** — Line chart over time
- **Top risky services** — Bar chart of services with highest aggregate risk
- **Decision breakdown** — Approved vs. blocked statistics
- **Prediction accuracy trend** — How accurate the system's predictions have been
- **Agent success rates** — Table showing each agent's success percentage

### Agent Map (`/driftwatch/agent-map`)
- Visual pipeline showing all 4 agents in sequence
- Status indicators per agent (idle, active, complete)
- Metrics: total runs, success rate, average execution time
- Recent 10 PR traces with timing data
- Observability dashboard: total traces, error rate, p50/p95 latency

### Agent Detail (`/driftwatch/agents/{agent}`)
- Individual page for each agent (archaeologist, historian, negotiator, chronicler)
- Agent description and capabilities
- Recent 10 agent runs with input/output payloads
- Success rate and average duration
- Token usage and cost tracking

### Governance (`/driftwatch/governance`)
- DriftWatch principles and responsible AI practices
- Model explainability overview
- Recent deployment decision audit trail
- Bias mitigation strategies
- Azure Content Safety integration documentation

### Explainability (`/driftwatch/explainability`)
- Detailed explanation of how risk scoring works
- Archaeologist scoring rubric (file-by-file)
- Historian 3-layer matching algorithm explained
- Negotiator decision thresholds
- Interactive example scenarios with walk-throughs

### Settings (`/driftwatch/settings`)
- Pipeline template configuration
- Risk thresholds per environment (dev, staging, production)
- Approval gate requirements
- Agent retry policies
- Webhook URL configuration (Slack, Teams)
- GitHub token validation

### Repositories (`/driftwatch/repositories`)
- List of connected GitHub repositories
- Connect new repo (validates via GitHub API, creates webhook)
- Per-repo: toggle auto-analyze, sync PRs, analyze all open PRs, disconnect
- Shows PR count and last sync time per repo

---

## 9. PR Detail Page — Deep Dive

The PR detail page (`/driftwatch/pr/{id}`) is the most feature-rich page in the application. Here's every component:

### 9.1 Decision Banner
- Full-width banner at the top showing the deployment decision
- Color-coded: green (approved), red (blocked), yellow (pending review)
- Animated risk score with pulse ring effect
- Shows decided_by (AI Agent or Manual Override)

### 9.2 PR Summary Card
- PR title, number, author, repository
- Branch info: head → base
- Stats: files changed, additions, deletions
- Status badge
- Link to GitHub PR

### 9.3 Action Buttons
- **Approve** (green) — Manual approve override (admin/reviewer only)
- **Block** (red) — Manual block override (admin/reviewer only)
- **Re-analyze** — Trigger fresh pipeline run
- **Resume Pipeline** — Continue a paused pipeline

### 9.4 Blast Radius Visualization
Three visualization modes:
1. **Animated Concentric Rings** — SVG rings showing impact scale, risk-colored nodes
2. **Dynamic Network Graph** — vis.js interactive force-directed graph
3. **Structural DAG** — dagre-d3 hierarchical dependency tree (see Section 13)

### 9.5 Impact Chat (see Section 10)
### 9.6 Code Preview Modal (see Section 11)

### 9.7 Risk Assessment Card
- Risk score display (0–100) with color gradient
- Score breakdown showing Archaeologist + Historian contributions
- Historical incident correlations (matched incidents with relevance)
- Contributing factors (bullet list)
- Deployment recommendation text

### 9.8 Deployment Decision Details
- Decision reasoning from the Negotiator
- MRP (Modular Release Process) payload viewer
- Weather checks: freeze windows, concurrent deploys, on-call status
- Audit trail for Teams notification approve/block callbacks

### 9.9 Change Classifications Table
- File-by-file risk breakdown table
- Columns: file path, change type, risk score, reasoning, dependency count
- Sortable and filterable
- Click any file to open it in the code preview modal

### 9.10 Agent Debug Panel
- Expandable timeline of all agent runs for this PR
- For each agent: status, input payload (JSON viewer), output payload, execution time, token count, cost
- Color-coded status badges (scored = green, error = red, insufficient_data = yellow)
- Useful for debugging why an agent gave a particular score

---

## 10. Impact Chat System

The Impact Chat is a conversational AI panel on the PR detail page that lets users ask natural language questions about the PR's impact.

### How It Works

1. **User types a question** in the chat input (e.g., "What are the riskiest files?")
2. **Frontend sends POST to `/api/impact-chat`** with `{ pr_id, query }`
3. **Backend processes through fallback chain:**
   - **Step 1:** Try Navigator Azure Function agent
   - **Step 2:** If unavailable, call Azure OpenAI directly with PR context as system prompt
   - **Step 3:** If unavailable, use local keyword matching (no AI needed)
4. **Response rendered** with markdown formatting (bold, italic, code, lists, headers, code blocks)
5. **Nodes highlighted** in the dependency tree based on `highlight_nodes` array
6. **Follow-up suggestions** displayed as clickable buttons

### Chat Features

| Feature | Description |
|---------|-------------|
| **Rich markdown rendering** | `**bold**`, `*italic*`, `` `code` ``, code blocks, lists, headers |
| **File cards** | Clickable cards in chat with risk badges and quick action buttons |
| **Quick actions per file** | Explain, Dependencies, Why Risky?, Impact, View Code |
| **Voice input** | Web Speech API speech-to-text (microphone icon) |
| **Voice output (TTS)** | Speaker icons on messages — reads response aloud via Azure Speech |
| **Node highlighting** | Chat responses can highlight files in the dependency tree |
| **Suggested follow-ups** | AI suggests next questions based on conversation |
| **Context injection** | If you click a file in the tree, then ask "explain this", it knows which file |
| **Chat export** | Download entire conversation as markdown file |
| **Typing indicator** | Animated dots while AI is processing |
| **Source badge** | Shows whether response came from Agent, Azure OpenAI, or Local analysis |

### Local Fallback Keywords

When AI is unavailable, the chat handles these queries locally:

| Query Pattern | Response |
|--------------|----------|
| "highest risk", "most dangerous", "riskiest" | Top 5 files by risk score |
| "service", "affected service" | List of affected services |
| "depend", "downstream", "import" | Files with downstream dependencies |
| "summary", "overview", "what changed" | Impact overview with counts |
| "auth", "security" | Auth-related files |
| "migration", "database" | Database-related files |
| Any file name | Details about that specific file |

---

## 11. Code Preview & Edit System

### Code Preview Modal

The code preview modal lets you view any file in the PR with syntax highlighting.

**Opening the Modal:**
- Click any file card in the Impact Chat
- Click "View Code" quick action on a file card
- Click a file in the change classifications table
- Click a node in the dependency tree

**Modal Features:**

| Feature | Description |
|---------|-------------|
| **Full file source code** | With line numbers, Catppuccin dark theme |
| **Diff view** | Toggle between source and diff (colored additions/deletions) |
| **Split-view mode** | Compare two files side-by-side with resizable drag handle |
| **Multi-file tabs** | Open multiple files, switch between them |
| **Copy to clipboard** | Copy full file content |
| **GitHub link** | Jump to file on GitHub |
| **File stats** | Size, line count, language |
| **Resizable** | Drag edges to resize modal (min 480px wide, min 300px tall) |
| **Dock mode** | Minimize to left side of screen, keep chat visible |
| **Keyboard shortcuts** | Escape to close |

### Code Editing & Push to GitHub

When in the code preview modal:

1. Click **Edit** button to switch to edit mode
2. File content becomes an editable `<textarea>`
3. Make your changes
4. Click **Push to GitHub** button
5. System calls `POST /api/file-update` with:
   - PR ID, file path, new content, file SHA (for conflict detection), commit message
6. GitHub Contents API commits the change to the PR's head branch
7. New SHA returned and stored for subsequent edits

**Note:** This effectively lets a reviewer fix code and push directly to the PR without leaving DriftWatch.

---

## 12. Blast Map Visualization

The blast map is an animated SVG showing the impact scale of a PR.

### Concentric Ring View
- **Center:** PR origin node (purple)
- **Ring 1:** Directly changed files (red nodes)
- **Ring 2:** Downstream dependencies (yellow nodes)
- **Ring 3:** Affected services (blue nodes)
- **Ring 4:** Affected endpoints (cyan diamonds)
- Each node is positioned radially with risk-based sizing

### Interactive Controls
| Control | Action |
|---------|--------|
| **Mouse wheel** | Zoom in/out (0.3x to 3x) |
| **Click + drag** | Pan the viewport |
| **+ button** | Zoom in by 0.2x |
| **- button** | Zoom out by 0.2x |
| **Reset button** | Return to 1x zoom, centered |
| **Hover node** | Show tooltip with file name, type, risk score |
| **Click node** | Open file in code preview or chat |

### Implementation
- All SVG elements are wrapped in a `<g id="blastZoomGroup">` transform group
- Zoom applies `translate(x,y) scale(z)` CSS transform
- Pan tracks mouse delta during drag events
- Ring labels use `text-anchor: middle` with uppercase styling

---

## 13. Dependency Tree (DAG)

The dependency tree is a directed acyclic graph rendered with **dagre-d3** (Dagre layout + D3.js rendering).

### Node Types

| Type | Color | Shape | Represents |
|------|-------|-------|------------|
| `pr_origin` | Purple (#605DFF) | Rectangle | The PR itself |
| `file_*` | Red (#FEE2E2) | Rectangle | Directly changed files |
| `dep_*` | Yellow (#FEF3C7) | Rounded rect | Downstream dependencies |
| `svc_*` | Blue (#DBEAFE) | Rounded rect | Affected services |
| `ep_*` | Cyan (#CFFAFE) | Diamond | Affected endpoints |

### Edge Types
- **PR → Changed File:** Solid purple line
- **Changed File → Dependency:** Solid yellow line
- **Changed File → Service:** Dashed blue line (heuristic match)
- **Service → Endpoint:** Dashed cyan line

### Interactive Features

| Feature | Description |
|---------|-------------|
| **Hover** | Glow effect on nodes |
| **Click** | Open file in Impact Chat side panel |
| **Right-click** | Context menu: Add Note, View Note, Mark Reviewed, Ask AI, View Code, Open on GitHub |
| **Pan** | d3.zoom() with drag (0.3x to 3x range) |
| **Zoom** | Mouse wheel zoom |
| **Review checkmarks** | Toggle-able checkboxes on each file node (SVG foreignObject) |
| **Note indicators** | Yellow dot on nodes that have attached notes |
| **Path highlighting** | Click a file card in chat to highlight its full dependency path (BFS traversal) |

### Dark Mode
- Node text automatically switches to light color (`#e2e8f0`) via CSS `[data-theme=dark]` override
- Edge paths get reduced opacity for better contrast

### Context Menu Options

| Option | Action |
|--------|--------|
| **Add Note** | Opens a floating note editor popup with textarea |
| **View Note** | Opens existing note for viewing/editing |
| **Mark Reviewed** | Toggles review checkbox, adds semi-transparent style |
| **Ask AI** | Sends "Explain {filename} and its risk" to Impact Chat |
| **View Code** | Opens file in the code preview modal |
| **Open on GitHub** | Opens file in browser on GitHub |

---

## 14. Code Review Session Management

DriftWatch includes a full review session system for tracking your code review progress across files.

### Review Checkmarks
- Each file in the dependency tree has a checkbox (SVG foreignObject)
- Checking a file marks it as "reviewed" — the node becomes semi-transparent with a green dashed border
- Toggle visibility with the review toggle button in the header
- Progress bar updates: "3/12 files reviewed"

### File Notes
- Right-click any file node → "Add Note"
- Opens a floating note editor with:
  - File name header
  - Textarea for notes
  - Save, Delete, Cancel buttons
- Notes persist in localStorage keyed by PR ID
- Files with notes get a small yellow dot indicator on their tree node

### Save/Load Sessions
- **Save** button persists to localStorage:
  - Which files are marked as reviewed
  - All file notes
  - Review progress count
- **Auto-restore** on page reload (by PR ID)
- Visual toast notification on save ("Review session saved (3/12 files reviewed)")

### Export Session
- Downloads a JSON file containing:
  - Reviewed files list
  - All file notes
  - Chat conversation messages
  - PR metadata
  - Export timestamp

### Import Session
- Upload a previously exported JSON file
- Restores:
  - Review checkmarks
  - File notes (with yellow dot indicators)
  - Chat messages
- Useful for handing off a review to a teammate

### Review All (AI)
- Click **Review All** button to have the AI review every file sequentially
- For each file, sends a request to `/api/review-all` with a pre-built query:
  > "Review the file '{filename}' in detail. Risk score: {score}. Change type: {type}. Dependencies: {deps}. Give a brief code review: what looks good, potential issues, and suggestions."
- Results appear as chat messages (one per file)
- **Stop button** — Click again to stop the sequential review at any point
- Button toggles between blue "Review All" and red "Stop"
- Uses `_reviewAllStopped` flag to break the async loop

---

## 15. Text-to-Speech (TTS)

DriftWatch uses Azure Speech Services for accessibility and hands-free review.

### How It Works
1. Speaker icon appears on each bot message in the Impact Chat
2. Click the speaker icon to hear the message read aloud
3. Frontend sends `POST /api/tts` with the message text (max 3000 chars)
4. Backend calls Azure Speech REST API with SSML
5. Returns MP3 audio blob
6. Frontend plays via `new Audio(URL.createObjectURL(blob))`

### Implementation Details
- **Voice:** en-US-JennyNeural (Azure Neural TTS)
- **Format:** audio-16khz-128kbitrate-mono-mp3
- **Auto-detection:** MutationObserver watches `#chatMessages` for new messages and adds TTS buttons
- **Visual feedback:** Speaker icon pulses while audio is playing
- **Error handling:** Silently fails if Azure Speech is unavailable

---

## 16. Authentication & RBAC

### Authentication Flow
1. All `/driftwatch/*` routes require `auth` middleware
2. Unauthenticated users are redirected to `/login`
3. Login page accepts email + password
4. Uses Laravel's `Auth::attempt()` with session regeneration
5. Logout invalidates session and regenerates CSRF token

### Role-Based Access Control

| Role | View Dashboard | View PR Detail | Approve/Block | Edit Settings | Manage Repos |
|------|---------------|----------------|---------------|--------------|--------------|
| **admin** | Yes | Yes | Yes | Yes | Yes |
| **reviewer** | Yes | Yes | Yes | No | No |
| **viewer** | Yes | Yes | No | No | No |

### Demo Accounts

| Name | Email | Password | Role |
|------|-------|----------|------|
| Admin User | admin@driftwatch.dev | password | admin |
| Sarah Chen | sarah@driftwatch.dev | password | reviewer |
| James Wilson | james@driftwatch.dev | password | reviewer |
| Demo Viewer | viewer@driftwatch.dev | password | viewer |

### Login Page Features
- DriftWatch-branded dark theme with gradient background
- Quick Demo Access buttons (click to auto-fill and submit)
- Error display for invalid credentials
- CSRF protection on login form

---

## 17. GitHub Integration

### Webhook Reception
- **Endpoint:** `POST /webhooks/github`
- **Security:** HMAC-SHA256 signature verification using webhook secret
- **Events handled:**
  - `pull_request.opened` — New PR created
  - `pull_request.synchronize` — PR updated with new commits

### GitHub API Usage

| Action | API | When |
|--------|-----|------|
| Fetch PR metadata | `GET /repos/{owner}/{repo}/pulls/{number}` | Manual analysis |
| Fetch PR files | `GET /repos/{owner}/{repo}/pulls/{number}/files` | Pipeline execution |
| Fetch file content | `GET /repos/{owner}/{repo}/contents/{path}` | Code preview |
| Post PR comment | `POST /repos/{owner}/{repo}/issues/{number}/comments` | After Negotiator decision |
| Push code edit | `PUT /repos/{owner}/{repo}/contents/{path}` | Code edit feature |
| Create webhook | `POST /repos/{owner}/{repo}/hooks` | Repository connection |
| Delete webhook | `DELETE /repos/{owner}/{repo}/hooks/{id}` | Repository disconnection |
| Fetch check runs | `GET /repos/{owner}/{repo}/commits/{sha}/check-runs` | CI status in Archaeologist |

### PR Comment
After the pipeline completes, a formatted markdown comment is posted on the GitHub PR containing:
- Risk score with visual emoji indicator
- Blast radius summary
- Contributing factors
- Clear recommendation
- Link to DriftWatch dashboard

### Microsoft Teams Integration
- Sends Adaptive Card to Teams webhook URL
- Card includes: Risk score, decision, affected services
- Action buttons: Approve, Block, View in DriftWatch
- Buttons use HMAC-signed URLs for security

---

## 18. Azure Services Used (18 Total)

| # | Service | Purpose | How It's Used |
|---|---------|---------|---------------|
| 1 | **Azure AI Foundry** | Agent registration, Responses API, RAI guardrails | All LLM calls route through Foundry with DriftWatch-Safety RAI policy |
| 2 | **Azure OpenAI** | GPT-4.1-mini + GPT-4.1 power all agents | System prompts + PR context → structured JSON responses via Model Router |
| 3 | **Semantic Kernel SDK** | Microsoft Agent Framework | Planner → Skills → Memory orchestration with @kernel_function plugins |
| 4 | **Azure Functions V2** | Python serverless agent hosting | 7 HTTP-triggered functions (6 agents + health endpoint) |
| 5 | **Azure MCP Server** | Model Context Protocol tool integration | 10 tools (GitHub read/write + DB queries) for agent tool access |
| 6 | **Azure AI Content Safety** | RAI guardrails on all outputs | Every agent output filtered before storage/posting |
| 7 | **Azure Database for MySQL** | Flexible Server for all data | All models, incidents, agent runs stored with SSL |
| 8 | **Application Insights** | gen_ai.* telemetry via OpenTelemetry | Agent calls, token usage, latency, error rates → Foundry Operate tab |
| 9 | **Azure Monitor** | Alerts, SLAs, diagnostics | Log Analytics workspace for all traces |
| 10 | **Azure Key Vault** | Secrets management | API keys, connection strings, webhook secrets |
| 11 | **Azure Service Bus** | Async agent message queue | Decoupled pipeline communication |
| 12 | **Azure Speech Services** | Text-to-speech | Neural voice (JennyNeural) for chat narration |
| 13 | **Microsoft Teams** | Human-in-the-loop decisions | Adaptive Cards with HMAC-signed approve/block callbacks |
| 14 | **GitHub Copilot** | Automated issue remediation | Negotiator creates Issues → Copilot Agent Mode generates fix PRs |
| 15 | **Azure SRE Agent** | Incident auto-response | Post-deployment health monitoring + automated rollback triggers |
| 16 | **Azure AI Language** | Key phrase extraction + entity recognition | Historian uses for semantic incident matching; Security Agent uses for entity context |
| 17 | **Azure AI Search (RAG)** | Semantic incident retrieval | Historian retrieves semantically relevant past incidents; Chronicler indexes new outcomes |
| 18 | **Azure Blob Storage** | MRP artifact archive | Complete audit trail — every agent's output archived as timestamped JSON |

---

## 19. Mock Fallback Pattern

Every agent call in DriftWatch follows a **try-real-then-mock** pattern:

```php
try {
    // Call real Azure Function endpoint
    $response = Http::timeout(30)
        ->withHeaders(['x-functions-key' => config('services.agents.function_key')])
        ->post(config('services.agents.archaeologist_url'), $payload);

    if ($response->successful()) {
        $result = $response->json();
    } else {
        throw new \Exception('Agent returned ' . $response->status());
    }
} catch (\Exception $e) {
    Log::warning('[DriftWatch] Archaeologist unavailable, using mock.', ['error' => $e->getMessage()]);
    $result = $this->getMockArchaeologistResult($codeContext);
}
```

**Why this exists:**
- Azure Functions have cold starts (5–15 seconds)
- Functions may be unavailable during development
- Demo mode works without any Azure infrastructure
- Mock results are **code-aware** — they parse actual PR diffs and classify files using heuristics

**Mock Quality:**
The mock Archaeologist actually reads the PR diff and:
- Classifies files by risk (CSS = 2pts, auth = 30pts, etc.)
- Builds dependency graphs from import statements
- Detects endpoints from route files
- Infers services from directory structure

This means the demo works realistically even without Azure Functions running.

---

## 20. Configuration Reference

### Environment Variables (`.env`)

| Variable | Purpose | Example |
|----------|---------|---------|
| `APP_URL` | Application URL | `http://localhost:8000` |
| `DB_HOST` | MySQL host | `startup-flexserver-db.mysql.database.azure.com` |
| `DB_DATABASE` | Database name | `driftwatch` |
| `DB_USERNAME` | DB username | `adriantennant` |
| `DB_PASSWORD` | DB password | `admin123#` |
| `SESSION_DRIVER` | Session storage | `database` |
| `AZURE_OPENAI_ENDPOINT` | Azure OpenAI endpoint | `https://eastus.api.cognitive.microsoft.com/` |
| `AZURE_OPENAI_API_KEY` | Azure OpenAI key | (secret) |
| `AZURE_OPENAI_DEPLOYMENT` | Model deployment name | `gpt-4.1-mini` |
| `GITHUB_TOKEN` | GitHub personal access token | `ghp_...` |
| `GITHUB_WEBHOOK_SECRET` | Webhook HMAC secret | (secret) |
| `AGENT_ARCHAEOLOGIST_URL` | Archaeologist endpoint | `https://driftwatch-agents.azurewebsites.net/api/archaeologist` |
| `AGENT_HISTORIAN_URL` | Historian endpoint | (same pattern) |
| `AGENT_NEGOTIATOR_URL` | Negotiator endpoint | (same pattern) |
| `AGENT_CHRONICLER_URL` | Chronicler endpoint | (same pattern) |
| `AZURE_FUNCTION_KEY` | Azure Functions auth key | (secret) |
| `AZURE_SPEECH_ENDPOINT` | Azure Speech endpoint | `https://eastus.api.cognitive.microsoft.com/` |
| `AZURE_SPEECH_KEY` | Azure Speech key | (secret) |
| `AZURE_SPEECH_REGION` | Azure Speech region | `eastus` |
| `TEAMS_WEBHOOK_URL` | Microsoft Teams webhook | (URL) |
| `TEAMS_NOTIFY_ABOVE_SCORE` | Min score for Teams alert | `60` |
| `APPLICATIONINSIGHTS_CONNECTION_STRING` | App Insights connection | (connection string) |

### Config File (`config/services.php`)
All agent URLs, API keys, and service configurations are accessed via `config('services.agents.*')`, `config('services.github.*')`, etc. Environment variables are only used in config files (never directly in code).

---

## 21. Frontend Stack & Conventions

### Template System
- **Admin Template:** Trezo (Bootstrap 5 commercial template)
- **Layout:** All views extend `layouts.app` with `@yield('title')`, `@yield('content')`, `@stack('scripts')`
- **Partials:** `sidebar.blade.php`, `header.blade.php`, `footer.blade.php`, `preloader.blade.php`, `theme_settings.blade.php`

### CSS Conventions
- Bootstrap 5 utility classes throughout
- Custom styles use `<style>` blocks in Blade views
- Dark mode via `[data-theme=dark]` CSS selectors
- Catppuccin color palette for code and dark UI elements
- No build step required — all CSS is pre-built

### JavaScript Libraries
| Library | Version | Purpose |
|---------|---------|---------|
| Bootstrap 5 | 5.x | UI framework |
| ApexCharts | latest | Dashboard charts |
| dagre-d3 | 0.6.4 | Dependency tree DAG rendering |
| D3.js | 7.x | SVG manipulation and zoom |
| vis.js | latest | Network graph visualization |
| Clipboard.js | latest | Copy to clipboard |
| SimpleBar | latest | Custom scrollbars |

### Icon System
- **Google Material Symbols** (Outlined variant)
- Usage: `<span class="material-symbols-outlined">icon_name</span>`
- Common icons: `dashboard`, `merge_type`, `warning`, `analytics`, `settings`, `smart_toy`, `target`

### Color Scheme
| Element | Light | Dark |
|---------|-------|------|
| Background | `#f8fafc` | `#0f172a` |
| Card background | `#ffffff` | `#1e293b` |
| Text primary | `#1a1a2e` | `#cdd6f4` |
| Text secondary | `#6c7086` | `#a6adc8` |
| Brand purple | `#605DFF` | `#605DFF` |
| Danger red | `#EF4444` | `#f38ba8` |
| Warning yellow | `#F59E0B` | `#fab387` |
| Success green | `#10B981` | `#a6e3a1` |
| Info blue | `#3B82F6` | `#89b4fa` |

---

## 22. File Structure

```
DriftWatch/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── DriftWatchController.php    # All dashboard pages (26 methods)
│   │       └── GitHubWebhookController.php # Webhook + agent pipeline (2258 lines)
│   └── Models/
│       ├── PullRequest.php                 # Core entity with relationships
│       ├── BlastRadiusResult.php           # Archaeologist output
│       ├── RiskAssessment.php              # Historian output
│       ├── DeploymentDecision.php          # Negotiator output
│       ├── DeploymentOutcome.php           # Chronicler output
│       ├── Incident.php                    # Historical incidents
│       ├── AgentRun.php                    # Agent observability traces
│       ├── Repository.php                  # Connected GitHub repos
│       ├── PipelineConfig.php              # Custom pipeline rules
│       └── User.php                        # Auth with RBAC roles
│
├── agents/
│   └── function_app.py                     # Python Azure Functions (all 4 agents)
│
├── config/
│   └── services.php                        # Agent URLs, API keys, service config
│
├── database/
│   ├── migrations/                         # 15+ migrations
│   └── seeders/
│       ├── DatabaseSeeder.php              # Calls UserSeeder + DemoDataSeeder
│       ├── UserSeeder.php                  # 4 demo accounts
│       └── DemoDataSeeder.php              # 14 incidents + 5 demo PRs
│
├── resources/views/
│   ├── layouts/
│   │   └── app.blade.php                   # Main layout
│   ├── partials/
│   │   ├── sidebar.blade.php               # Navigation menu
│   │   ├── header.blade.php                # Top bar (search, notifications, profile)
│   │   ├── footer.blade.php                # Footer
│   │   ├── preloader.blade.php             # Loading spinner
│   │   ├── theme_settings.blade.php        # Customization panel (dark mode, sidebar)
│   │   └── styles.blade.php                # CSS includes
│   ├── driftwatch/
│   │   ├── index.blade.php                 # Dashboard
│   │   ├── show.blade.php                  # PR detail (6000+ lines)
│   │   ├── pull-requests.blade.php         # PR list
│   │   ├── incidents.blade.php             # Incident history
│   │   ├── analytics.blade.php             # Analytics dashboard
│   │   ├── settings.blade.php              # Pipeline settings
│   │   ├── agent-map.blade.php             # Agent pipeline visualization
│   │   ├── governance.blade.php            # Responsible AI page
│   │   ├── explainability.blade.php        # How scoring works
│   │   ├── repositories.blade.php          # Repository management
│   │   ├── repositories/show.blade.php     # Single repo detail
│   │   └── agents/show.blade.php           # Individual agent page
│   └── login.blade.php                     # Login page
│
├── routes/
│   ├── web.php                             # Web routes (auth + dashboard)
│   └── api.php                             # API routes (chat, preview, TTS, etc.)
│
├── docs/
│   ├── APPLICATION_GUIDE.md                # This file
│   ├── ARCHITECTURE.md                     # Mermaid diagrams
│   ├── TEAM_SETUP.md                       # Setup instructions
│   ├── DEMO_SCRIPTS.md                     # Hackathon demo scripts
│   └── AZURE_SETUP_FOR_FRIEND.md           # Azure infrastructure setup
│
└── .env                                    # Environment configuration
```

---

*This document covers every feature, agent, API endpoint, database model, UI component, and integration in the DriftWatch application.*
