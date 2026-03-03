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

## Stage 4: Python Agents — TODO
## Stage 5: Azure Deployment — TODO
## Stage 6: Polish & Submission — TODO
