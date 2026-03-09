# DriftWatch — Staged Implementation Guide
### Microsoft AI Dev Days Hackathon | Coding Agent Execution Plan
*v4 — meticulous stage-by-stage instructions for a coding agent*

---

## RULES FOR THE CODING AGENT — READ FIRST, EVERY SESSION

1. Every stage starts with a RECON block. Read every file listed before writing a single line of code. If a file path does not exist, find the correct path by grepping or listing the directory — never guess.
2. Complete one stage fully and verify it works before starting the next. Do not combine stages.
3. Do not modify files outside the scope listed for a stage. If you notice something broken elsewhere, note it in `docs/STAGE_PROGRESS.md` and keep going.
4. Before touching any Laravel view, check the layout file and identify the CSS framework in use. Match all new markup to existing patterns — never introduce a new frontend framework.
5. Commit after every stage using the exact commit message shown at the bottom of that stage.
6. Update `docs/STAGE_PROGRESS.md` after every stage.
7. Never hallucinate a class name, method name, or file path. If you are not sure, read the file.

---

## SESSION START — DO THIS EVERY TIME BEFORE ANY STAGE

Run the following and read the output before doing anything else. This tells you the real structure of the project so nothing below is assumed:

```bash
ls -la
cat composer.json | grep -E "name|require|laravel"
ls -la agents/
ls -la app/Models/
ls -la app/Jobs/
ls -la app/Http/Controllers/
ls -la resources/views/driftwatch/
cat routes/web.php
cat routes/api.php
grep -r "agent" app/Services/ --include="*.php" -l 2>/dev/null
grep -r "RunAgent\|AgentPipeline\|dispatch" app/ --include="*.php" -l
```

Write a one-paragraph summary of what you found in `docs/STAGE_PROGRESS.md`. Then begin the stage you are working on.

---

## STAGE 1 — Fix Agent Scoring
**Goal:** Make agents produce real differentiated scores. Right now everything returns "Clear Skies" because agents are reading file names, not code.
**Scope:** Python agent files in `/agents/` only. Do not touch PHP/Laravel this stage.
**Estimated time:** 3–4 hours

---

### 1A — Archaeologist: Read the Diff, Not Just File Names

**Recon first:**
```bash
cat agents/archaeologist/function_app.py
# Find which GitHub MCP tools are currently being called
grep -r "mcp\|get_pull\|list_files\|get_diff\|get_file" agents/archaeologist/ --include="*.py"
# See what tools the MCP server exposes
cat agents/mcp_server.py 2>/dev/null || find agents/ -name "mcp*.py" | xargs cat
```

The Archaeologist's job is to take a PR and understand what the code actually does — not just what files changed. Currently it is probably listing file names and returning metadata. That needs to change completely.

The agent must now work in five steps in sequence. Step one: call the GitHub MCP tool that returns the actual diff content. Look for a tool named something like `get_pull_request_diff`, `get_diff`, or `compare_commits`. If no diff tool exists, call `get_pull_request_files` to get the list of changed files, then call `get_file_contents` for each file to read the actual content.

Step two: for each changed file, classify the change by reading the diff and assigning a type from this scoring table. Assign these scores to a variable `change_score` and sum them for the final blast radius score:

- CSS or view-only change (no logic, only `.blade.php` layout or `.css`): 2 points
- New standalone function added, not called by existing code: 5 points
- New API endpoint or new controller action: 15 points
- Existing function signature modified (parameters changed, return type changed): 20 points
- Anything in `middleware/`, `auth/`, token handling, session handling, guards: 30 points
- Any SQL query added or modified, Eloquent query builder changes, any migration file: 25 points
- Config file changed (`.env`, `config/`, any `.json` config): 20 points
- Shared utility or base class that is imported by 3 or more other files: 25 points

Step three: for any file that scores 20 or above, call `get_file_contents` to read the full file (not just the diff). This gives context about whether the change is isolated or connects to many other parts of the system.

Step four: build the blast radius by following imports. For PHP files look for `use`, `require`, and `include` statements. For JS files look for `import` and `require`. Every file that imports the changed file is in the blast radius. Add those to `affected_files`.

Step five: return this exact JSON structure — the Historian and Negotiator agents depend on these exact field names:

```json
{
  "status": "scored",
  "affected_files": [],
  "affected_services": [],
  "affected_endpoints": [],
  "dependency_graph": {},
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
  "summary": "plain English paragraph explaining what the PR changes and why it is risky or safe"
}
```

The agent must also handle failure states. If the diff is empty or the PR has no changed files return `"status": "insufficient_data"`. If an exception occurs return `"status": "error"` with an `"error_message"` field. Never return a fake low score when data is missing — that is what is causing the "Clear Skies" problem.

Add a 30-second timeout to the Azure Function. If it times out return `"status": "error", "error_message": "timeout after 30s"`.

**Step 6 — Check CI status and consume other bot findings (critical — proven missing by real-world test).**

A real n8n PR (n8n-io/n8n#26562) was analyzed by DriftWatch and returned "Clear Skies" score 10/100 while the PR had 3 failing CI checks including Backend Unit Tests. This is a fundamental credibility failure. The Archaeologist MUST check two additional data sources:

First, call the GitHub Checks API endpoint `GET /repos/{owner}/{repo}/commits/{head_sha}/check-runs` to get all CI check results. If any check with `conclusion: failure` exists AND that check is a required check (name contains "Unit Tests", "Required Checks", "Backend", or "Integration"), add 25 points to the blast radius score automatically and add a `ci_failures` field to the output listing the failing check names.

Second, call `GET /repos/{owner}/{repo}/pulls/{pr_number}/reviews` and `GET /repos/{owner}/{repo}/issues/{pr_number}/comments` to find existing bot findings. Look for comments from known security bots: Aikido, Snyk, CodeQL, Dependabot, Semgrep. If any security bot has posted a finding, extract the severity and add it to the score: critical finding = +30 pts, high = +20 pts, medium = +10 pts. Add a `bot_findings` array to the output with each finding summarized. This turns DriftWatch into a synthesis layer over all other bots rather than yet another bot adding noise — that is a compelling story for judges and a real pain point for engineers managing PRs with 5+ bots commenting.

Add these fields to the output JSON:
```json
{
  "ci_status": "failing",
  "failing_checks": ["Backend Unit Tests", "Unit Tests", "Required Checks"],
  "ci_risk_addition": 25,
  "bot_findings": [
    {
      "bot": "aikido-pr-checks",
      "severity": "medium",
      "summary": "resetDataProxies sets timezone conditionally — later calls without timezone keep previous value",
      "risk_addition": 10
    }
  ]
}
```

---

### 1D — Team Pattern Learning (Competitive Neutralizer)

This absorbs the "PR quality gate that learns your team's coding standards" competitor idea. Create a `team_patterns` table via migration: `id`, `repo_full_name`, `pattern_type` (string), `pattern_description` (text), `example_pr_ids` (JSON), `risk_weight` (integer), `confidence` (decimal), `created_at`, `updated_at`.

Create `app/Jobs/LearnTeamPatterns.php`. This job runs weekly (schedule it in `app/Console/Kernel.php`) and queries the last 90 days of merged PRs from the GitHub API for each repository in the `repositories` table. It looks for three categories of pattern: files that always appear together in the same PR (co-change patterns), change types that always get extra review comments before merging (quality friction patterns), and file paths that correlate with incidents post-deploy (danger zone patterns).

Pass these patterns as additional context in the Briefing Pack (Stage 5A) so the Archaeologist can factor them in. Example: if `ChatifyMessenger.php` and `config/chatify.php` always change together but this PR only changes one of them, the Archaeologist flags it — "this file usually changes with its config file, which is not included in this PR."

This does not require LLM fine-tuning. It is simple statistical analysis of your own PR history that produces structured patterns the Archaeologist uses as prompting context. The learning happens in PHP, the reasoning happens in the agent. Add a "Team Patterns" section to the Configuration page showing what patterns have been learned, when they were last updated, and how many PRs contributed to each pattern.

---

### 1B — Historian: Real Data, No Seeds

**Recon first:**
```bash
cat agents/historian/function_app.py
grep -r "incident\|SELECT\|query\|DB_" agents/historian/ --include="*.py"
# Check what incident data actually exists in the DB right now
php artisan tinker --execute="echo App\Models\Incident::count();"
php artisan tinker --execute="print_r(App\Models\Incident::first()->toArray());"
```

The Historian currently does exact file name matching against the incidents table. If your demo repo has no incidents seeded against its actual files, the Historian returns zero points every time.

Do not use fake seeded data. Instead, rewrite the Historian to do three layers of matching. Layer one is the existing exact file match — keep it. Layer two is service matching: check if any service name in `affected_services` from the Archaeologist output matches the `affected_services` field of any incident. Layer three is change-type matching: check if any `change_type` from the Archaeologist's `change_classifications` matches a `change_type` field in past incidents.

To make layer three work you need to add a `change_type` column to the incidents table. Do this in the Laravel scope (Stage 1C below covers the migration). For now, add the column to your query logic so it works when the column exists.

Score each layer: direct file match gives 25 points, service match gives 10 points, change-type match gives 15 points. Cap the Historian's total contribution at 40 points.

After the matching logic, add a check: if no incidents exist in the database at all, return `"status": "insufficient_data"` with the message "No incident history available — deploy to a real environment and accumulate incident data to enable historical scoring." Do not return zero as if the PR is safe. Be honest that scoring is unavailable.

---

### 1C — Laravel: Add `change_type` Column + `AgentRun` Table

**Recon first:**
```bash
ls database/migrations/
ls database/seeders/
cat app/Jobs/RunAgentPipeline.php 2>/dev/null || grep -r "archaeologist\|historian\|negotiator" app/ --include="*.php" -l
```

Create two migrations. Run `php artisan make:migration add_change_type_to_incidents_table` and add a nullable string column `change_type` to the incidents table.

Run `php artisan make:migration create_agent_runs_table` and create this table:

```php
$table->id();
$table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
$table->string('agent_name');  // archaeologist, historian, negotiator, chronicler
$table->string('status');      // scored, insufficient_data, error
$table->json('input_payload')->nullable();
$table->json('output_payload')->nullable();
$table->integer('score_contribution')->default(0);
$table->text('reasoning')->nullable();
$table->integer('tokens_used')->default(0);
$table->decimal('cost_usd', 8, 6)->default(0);
$table->integer('duration_ms')->default(0);
$table->string('model_used')->nullable();
$table->string('input_hash')->nullable();
$table->timestamps();
```

Run `php artisan migrate`.

Create `app/Models/AgentRun.php`. Add `$fillable` for all columns above. Add `$casts` so `input_payload` and `output_payload` cast to array. Add a `pullRequest()` belongsTo relationship.

In the existing agent pipeline job (find it from the recon above), after each agent HTTP call, save an AgentRun record. Record the start time before the call and compute `duration_ms` as the difference. Record `tokens_used` from the response if your agent returns usage data. Estimate `cost_usd` as `tokens_used * 0.000001` (adjust this multiplier per model — check Azure OpenAI pricing for your deployed model).

Add the `agentRuns()` hasMany relationship to `PullRequest` model.

Add a debug panel to `resources/views/driftwatch/show.blade.php`. Find the existing view first — do not rewrite it. At the very bottom, before the closing content div, add a section that only renders when `?debug=1` is in the URL. The section should loop over `$pullRequest->agentRuns` and show: agent name, status badge (green for scored, red for error/insufficient_data), duration, token count, cost in USD, score contribution, the reasoning text, and a collapsible raw JSON output block. Use the existing CSS classes from the template for badges and cards.

**Commit message:** `Stage 1: Agent scoring — diff reading, semantic matching, real data, agent runs tracking`

---

## STAGE 2 — Fix the UI
**Goal:** Make the risk score dominant, deploy decision impossible to miss, score factors explainable.
**Scope:** Laravel Blade views only. Do not touch Python agents this stage.
**Estimated time:** 2–3 hours

---

### 2A — Recon the Existing PR Detail View

```bash
cat resources/views/driftwatch/show.blade.php
# Identify: where is the score circle element? What class/id does it have?
# Identify: where is the deploy decision card?
# Identify: where is the score composition section?
# Identify: where are Action Items and Time Bomb Detection sections?
# Check what CSS framework and what existing component patterns exist
grep -r "class=" resources/views/driftwatch/show.blade.php | head -30
ls resources/views/driftwatch/partials/ 2>/dev/null
```

Do not rewrite the view from scratch. Find each element described below and modify it in place.

---

### 2B — Risk Score and Deploy Decision

Find the element that currently renders the small "10" score circle. Replace it with a larger version that is visually dominant — minimum 120x120px, thick colored ring, score number at minimum 48px font size, risk level label in large caps below the number. Use these color rules based on the numeric score: 0–20 is green, 21–40 is yellow, 41–60 is orange, 61–80 is red, 81–100 is dark red with a CSS pulse animation. Use whatever Tailwind or Bootstrap classes the existing template already uses for colors — match the existing design language.

Find the deploy decision card. Move it to the very top of the main content area, before everything else including the score. Make it full width. Green background with "✅ SAFE TO DEPLOY" for APPROVED, red background with "🚫 DEPLOYMENT BLOCKED" for BLOCKED, orange with "⏳ AWAITING HUMAN DECISION" for PENDING_REVIEW. Minimum 60px tall. This must be the first thing anyone sees when they open the page.

---

### 2C — Reorder Sections and Promote Time Bomb

Find the Action Items section. Move it so it renders before the Blast Radius details section. The current order is roughly: score → blast radius → action items. It should be: deploy banner → score → time bomb warning → action items → blast radius details.

Find the Time Bomb Detection section. It is currently at the bottom of the page. Move it to render directly below the risk score circle, styled as a prominent amber/yellow warning card with a ⏰ icon and a title of "Time Bomb Detection." Each flagged file should show its name and downstream dependency count.

---

### 2D — Expandable Score Factor Reasoning

Find the Score Composition panel — the section showing "+10 pts Blast Radius" and similar factor rows. Each row must become an expandable accordion. Use whatever accordion or collapse component already exists in the template.

When a factor row is expanded it must show: the agent's reasoning text (from `agentRuns` where `agent_name` matches — pass this data from the controller), a confidence label derived from the score contribution (above 20 is High, 10–20 is Medium, below 10 is Low), a "What would raise this score?" hint generated statically per factor type, and a link to the GitHub diff for this PR (construct the URL from the repo name and PR number already stored in the PullRequest model).

Before modifying the view, check the controller that renders it:
```bash
grep -r "show\|driftwatch" app/Http/Controllers/ --include="*.php" -l
cat app/Http/Controllers/PullRequestController.php  # or equivalent
```

Make sure the controller passes `$pullRequest->load('agentRuns', 'riskAssessment', 'blastRadius')` to the view so the reasoning data is available.

**Commit message:** `Stage 2: UI — dominant score, full-width deploy banner, expandable reasoning, section reorder`

---

## STAGE 3 — Fix the Blast Radius Graph
**Goal:** Replace the force-directed graph with a hierarchical DAG layout that works at scale.
**Scope:** JavaScript and the blast map blade partial only.
**Estimated time:** 2–3 hours

---

### 3A — Recon the Graph

```bash
grep -r "force\|d3\|graph\|vis\|cytoscape\|dagre\|elk" resources/ --include="*.js" --include="*.blade.php" -l
# Find the blast map partial
find resources/views/ -name "*blast*" -o -name "*graph*" -o -name "*map*" | head -10
cat resources/views/driftwatch/partials/blast-map.blade.php 2>/dev/null
# Find where graph data is passed as JSON
grep -r "dependency_graph\|blast_radius\|graphData\|nodes\|edges" resources/ --include="*.blade.php"
# Check what JS libraries are already loaded in the layout
grep -r "d3\|vis\|cytoscape\|dagre\|elk\|script src" resources/views/layouts/ --include="*.blade.php"
```

---

### 3B — Replace Force-Directed with Hierarchical Layout

The current graph uses a force-directed layout which clusters into an unreadable tangle at 30+ nodes. The data is a dependency tree — it has a clear left-to-right direction: PR → changed files → affected services → exposed endpoints. A hierarchical layout matches this perfectly.

Replace the graph layout algorithm only — keep the data preparation code that builds nodes and edges from the blast radius JSON. Do not rewrite the data layer.

Use the `dagre-d3` library which works with the existing D3 setup. Load it from CDN: `https://cdnjs.cloudflare.com/ajax/libs/dagre-d3/0.6.4/dagre-d3.min.js`. Add this script tag to the blast map partial (not the global layout — scoped to this partial only).

**CRITICAL — direction must be left-to-right.** Set `rankdir: "LR"` explicitly on the dagre graph object before calling `dagre.layout()`. Do NOT use top-down (`TB`) — this causes the graph to grow very tall at 15+ nodes and requires scrolling. Left-to-right keeps the graph wide and scannable at any PR size. Verified by real-world testing on n8n PR #26562 which produced a working but top-down layout in the existing Flow tab.

Node shape rules: PR origin node is a filled rectangle (blue). Changed files are rectangles with a border. Affected/downstream files are rounded rectangles. Services are bold-bordered rounded rectangles. Endpoints are diamonds. Node color rules: red for files scoring above 20, orange for 10–20, gray/yellow for below 10. Blue for service nodes. Cyan for endpoint nodes.

Node size must scale with downstream dependent count — a file imported by 5 others should have a larger node than one imported by 1. Set minimum node width to 160px so labels do not clip. Show `{n} deps` below the filename inside the node.

**Disconnected node bug — must fix.** In the current implementation, some nodes appear floating disconnected from the graph (observed in real testing: `expression.ts` floated below the main graph with no edges). This happens when a file exists in `affected_files` but has no entry in `dependency_graph`. Before rendering, validate that every node in the graph has at least one edge. If a node has no edges, either: find its parent by scanning the dependency_graph values for its filename, or attach it as a direct child of the PR origin node as a fallback. Never render an orphan node.

**Tab default — the Flow tab (hierarchical) must be the DEFAULT tab**, not the Blast Map (force-directed). The force-directed layout is the confusing one. Swap the tab ordering so Flow is first and selected by default. Rename "Flow" to "Dependency Tree" to be clearer. Keep "Blast Map" as the second tab for users who prefer the radial view.

When a node is clicked, show a tooltip or side panel with: the file's change type and score, the list of files that import it, and the last incident it appeared in (pass this data from the controller via a `data-incidents` attribute on the graph container).

**Commit message:** `Stage 3: Blast radius graph — LR hierarchical DAG, orphan node fix, Flow tab as default`

---

## STAGE 4 — CI/CD Pipeline Integration
**Goal:** Add a GitHub Actions action so DriftWatch lives inside pipelines, not alongside them.
**Scope:** New files in a `/action/` directory at project root. No existing files modified.
**Estimated time:** 2–3 hours

---

### 4A — Recon the API

```bash
# Find the existing API endpoint that triggers analysis
cat routes/api.php
grep -r "analyze\|webhook\|trigger\|pipeline" app/Http/Controllers/ --include="*.php" -l
# Find what authentication the API uses
grep -r "api_token\|sanctum\|Bearer\|auth:api" routes/api.php app/Http/ --include="*.php"
# Check if there is a result-polling endpoint
grep -r "status\|result\|score" routes/api.php
```

---

### 4B — Create the GitHub Action

Create the directory `/action/` at the project root. Create these three files:

**`/action/action.yml`** — the action definition. It must accept these inputs: `driftwatch-url` (the deployed DriftWatch API base URL), `api-token` (secret for authenticating to DriftWatch), `risk-threshold` (integer, default 70, the score above which the action fails), `block-on-critical` (boolean, default true). It must output: `risk-score`, `risk-level`, `decision`.

The action runs using Node.js. Set `runs.using` to `node20` and `runs.main` to `dist/index.js`.

**`/action/src/index.js`** — the action logic. Read the GitHub context to get the PR number and repository name from `process.env.GITHUB_EVENT_PATH` (parse the JSON file at that path). POST to `{driftwatch-url}/api/analyze` with the PR number, repo name, and a Bearer token from `api-token` input. The response will include a `job_id`. Poll `{driftwatch-url}/api/jobs/{job_id}/status` every 10 seconds until status is `completed` or `failed`, with a maximum wait of 5 minutes. Once complete, read the `risk_score` and `decision` from the result. If `risk_score` exceeds the `risk-threshold` input and `block-on-critical` is true, call `core.setFailed()` with a message showing the score and which services are at risk. Otherwise call `core.setOutput()` with the score and decision. Use the `@actions/core` and `@actions/github` npm packages.

**`/action/README.md`** — usage example showing how to add the action to a workflow yaml file.

Create `/action/package.json` with dependencies on `@actions/core` and `@actions/github`. Run `npm install` in the `/action/` directory and run `npm run build` using `@vercel/ncc` to bundle it into `dist/index.js`. Check that `dist/index.js` exists and is not empty before committing.

Make sure the DriftWatch API has an endpoint `POST /api/analyze` that accepts a PR number and repo name and returns a job ID, and a `GET /api/jobs/{id}/status` polling endpoint. If these do not exist yet, create them:

```bash
# Check existing API routes first
cat routes/api.php
# If the endpoints don't exist, create them in the appropriate controller
```

**Commit message:** `Stage 4: GitHub Actions action — driftwatch-analyze@v1 with polling and threshold blocking`

---

### 4C — Lightweight Mode (Zero-Infrastructure Entry Point)

This is a competitive neutralizer. Any project could build a GitHub Action that calls an external API. The differentiator is that DriftWatch also works with *no backend at all* — a self-contained mode that runs the entire analysis inside the GitHub Actions runner using only an Azure OpenAI key.

Add a `mode` input to `action.yml` with two options: `full` (default, calls the DriftWatch backend) and `lightweight` (runs analysis entirely inside the action runner, no backend required).

When `mode: lightweight`, the action must:

Step one: read the PR diff directly using the GitHub API inside the action — call `https://api.github.com/repos/{owner}/{repo}/pulls/{pr_number}/files` using the `GITHUB_TOKEN` that is automatically available in every Actions environment.

Step two: for each changed file, fetch the file content using `https://api.github.com/repos/{owner}/{repo}/contents/{path}?ref={head_sha}`.

Step three: construct a prompt containing the diff and file content and call Azure OpenAI directly using the `AZURE_OPENAI_ENDPOINT` and `AZURE_OPENAI_API_KEY` inputs. The prompt instructs the model to classify each changed file using the same risk scoring table from Stage 1A and return a JSON risk score and summary.

Step four: post the result as a GitHub PR check using `@actions/github`'s `checks.create` API — this creates a native green/red check on the PR, not just a comment.

The lightweight mode has no history, no incident matching, no deployment weather. It only does code-level blast radius analysis. But it works in any repo in under 5 minutes with zero infrastructure. Add this to the README as "Quick Start (5 minutes)" and promote the full mode as "Full Platform (enterprise features)."

This two-tier install story directly neutralizes any competitor that only offers the lightweight pattern — DriftWatch has both, and users graduate from one to the other naturally.

---

## STAGE 5 — Structured Briefing Pack + Merge-Readiness Pack
**Goal:** Make agent inputs and outputs formal structured documents (from SASE research). This improves agent reliability and creates the enterprise audit trail.
**Scope:** Python agents and one new Laravel controller endpoint.
**Estimated time:**  COUPLE MINUTES

---

### 5A — Briefing Pack: Structure the Input to Every Agent

**Recon first:**
```bash
# Find exactly what data is passed to each agent today
cat app/Jobs/RunAgentPipeline.php 2>/dev/null || grep -r "agent\|http\|guzzle" app/ --include="*.php" -l | head -5 | xargs cat
```

Before the Archaeologist runs, generate a Briefing Pack from the PR data and pass it as the structured input. The Briefing Pack is a JSON object that every agent receives. Add this generation step in the Laravel pipeline job that dispatches agents:

```json
{
  "briefing_id": "PR-{pr_number}-{timestamp}",
  "pr_number": 365,
  "pr_title": "add voice message",
  "pr_description": "full PR body text",
  "pr_author": "username",
  "repo_full_name": "org/repo",
  "base_branch": "main",
  "head_branch": "feature/voice-messages",
  "files_changed_count": 10,
  "additions": 2180,
  "deletions": 1461,
  "pr_url": "https://github.com/...",
  "requested_analysis_time": "ISO8601 timestamp"
}
```

Pass this briefing to the Archaeologist as the input payload. The Archaeologist then enriches it with its findings and passes the enriched version to the Historian, and so on down the pipeline. Each agent adds its section to the shared document. This creates a complete provenance trail.

Store the final enriched briefing as the `input_payload` in the AgentRun records.

---

### 5B — Merge-Readiness Pack: Structure the Output

After the Negotiator agent runs, generate a formal Merge-Readiness Pack (MRP) and store it. Add an `mrp_payload` JSON column to the `deployment_decisions` table via a new migration. The MRP is a structured JSON document:

```json
{
  "mrp_id": "MRP-{pr_number}-{version}",
  "version": 1,
  "generated_at": "ISO8601",
  "decision": "APPROVED | BLOCKED | PENDING_REVIEW",
  "overall_risk_score": 45,
  "risk_level": "MEDIUM",
  "evidence": {
    "blast_radius": { "score": 25, "summary": "...", "affected_services": [] },
    "incident_history": { "score": 10, "summary": "...", "matching_incidents": [] },
    "deployment_weather": { "score": 10, "summary": "..." }
  },
  "conditions_for_approval": ["Run integration tests on chatify-messaging", "Monitor error rate post-deploy"],
  "approved_by": null,
  "approved_at": null,
  "audit_trail": []
}
```

Increment the version number each time the same PR is re-analyzed. Store all versions — do not overwrite. This gives you a full history of how the risk assessment evolved as the PR was updated.

Add a "View MRP" button to the PR detail page that renders the MRP as a formatted card. Show the version number and a "previous versions" dropdown if multiple MRP versions exist.

**Commit message:** `Stage 5: Briefing Pack inputs, Merge-Readiness Pack outputs, MRP versioning`

---

## STAGE 6 — Deployment Weather
**Goal:** Score the environmental risk at deploy time, not just the code risk.
**Scope:** New Python agent (Sentinel Weather), new Laravel endpoint, new UI card.
**Estimated time:** 3–4 hours

---

### 6A — Recon Azure MCP and Monitor Access

```bash
# Check what Azure MCP tools are available
grep -r "azure\|monitor\|metrics\|appinsights" agents/ --include="*.py" -r
cat agents/mcp_server.py 2>/dev/null || find agents/ -name "*.py" | xargs grep -l "azure\|monitor" 2>/dev/null | head -5 | xargs cat
# Check Azure credentials available
grep -r "AZURE_\|APP_INSIGHTS\|MONITOR" agents/ --include="*.py" -r | grep -i "environ\|os.get\|config"
```

---

### 6B — Add Weather Scoring to the Negotiator Agent

Rather than a new agent, extend the Negotiator's system prompt and tool calls to check environmental signals before issuing its decision. Add these checks in sequence:

**Check 1 — Concurrent deploys.** Call the GitHub API (via MCP) to list recent workflow runs in the repository in the last 30 minutes and the next 30 minutes in related repositories if you have access. If any other deploy is in progress to a service in the blast radius, add 20 points to weather score.

**Check 2 — Active incidents.** Query the incidents table for any incident with `resolved_at` IS NULL. If any open incident affects a service in the blast radius, add 30 points.

**Check 3 — Infrastructure health.** If Azure Monitor MCP tools are available, query current error rate and latency for each service in the blast radius. If any service has error rate above 1% or latency above 2x its 7-day average, add 20 points.

**Check 4 — High traffic window.** Check the current hour against a configurable high-traffic schedule stored in the DriftWatch config table (or a simple JSON config file). If deploying during a known high-traffic window, add 15 points.

**Check 5 — Recent related deploy.** Check if another PR touching the same services was merged and deployed in the last 2 hours. If yes, add 10 points (stacked deploys increase blast radius correlation).

The weather score is separate from the code risk score. Store it in the `deployment_decisions` table by adding a `weather_score` integer column. Display it on the PR detail page as a separate "Deployment Weather" card showing: overall weather score, which checks fired, and a recommendation: "Now is a good time to deploy" vs "Conditions are unfavorable — consider waiting."

**Commit message:** `Stage 6: Deployment Weather — environmental risk scoring at deploy time`

---

## STAGE 7 — Teams Notification + Human Decision Loop
**Goal:** Send high-risk alerts to Teams with approve/block buttons and log human decisions.
**Scope:** New Laravel notification class, new API endpoints for decision callback.
**Estimated time:** 2 hours

---

### 7A — Recon

```bash
# Check if Teams webhook is already configured anywhere
grep -r "teams\|webhook\|notification" app/ --include="*.php" -r -l
grep -r "TEAMS_\|WEBHOOK_" .env.example config/ --include="*.php" -r 2>/dev/null
# Check existing notification setup
ls app/Notifications/ 2>/dev/null
```

---

### 7B — Teams Adaptive Card

Add `TEAMS_WEBHOOK_URL` to `.env.example`. In the Negotiator's pipeline step (after the decision is saved), check if the risk score is above 60 or the decision is BLOCKED. If so, send an HTTP POST to the Teams webhook URL with this Adaptive Card payload.

The card must show: the PR number and title, risk score as a large number with color (use Teams accent colors), affected services list, top two action items, and two Action.OpenUrl buttons — one for APPROVE linking to `{app_url}/api/decisions/{pr_id}/approve?token={decision_token}` and one for BLOCK linking to the same with `/block`. Do not use Action.Http (it requires additional Teams app registration) — use Action.OpenUrl which works with any incoming webhook.

Create the decision callback endpoints in Laravel at `GET /api/decisions/{id}/approve` and `GET /api/decisions/{id}/block`. Both accept a `token` query parameter that is a signed token generated when the decision was created (use Laravel's `Str::hmac` or a simple `hash_hmac`). Validate the token, update the deployment decision record, post a comment back to the GitHub PR via the GitHub API, and render a simple HTML confirmation page: "Decision recorded. PR #365 has been APPROVED by {github_username}."

Log every human decision to the MRP's `audit_trail` array with: who decided, what they decided, and when.

**Commit message:** `Stage 7: Teams Adaptive Card notifications with approve/block decision loop`

---

## STAGE 8 — Change Management Auto-Record
**Goal:** Auto-generate and submit change management records so engineers stop writing them manually.
**Scope:** New Laravel service class and optional Jira/ServiceNow/Azure DevOps Board webhook.
**Estimated time:** 2 hours

---

### 8A — Recon

```bash
grep -r "jira\|servicenow\|devops\|board\|ticket" config/ app/ --include="*.php" -r 2>/dev/null | head -10
grep -r "JIRA_\|SERVICENOW_\|ADO_" .env.example 2>/dev/null
```

---

### 8B — Auto-Generate Change Record

Create `app/Services/ChangeManagementService.php`. When a PR receives an APPROVED decision (either automated or via Teams callback), this service generates a change record document and optionally submits it.

The change record must contain: change title from the PR title, change justification auto-written from the MRP summary, risk score with evidence, blast radius (services and files affected), rollback plan auto-generated as "Revert commit {commit_sha} and redeploy previous version", approver name from the deployment decision, and the MRP ID as the evidence reference.

First, render this as a formatted section on the PR detail page under a "Change Record" tab. This is the minimum viable version — it generates the document but does not submit it anywhere.

Second, if `CHANGE_MGMT_WEBHOOK_URL` is set in the environment, POST the change record as JSON to that URL. This allows integration with any system (Jira, ServiceNow, Azure DevOps) by simply pointing the webhook at the right endpoint. Document this in the README with an example Jira webhook configuration.

**Commit message:** `Stage 8: Auto-generated change management records from MRP data`

---

## STAGE 9 — Incident Correlation (Which Deploy Caused This?)
**Goal:** When an incident occurs, automatically correlate it back to the most likely causal deploy.
**Scope:** Extend Chronicler agent and add a new dashboard panel.
**Estimated time:** 2–3 hours

---

### 9A — Recon

```bash
cat agents/chronicler/function_app.py
grep -r "outcome\|incident\|deploy" app/Models/ --include="*.php" -l | xargs cat
```

---

### 9B — Causal Deploy Correlation

Extend the Chronicler agent so that when it receives notification of a new incident (either via webhook from Azure Monitor or via manual incident creation in DriftWatch), it runs a correlation algorithm:

Step one: get the timestamp of when the incident started. Step two: query all `deployment_outcomes` for deploys that completed in the 2 hours before the incident started and affect any of the same services. Step three: for each candidate deploy, compute a correlation score based on: time proximity (deploy within 15 minutes = +40 pts, 15–60 min = +20 pts, 60–120 min = +10 pts), service overlap (each matching service = +15 pts), and whether the deploy's blast radius included the files in the incident's `affected_files` (+25 pts). Step four: return the top candidate deploy with its correlation score and the evidence for the correlation.

Store the correlation result in the Incident record (add a `probable_cause_pr_id` foreign key and a `correlation_score` integer column via migration). Display it on the incidents list and detail pages as "Probable cause: PR #347 (85% correlation)."

Add a dashboard panel on the Analytics page showing correlation accuracy: how often the Chronicler's probable cause identification matched what was determined in the post-mortem. This requires a `correlation_confirmed` boolean column on the Incident model.

---

### 9C — Autonomous Runbook Execution

This absorbs a whole competitor idea into DriftWatch's existing Chronicler architecture. When the Chronicler identifies a causal deploy and the incident is still unresolved, it checks a runbook library for known remediation steps and executes low-risk ones automatically.

Create a `runbooks` table via migration with these columns: `id`, `name`, `trigger_pattern` (JSON — conditions that activate this runbook, e.g. service name + error type), `steps` (JSON array of step objects), `max_auto_execute_risk` (integer 0–100, steps below this threshold execute automatically, above require human confirmation), `created_at`, `updated_at`.

Each step object in the `steps` array has: `action` (one of: `restart_container`, `scale_up`, `clear_cache`, `revert_deploy`, `notify_team`), `target` (service name or deployment name), `risk_level` (integer 1–100), and `description` (plain English explanation of what the step does).

Seed three example runbooks: one for high error rate on a web service (clear cache → scale up → notify team), one for a failed deploy (revert to previous deployment slot → notify team), and one for database connection exhaustion (restart connection pooler → notify team).

When the Chronicler identifies an incident, it queries the runbooks table to find any runbook whose `trigger_pattern` matches the incident's affected services and error type. If a match is found it iterates through the steps. For each step where `risk_level` is below the runbook's `max_auto_execute_risk` threshold, it executes the step immediately via the appropriate Azure MCP or GitHub API call. For steps above the threshold it generates a Teams CRP (Consultation Request Pack from Stage 6) asking for human confirmation before proceeding.

Log every action taken — automated or human-confirmed — to a new `runbook_executions` table with: incident ID, runbook ID, step executed, outcome (success/failure), executed_by (agent or human username), and timestamp. This is the full audit trail for autonomous remediation.

Create a Runbooks management page in the Laravel dashboard where engineers can view, create, and edit runbooks. Keep the UI simple — a list of runbooks with their trigger patterns and step counts, and a detail view showing each step. Engineers need to be able to add their team's known fixes without writing code.

Display runbook execution history on the Incident detail page: "Chronicler executed 2 steps automatically. Step 3 required human approval — approved by @username at 03:47."

**Commit message:** `Stage 9: Incident correlation, autonomous runbook execution with human-in-the-loop gates`

---

## STAGE 10 — Prediction Accuracy Panel + Foundry Model Router
**Goal:** Prove the system learns over time. Move agents to Foundry for the $10K prize category.
**Scope:** New Analytics blade partial, Azure Foundry configuration in agents.
**Estimated time:** 3–4 hours

---

### 10A — Prediction Accuracy

```bash
# Check existing analytics page
find resources/views/driftwatch/ -name "*analytics*" -o -name "*dashboard*" | xargs cat 2>/dev/null | head -100
cat app/Http/Controllers/AnalyticsController.php 2>/dev/null || grep -r "analytics\|accuracy" app/Http/Controllers/ --include="*.php" -l
```

Add a new section to the Analytics page showing prediction accuracy over time. The data comes from joining `risk_assessments` (predicted score) with `deployment_outcomes` (whether an incident occurred). A prediction is "accurate" if: risk score above 60 and an incident occurred within 24 hours of deploy, or risk score below 40 and no incident occurred within 24 hours.

Show a line chart (use whatever charting library is already in the project — find it before using it) with two lines: predicted risk score over time and actual incident occurrence (binary 0/1 scaled to 0-100). Show metric cards: overall accuracy percentage, false positive rate (predicted high, no incident), false negative rate (predicted low, incident occurred). Show a per-service breakdown table.

---

### 10B — Foundry Model Router

```bash
# Check current agent HTTP calls to Azure OpenAI
grep -r "openai\|endpoint\|deployment\|gpt" agents/ --include="*.py" -r | grep -v "#" | head -20
grep -r "AZURE_OPENAI\|FOUNDRY\|MODEL_" agents/ --include="*.py" -r | grep "environ\|config\|os.get" | head -10
```

Read the Azure AI Foundry documentation for the Responses API v2 SDK before making changes. The goal is to route all agents through Foundry Agent Service with dynamic model selection.

For the Archaeologist (lower stakes, large input), use `gpt-4.1-mini`. For the Historian and Negotiator (risk scoring and deployment decisions where accuracy matters most), use `gpt-4.1`. For the Chronicler (post-deploy recording), use `gpt-4.1-mini`.

Add `AZURE_FOUNDRY_ENDPOINT` and `AZURE_FOUNDRY_PROJECT_NAME` to the environment configuration. Register each agent in Foundry with its system prompt as a Foundry Agent definition. Update each `function_app.py` to use the Foundry Responses API instead of the bare Azure OpenAI SDK. The Foundry API call replaces the existing OpenAI `chat.completions.create` call — find that call in each agent and replace it.

**Commit message:** `Stage 10: Prediction accuracy panel, Foundry Model Router with dynamic model selection`

---

## STAGE 11 — One-Command Install + Final Polish
**Goal:** Make DriftWatch deployable in under 15 minutes from zero. Final UI polish.
**Scope:** New `docker-compose.yml`, README updates, minor UI touches.
**Estimated time:** 2 hours

---

Create a `docker-compose.yml` at the project root that starts: a Laravel container with PHP-FPM, a MySQL container with the DriftWatch schema, a Redis container for queues, and an Nginx container as the proxy. Use environment variables for all secrets — no hardcoded values. Add a `docker-compose.example.env` file documenting every required variable.

Add a `scripts/setup.sh` that: copies `.env.example` to `.env`, runs `composer install`, runs `php artisan key:generate`, runs `php artisan migrate --seed`, and prints "DriftWatch is ready at http://localhost:8080."

Add a Deploy to Azure button in the README using a Bicep template. The Bicep template does not need to be complete — a basic App Service + MySQL Flexible Server + Application Insights template is enough to demonstrate the pattern.

**Commit message:** `Stage 11: Docker compose, setup script, Azure deploy button, final polish`

---

## WHAT MAKES DRIFTWATCH DIFFERENT — FOR YOUR SUBMISSION DESCRIPTION

Every other tool in this hackathon answers "what happened?" DriftWatch answers "what will happen if you merge this — and is right now even a safe moment to deploy?"

That second question is the differentiator. Tools like SonarQube, Checkmarx, and GitHub Advanced Security analyze code in isolation. DriftWatch analyzes code in the context of your system's specific incident history, live infrastructure state, and current deployment environment. A PR that is code-safe can still be environmentally dangerous — and no existing tool asks that question.

The feedback loop is the second differentiator. The Chronicler agent closes the loop between prediction and reality. The system gets more accurate for your specific codebase over time. No generic scanner does that.

The third differentiator, backed by the 2025 SASE research paper from Queen's University and Huawei, is that DriftWatch implements SE 3.0 patterns: structured Briefing Packs as agent inputs, formal Merge-Readiness Packs as versioned audit outputs, and Consultation Request Packs for agent-initiated human callbacks. These are the patterns that separate agentic coding tools from a true agentic software engineering platform.

---

## PRIZE TARGETING SUMMARY

| Prize | $$ | Stages that win it |
|-------|----|-------------------|
| Grand Prize — Agentic DevOps | $20K | Stages 1, 4, 5, 6 |
| Best Multi-Agent System | $10K | Stages 1, 5, 10B |
| Best Use of Microsoft Foundry | $10K | Stage 10B |
| Best Enterprise Solution | $10K | Stages 5, 7, 8 |
| Best Azure Integration | $10K | Stages 6, 9, 10 |

**Maximum realistic ceiling: $60K**

---

## STAGE COMPLETION CHECKLIST

After each stage, before committing, verify:
- [ ] No hardcoded file paths that assumed structure without reading first
- [ ] No new frontend frameworks introduced unless already in the project
- [ ] `docs/STAGE_PROGRESS.md` updated
- [ ] Committed with the exact message specified
- [ ] The feature works end-to-end with a real PR (not just the code compiles)

---

*Sources: SASE paper (Hassan et al., Queen's University / Huawei, 2025), Opsera Agentic DevOps Report 2026, IBM Shift Everywhere Report 2026*
