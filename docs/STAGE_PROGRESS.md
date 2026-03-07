# DriftWatch — Stage Progress

## Stage 0: Reconnaissance — COMPLETE
- Identified Trezo admin template (Bootstrap 5, Material Symbols, ApexCharts)
- No Blade layouts existed — all views used @include partials
- Laravel 11.x with PHP 8.3+, no auth scaffolding

## Stage 1: Database & Models — COMPLETE
- 6 migrations created: pull_requests, blast_radius_results, risk_assessments, deployment_decisions, deployment_outcomes, incidents
- 6 Eloquent models with relationships, fillable, casts, and accessors
- DemoDataSeeder: 14 historical incidents + 5 demo PRs with full pipeline data
- Payment-service narrative established (3 P1 incidents)

## Stage 2: Webhook & Queue Pipeline — COMPLETE
- GitHubWebhookController with signature verification
- 3-agent pipeline (Archaeologist → Historian → Negotiator) runs synchronously
- Mock fallback data when Azure Functions unavailable
- API endpoint for incidents (used by Historian agent)
- config/services.php updated with GitHub + agent URLs

## Stage 3: Dashboard Pages — COMPLETE
- Blade layout system (layouts/app.blade.php)
- Rebranded from Trezo to DriftWatch (preloader, footer, sidebar, logo)
- Dashboard index with 4 stat cards + PR table
- PR detail page with blast radius, risk assessment, deployment decision, timeline
- Pull requests list with search/filter
- Incidents list page
- Analytics page with risk distribution, risky services, accuracy
- Agent status pages (Archaeologist, Historian, Negotiator, Chronicler)
- Settings page with config status

## Stage 4: Python Agents — COMPLETE
- All 4 agents in agents/function_app.py (single Azure Functions V2 app)
- Agent 1 (Archaeologist): Fetches PR diff from GitHub, analyzes with Azure OpenAI, returns blast radius
- Agent 2 (Historian): Fetches incidents from Laravel API, correlates with blast radius, produces risk score
- Agent 3 (Negotiator): Makes deploy decision, posts markdown comment to GitHub PR
- Agent 4 (Chronicler): Records post-deploy outcome, evaluates prediction accuracy
- Health check endpoint at /api/health
- All agents use structured JSON output (response_format: json_object)
- Friend's Azure setup guide: docs/AZURE_SETUP_FOR_FRIEND.md

## Stage 5: Azure Deployment — PENDING (friend has subscription)
## Stage 6: Polish & Submission — TODO

---

## IMPROVEMENT ROADMAP STAGES (v4 Implementation)

### Improvement Stage 1: Fix Agent Scoring — COMPLETE (2026-03-06)

**1A — Archaeologist: Diff Reading + Change Classification**
- Rewrote ARCHAEOLOGIST_SYSTEM prompt with full scoring rubric (2-30 points per change type)
- Added 6-step analysis: fetch diff → classify changes → read high-risk full files → follow imports → check CI status → check bot findings
- Returns `change_classifications`, `total_blast_radius_score`, `ci_status`, `failing_checks`, `bot_findings`
- Returns `insufficient_data` when no diff available — prevents false "Clear Skies"
- Fetches full file content for migrations, middleware, auth, config, base classes
- CI check: queries GitHub Checks API, adds 25pts for failing required checks
- Bot findings: scans PR comments for Aikido, Snyk, CodeQL, Dependabot, Semgrep etc.

**1B — Historian: Multi-Layer Matching**
- Rewrote HISTORIAN_SYSTEM with 3-layer matching: file (25pts), service (10pts), change-type (15pts)
- History score capped at 40 points
- Returns `insufficient_data` with honest message when no incidents in DB
- Receives enriched data from Archaeologist: blast_radius_score, change_classifications, CI/bot data
- Returns `match_summary` for transparent scoring breakdown

**1C — Laravel: AgentRun Tracking**
- Migration: `add_change_type_to_incidents_table` — nullable string column
- Migration: `create_agent_runs_table` — timing, cost, tokens, score contribution, full I/O payloads
- Model: `AgentRun` with casts, relationships, status/display name accessors
- PullRequest model: added `agentRuns()` HasMany relationship
- GitHubWebhookController: records AgentRun after each agent call with timing
- Pipeline passes enriched data between agents (blast_radius_score, change_classifications, CI/bot data)
- Mock data updated with realistic change_classifications and scoring
- Debug panel added to show.blade.php (visible with `?debug=1`)

### Improvement Stage 2: Fix the UI — COMPLETE (2026-03-06)

**2A — Deploy Decision Banner**
- Full-width deploy decision banner at top of page content
- Green SAFE TO DEPLOY / Red DEPLOYMENT BLOCKED / Orange AWAITING HUMAN DECISION
- 60px min height, icons, shows decided-by when available

**2B — Dominant Risk Score Circle**
- 140x140px circle with 48px font score, thick colored ring
- Color rules: green ≤20, yellow ≤40, orange ≤60, red ≤80, dark red with CSS pulse ≥81

**2C — Section Reorder + Time Bomb Promotion**
- Time Bomb Detection moved from bottom to right after Weather Forecast
- Styled as amber/yellow gradient card with timer icon and "flagged" badge
- New page order: Deploy Banner → Score + Weather → Time Bomb → Summary → Impact Analysis

**2D — Expandable Score Factor Reasoning**
- Score Composition bars converted to expandable accordion rows
- Each row shows confidence badge (High/Medium/Low) and point contribution
- Expanded: agent reasoning from AgentRuns, "what raises this score" hint, GitHub diff link

### Improvement Stage 3: Blast Radius Graph — COMPLETE (2026-03-06)

- Replaced force-directed vis-network with dagre-d3 hierarchical DAG
- LR (left-to-right) layout: PR origin → Changed files → Downstream deps → Services → Endpoints
- Orphan node fix: disconnected nodes attached to PR origin with dashed edges
- "Dependency Tree" tab is now default; "Blast Map" kept as secondary
- Click tooltips with node details, zoom/pan with d3.zoom
- Dark mode CSS for dependency tree

### Improvement Stage 3.5: Rich Tooltips + Review Checklist — COMPLETE (2026-03-06)

**Rich Hover Tooltips on Dependency Tree**
- AI-generated file summaries shown on node click (from file_descriptions)
- Risk explanations from change classifications and AI analysis
- "Impact on other files" section explaining downstream cascade
- Downstream dependents listed with their own AI summaries
- Change type badge (e.g. "changed function signature", "sql migration query")
- Clickable links: "View diff" (opens GitHub PR diff), "View source" (opens file on GitHub)
- Risk score badge with color coding (red 25+, orange 15+, yellow 5+, green <5)

**Edge Hover Tooltips**
- Hovering over relationship edges shows explanation of connection
- "This PR directly changes X", "Y imports from X — breaking changes cascade here"
- Service/endpoint relationship descriptions
- Wider edge hit areas for easier hovering

**"What to Review" Checklist**
- New prioritized review section before Action Items
- Files sorted by risk score (highest first)
- Each file shows: AI summary, role, risk reasoning, downstream impact
- Priority legend with color-coded dots (Critical/High/Medium/Low)
- Downstream dependents as hover-able tags with summaries
- Direct links to GitHub diff and source file for each item

### Improvement Stage 4: CI/CD Pipeline Integration — COMPLETE (2026-03-06)

**GitHub Action (action.yml)**
- `driftwatch-analyze@v1` action with Node.js 20 runtime
- Two modes: `full` (calls DriftWatch backend) and `lightweight` (in-runner with Azure OpenAI)
- Inputs: driftwatch-url, api-token, risk-threshold, block-on-critical, azure-openai-*
- Outputs: risk-score, risk-level, decision, summary

**Full Mode**
- POSTs to `/api/analyze`, polls `/api/jobs/{id}/status` every 10s, max 5 min
- Fails GitHub check when risk exceeds threshold

**Lightweight Mode (Zero Infrastructure)**
- Fetches PR diff via GitHub API, classifies each file with risk scoring rubric
- Reads high-risk file contents via GitHub Contents API
- Calls Azure OpenAI for AI-powered analysis
- Creates native GitHub Check with risk results (green/red check on PR)

**API Endpoints**
- `POST /api/analyze` — Trigger analysis, returns job_id + immediate results
- `GET /api/jobs/{id}/status` — Poll job completion status
- `GET /api/decisions/{id}/approve` — Teams callback to approve deployment
- `GET /api/decisions/{id}/block` — Teams callback to block deployment
- Decision callbacks use HMAC token verification for security

### Improvement Stage 5: Briefing Pack + Merge-Readiness Pack — COMPLETE (2026-03-06)

**5A — Briefing Pack**
- Structured JSON input generated before agent pipeline starts
- Contains: briefing_id, PR metadata, timestamps
- Each agent enriches the briefing pack with its findings
- Enriched briefing stored as `input_payload` in AgentRun records
- Creates complete provenance trail across all agents

**5B — Merge-Readiness Pack (MRP)**
- Formal MRP generated after Negotiator agent runs
- Contains: mrp_id, version, decision, risk score, evidence breakdown, conditions, audit trail
- Evidence sections: blast_radius (score + summary + services), incident_history, CI status, bot findings
- Version incrementing on re-analysis (never overwrites, tracks evolution)
- Audit trail records generation events and human decisions
- Migration: `add_mrp_payload_to_deployment_decisions_table` (mrp_payload JSON, mrp_version int, weather_score int)
- DeploymentDecision model updated with mrp_payload (array cast), mrp_version, weather_score

**MRP View on PR Detail Page**
- New "Merge-Readiness Pack" card between AI Recommendation and Impact Analysis
- Shows MRP ID badge and version indicator
- Three evidence cards: Blast Radius, Incident History, CI & Bots
- Conditions for approval list with checkmarks
- Audit trail footer showing last action
