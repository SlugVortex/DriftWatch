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

### Pre-Stage 6: DevOps Research Features — COMPLETE (2026-03-07)

Implemented features from developer forum research on CI/CD pain points. Five feature sets added before starting Stage 6.

**Pipeline Orchestration & Configuration**
- New `pipeline_configs` table and `PipelineConfig` model
- 3 built-in templates: Full Analysis, Quick Scan, Gated Deployment
- Per-agent enable/disable toggles (Archaeologist, Historian, Negotiator, Chronicler)
- Conditional pipeline rules: path-based matching (`fnmatch`), file count thresholds
- Rule actions: `skip_all` (all files must match) and `force_gate` (any match triggers)
- High-traffic window detection (day/hour ranges)
- Settings page rewritten with pipeline orchestration UI (template cards, agent toggles, threshold config)

**Manual Approval Gates**
- Pipeline pause/resume mechanism with state preservation
- `pipeline_paused`, `paused_at_stage`, `paused_at`, `paused_reason` fields on PullRequest
- `shouldPausePipeline()` evaluates config gates, conditional rules, and environment thresholds
- `resumePipeline()` continues from paused stage (Negotiator onwards)
- Approval gate banner on PR detail page with Resume/Block buttons and pause reason

**Stacked PR Detection**
- `detectStackedPRs()` finds parent, child, and sibling PRs
- Three strategies: branch parent/child relationships and file overlap detection
- Combined blast radius score computed across stacked PRs
- `stacked_pr_ids` and `combined_blast_radius_score` added to DeploymentDecision
- Stacked PR section on PR detail page with relationship cards and risk scores

**Pipeline Artifacts & Timeline**
- Agent I/O payloads viewable as collapsible inspector panels
- Pipeline timeline with duration bar showing relative agent time
- Drill-down to full agent run details: reasoning, input/output JSON, attempt count, cost, hash

**Environment-Aware Risk Scoring**
- `target_environment` and `pipeline_template` fields on PullRequest
- Per-environment risk thresholds (production/staging/development)
- Per-environment approval requirements
- Environment overrides agent decisions (e.g., approved → pending_review if score exceeds threshold)
- Environment and template selector dropdowns on PR detail page

**Pipeline Retry Logic**
- `runWithRetry()` wrapper for agent function calls
- Configurable `max_retries_per_agent` and `retry_on_timeout` per template
- 500ms delay between retry attempts

**UI Glow Up**
- New CSS classes: `dw-card`, `dw-section-title`, `pipeline-step`, `dw-banner`, `dw-stat`, `dw-bomb-card`
- Hover effects, shadows, accent underlines, dark mode support
- Risk score circle with color-matched glow
- Modal with gradient header for dependency tree node details (replaced floating tooltip)
- DAG nodes with purple hover glow

**Routes Added**
- `POST /pr/{pullRequest}/resume-pipeline`
- `POST /pr/{pullRequest}/update-environment`
- `POST /pr/{pullRequest}/update-template`
- `POST /settings/pipeline`
- `POST /settings/pipeline/reset`

**Files Created/Modified**
- `database/migrations/2026_03_07_083058_create_pipeline_configs_table.php` (new)
- `database/migrations/2026_03_07_083110_add_pipeline_fields_to_pull_requests_table.php` (new)
- `app/Models/PipelineConfig.php` (new)
- `app/Models/PullRequest.php` (updated: new fields, casts method)
- `app/Models/DeploymentDecision.php` (updated: stacked PR fields)
- `app/Http/Controllers/GitHubWebhookController.php` (major rewrite: pipeline orchestration)
- `app/Http/Controllers/DriftWatchController.php` (updated: settings, resume, environment, template)
- `resources/views/driftwatch/show.blade.php` (updated: gate banner, controls, stacked PRs, timeline, CSS)
- `resources/views/driftwatch/settings.blade.php` (complete rewrite: pipeline orchestration UI)
- `routes/web.php` (updated: 5 new routes)

### Improvement Stage 6: Deployment Weather — COMPLETE (2026-03-07)

**Goal:** Score the environmental risk at deploy time, separate from code risk.

**5 Environmental Checks (max 95 pts combined):**
1. **Concurrent Deploys (+20 pts)** — Checks for other PRs approved in the last 30 min with overlapping services, plus GitHub Actions workflows currently in progress
2. **Active Incidents (+30 pts)** — Queries incidents with `resolved_at IS NULL` and checks for service overlap with the blast radius
3. **Infrastructure Health (+20 pts)** — Tries Azure Application Insights for real error rates; falls back to checking recently-resolved incidents in the last 24h as a stability proxy
4. **High Traffic Window (+15 pts)** — Checks current time against pipeline config's `high_traffic_windows` (day/hour ranges)
5. **Recent Related Deploy (+10 pts)** — Finds PRs merged/approved in the last 2 hours touching the same services (stacked deploy correlation)

**Weather Score Interpretation:**
- 0-10: Clear Skies — good to deploy
- 11-30: Partly Cloudy — acceptable, proceed with monitoring
- 31-50: Storm Warning — conditions unfavorable, consider waiting
- 51+: Severe Storm — strongly recommend delaying

**Decision Escalation:**
- Weather score >= 40 with approved decision → auto-escalates to `pending_review`

**UI — Deployment Weather Card:**
- New card between Risk Score section and Time Bomb Detection on PR detail page
- Weather score circle with color-matched glow (100px ring)
- 5 individual check cards in a 2-column grid showing fired/clear status, points, and detail text
- Summary bar showing clear count, flagged count, and check timestamp

**Files Created/Modified:**
- `database/migrations/2026_03_07_091028_add_weather_checks_to_deployment_decisions_table.php` (new)
- `app/Models/DeploymentDecision.php` (updated: weather_checks fillable + array cast)
- `app/Http/Controllers/GitHubWebhookController.php` (updated: computeWeatherScore + 5 check methods, integrated into Negotiator stage)
- `resources/views/driftwatch/show.blade.php` (updated: Deployment Weather card)

### Pre-Stage 7: Deep Code Analysis Pipeline — COMPLETE (2026-03-08)

**Code Context Fetching**
- New `fetchPrCodeContext()` method in GitHubWebhookController
- Fetches full unified PR diff from GitHub API (truncated at 200KB)
- Fetches changed file list with patches (up to 100 files)
- Skips binary, vendor, lock, and minified files automatically
- Fetches full file contents for high-risk files via GitHub Contents API (base64 decoded)
- High-risk patterns: migrations, middleware, auth, config, routes, controllers, models, services
- Limits to 30 full file fetches, 50KB per file max
- Passes `diff_text` and `changed_files` (with `full_file_content`) to Archaeologist agent

**Code-Aware Mock Analysis**
- When Azure Functions unavailable, mock Archaeologist performs real code classification
- Classifies files by path pattern: migration (25pts), auth/middleware (30pts), config (20pts), routing (15pts), controller (15pts), model (15pts), service (20pts), view/css (2pts), test (2pts)
- Detects function signature changes from diff patches
- Extracts class names, method counts, imports from full file content
- Builds dependency graph from PHP `use` statements and Python/JS imports
- Infers service names from file paths
- Generates `file_summaries` and `code_analysis` fields for UI display
- Mock Negotiator generates code-aware PR comments with markdown formatting

**Files Modified:**
- `app/Http/Controllers/GitHubWebhookController.php` (new: fetchPrCodeContext, updated: runArchaeologist passes code context, getMockArchaeologistResult with code classification, getMockNegotiatorResult with PR comment)

### Improvement Stage 7: Teams Notification + Human Decision Loop — COMPLETE (2026-03-08)

**Teams Adaptive Card Notifications**
- New `sendTeamsNotification()` method in GitHubWebhookController
- Sends Adaptive Card to Microsoft Teams incoming webhook when risk score exceeds threshold
- Configurable: `TEAMS_WEBHOOK_URL` and `TEAMS_NOTIFY_ABOVE_SCORE` (default: 60) in `.env`
- Card displays: PR number/title, risk score (color-coded), decision status, weather score, affected services, files changed, blast radius score, key concerns
- Three Action.OpenUrl buttons: APPROVE, BLOCK, View in DriftWatch
- Approve/Block buttons use HMAC-signed tokens for security

**Human Decision Loop**
- Decision callback endpoints updated to append to MRP audit trail
- Audit trail records: action (approved/blocked), by (who decided), at (timestamp), details
- Approve callback auto-resumes paused pipelines (calls resumePipeline)
- Decision callbacks accept optional `?approver=` query parameter for tracking

**Settings Page Updates**
- New "Microsoft Teams Notifications" card showing webhook status and notification threshold
- New "Code Analysis" card showing PR code fetching mode and analysis mode

**Config Updates**
- `config/services.php`: Added `teams.webhook_url` and `teams.notify_above_score`

**Files Modified:**
- `app/Http/Controllers/GitHubWebhookController.php` (new: sendTeamsNotification, integrated into Negotiator stage)
- `routes/api.php` (updated: decision callbacks with MRP audit trail, pipeline resume)
- `config/services.php` (new: teams config)
- `resources/views/driftwatch/settings.blade.php` (new: Teams + Code Analysis cards)

### Improvement Stage 8: Interactive PR Detail & Multi-Service Integration — COMPLETE (2026-03-08)

**8A — Full-Screen Code Preview Modal**
- Catppuccin dark theme code viewer with syntax line numbers
- Code highlighting: select text and it turns orange (#F97316) for visual emphasis
- Highlighted code can be attached to chat as a `.chat-code-attachment` block
- Multi-file panel: `.cpm-file-panel` with file list for switching between files
- Edit mode toggle: `contentEditable='true'` with orange outline + Save Draft button
- Dockable/minimizable mode: shifts to `right:0; width:50vw; max-width:700px` for side-by-side chat
- Collapsible "What to Review" section with Bootstrap collapse + icon rotation

**8B — Chat Sidebar Enhancements**
- Widened from 380px to 480px with drag-to-resize handle
- Review tooltip repositioned from off-screen `right: -320px` to below-item `top: 100%`
- Width slider removed (user feedback)
- Section-based scroll navigation arrows jump between `.dw-card` elements

**8C — Azure Speech Text-to-Speech Integration**
- `POST /api/tts` endpoint: Azure Speech TTS proxy with SSML (`en-US-JennyNeural` voice)
- Auto-adds speaker icons to all `.dw-section-title` elements via `initTtsButtons()`
- `speakText()` function: calls API, plays audio blob, manages play/pause state
- `.dw-tts-btn` with pulse animation when playing
- Truncates text to 3000 chars, strips HTML tags
- Config: `services.azure_speech` (endpoint, key, region)

**8D — Animated Blast Map Visualization**
- Replaced vis.js physics-based network graph with SVG concentric radius visualization
- Dark navy (#0F172A) background with animated expanding pulse rings
- 4 concentric zones: Changed (red, r=90) → Affected (amber, r=170) → Services (blue, r=250) → Endpoints (cyan, r=310)
- Radial gradient glow fills with pulsing animations per ring
- Nodes appear with staggered `blastNodeAppear` animation
- Expanding pulse rings (`blastRingExpand`) radiate from center every 1.33s
- Hover: dims other nodes, shows floating dark-themed info card
- Click: shows persistent detail panel below graph
- Stats overlay (top-left) and blast radius score (top-right)

**8E — Security Agent Infrastructure (Env + Config)**
- `.env`: Added `AGENT_SECURITY_URL`, `AZURE_AI_SEARCH_ENDPOINT`, `AZURE_AI_SEARCH_KEY`, `AZURE_AI_SEARCH_INDEX`
- `config/services.php`: Added `agents.security_url`, `azure_ai_search` (endpoint, key, index), `azure_ai_foundry`, `service_bus`, `content_safety`, `key_vault`, `semantic_kernel`
- Setup guide: `docs/SECURITY_AGENT_SETUP.md` (Azure AI Search index schema, RAG architecture, deployment steps)

**8F — Architecture Diagram Update**
- Updated Mermaid diagram on Settings page: 10 → 14 Azure services
- Added: Security Agent (purple), Azure AI Search + Knowledge Base (RAG), MS Teams (bidirectional), Azure Speech TTS, AI Foundry, Service Bus
- Teams shows bidirectional flow: Adaptive Card out, Approve/Block callbacks in

**8G — Auto-Analyze Repositories**
- `auto_analyze` boolean on Repository model (migration + fillable + cast)
- `toggleAutoAnalyze()` and `analyzeAllPrs()` methods in DriftWatchController
- Auto-analyze toggle and Analyze button on repository cards
- Routes: `POST /repositories/{repository}/toggle-auto-analyze`, `POST /repositories/{repository}/analyze-all`

**8H — Chronicler Agent Integration**
- Added `runChronicler()` method with Azure Function + mock fallback
- Integrated as Agent 4 in main pipeline and `resumePipeline()` flow
- Records `agent_chronicler` in `recordAgentRun()` reasoning extraction

**Files Created:**
- `docs/DRIFTWATCH_ICON_DESIGNS.md` (5 AI image generator prompts with brand hex codes)
- `docs/SECURITY_AGENT_SETUP.md` (Azure AI Search + Security Agent deployment guide)
- `database/migrations/2026_03_09_021737_add_auto_analyze_to_repositories_table.php`

**Files Modified:**
- `resources/views/driftwatch/show.blade.php` (major: full-screen modal, chat, TTS, blast map, edit mode)
- `resources/views/driftwatch/settings.blade.php` (architecture diagram, Azure Speech + AI Search cards, Security Agent endpoint)
- `app/Http/Controllers/DriftWatchController.php` (auto-analyze, toggle, sync)
- `app/Http/Controllers/GitHubWebhookController.php` (Chronicler agent, parse error fix)
- `app/Models/Repository.php` (auto_analyze field)
- `routes/api.php` (TTS endpoint)
- `routes/web.php` (auto-analyze routes)
- `config/services.php` (azure_speech, azure_ai_search, content_safety, key_vault, semantic_kernel, azure_ai_foundry, service_bus)
- `.env` (TTS keys, Security Agent placeholders, AI Search placeholders)

### Stage 9: Security Agent Implementation — PENDING
- Deploy Security Agent Azure Function with RAG pipeline
- Populate Azure AI Search with OWASP/CVE/CWE knowledge base
- Integrate into Laravel pipeline (6th agent after Chronicler)
- Add security findings to PR detail page UI

### Stage 10: Final Polish & Submission — TODO
- End-to-end testing with live Azure Functions
- Performance optimization
- Demo video recording
- Hackathon submission
