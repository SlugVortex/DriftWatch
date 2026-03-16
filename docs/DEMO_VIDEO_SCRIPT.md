# DriftWatch — 2-Minute Demo Script

**Pre-record prep**: Have the PR already analyzed. Speed up the agent pipeline animation in post (2-3x). Pre-load the PR detail page in a second tab so you can cut to it instantly.

---

## [0:00–0:10] Dashboard — Quick Flash

> "This is DriftWatch — a multi-agent pre-deployment risk intelligence system."

**Action**: Start on Dashboard. Quick scroll showing: 4 agent icons (Archaeologist → Historian → Negotiator → Chronicler), stat cards (PRs analyzed, avg risk score, deploys blocked, prediction accuracy), risk chart, recent PRs table. Don't stop — just smooth scroll down and back up.

---

## [0:10–0:20] Analyze a PR

> "Let's analyze a live pull request. I paste a GitHub PR URL and hit Analyze."

**Action**: Paste the PR URL into the input box. Click **Analyze**. The agent pipeline loading overlay appears — **speed this up 2-3x in post-editing** so all 4 agents complete in ~5 seconds. While it loads:

> "Four AI agents run in sequence — the Archaeologist maps what this PR touches, the Historian scores the risk, the Negotiator decides deploy or block, and the Chronicler records the outcome for learning."

---

## [0:20–0:40] PR Detail — Verdict Banner + Risk Score

**Action**: Land on the PR detail page. The orange/red verdict banner is immediately visible.

> "Instantly we see the verdict — this PR scores 65 out of 100, elevated risk. And it tells us straight up: *this could break production*. Not just 'it's a big change' — it tells us *why* and whether to deploy."

**Action**: Point to the "Why this score" section and the bottom-line verdict ("Elevated risk — could break production").

> "The Deployment Weather system checks the environment too — other active deploys, peak traffic, incident history. The score composition breaks down blast radius, history, and code complexity."

**Action**: Quick hover over score breakdown bars. Then scroll past the action items and "What to Review" — just acknowledge them:

> "We get prioritized action items, a filterable review checklist ranked Critical to Low..."

---

## [0:40–1:05] Dependency Tree + File Click + Chat

**Action**: Scroll to Impact Analysis. Dependency Tree tab is active.

> "The blast radius map shows every file this PR touches and their downstream dependencies. Green check means safe, red X means flagged — and I can click to toggle these as I review."

**Action**: Click a checkbox on a node to toggle it. Then click a file node (e.g., `function_app.py`) — the side panel opens with file details.

> "Clicking a file opens its details — risk score, change type, dependencies. And here's the Impact Chat."

**Action**: The chat panel is visible on the right. Type or have pre-typed: "Is this file safe?"

> "I can ask the AI anything about this PR. Watch — the verdict is highlighted right in the response. Green means OK, orange means flagged. No more reading paragraphs to find the answer."

**Action**: Show the highlighted verdict in the chat response (green "OK" badge or orange "ISSUE" badge).

---

## [1:05–1:25] Code Preview — Dock + Split

**Action**: Click "View code" on a file to open the code preview modal.

> "Full code preview with syntax highlighting, diff view, and source view."

**Action**: Click **Dock** button — modal docks to the left side. Chat stays visible on the right.

> "I can dock it to the side and keep chatting. Or split view —"

**Action**: Click **Split** — second file pane appears. Select another file from dropdown.

> "— to compare files side by side, pulled directly from GitHub."

**Action**: Close the modal. Quick flash.

---

## [1:25–1:40] Review All + Pause

**Action**: Click **Review All** button in the chat.

> "Review All goes through every file sequentially. I can pause, resume, or stop anytime."

**Action**: Let 2-3 files review (show green checks appearing in the progress tracker). Click **Pause**. Show it paused. Click **Resume**. Then stop it.

> "Each file gets a clear OK or FLAGGED verdict — flagged items are highlighted so you can spot issues instantly."

---

## [1:40–1:50] Ask Copilot + Observability

> "If issues are found, one click sends them to GitHub Copilot for suggested fixes."

**Action**: Point to the "Ask Copilot" button. Then quick scroll to the bottom showing:
- Agent execution artifacts (Archaeologist, Historian, Negotiator, Chronicler cards)
- Cost and token usage
- Deployment Outcome / Teams Adaptive Card if visible

> "Every agent run is fully observable — execution time, cost, model used, reasoning. All traceable through Azure App Insights."

---

## [1:50–2:00] Repositories + Close

**Action**: Quick click to **Repositories** page.

> "Connect any GitHub repo and DriftWatch automatically scans every new PR through the pipeline."

**Action**: Show the repo list / connect button. Then go back to Dashboard.

> "DriftWatch doesn't tell you what changed. It tells you what *breaks* — before it reaches production. Built on Azure AI Foundry with 6 Semantic Kernel agents, 18 Azure services, and zero blind spots."

---

## Timing Budget

| Section | Time | What to cut if over |
|---------|------|-------------------|
| Dashboard flash | 10s | Can trim to 5s |
| Analyze PR | 10s | Speed up agent animation more |
| Verdict + Risk | 20s | Skip score composition detail |
| Tree + Chat | 25s | **Core demo — don't cut** |
| Code dock/split | 20s | Skip split, just show dock |
| Review All | 15s | Show 2 files max, skip resume |
| Copilot + Observability | 10s | Skip cost detail |
| Repos + Close | 10s | Can trim to 5s |

## Recording Tips
- Use 1920x1080 resolution, browser at 90% zoom
- Smooth, deliberate mouse movements — never rush the cursor
- Speed up the agent pipeline to 2-3x in post
- Have the PR pre-analyzed in a second tab as backup
- If you're running over, cut: score composition, split view, observability detail
- The money shots: verdict banner, dependency tree toggles, chat verdict highlights, Review All progress
