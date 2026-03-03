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
