# DriftWatch — 2-Minute Demo Script (PR #27066)

**PR**: n8n-io/n8n #27066 — "Make Wait node fully durable" | 13 files, +381/-274
**Prep**: PR already analyzed. Browser 1920x1080, 90% zoom. Speed up agent animation 2-3x in post.

---

## [0:00-0:05] Dashboard Flash

> "This is DriftWatch — a multi-agent pre-deployment risk intelligence system."

**Action**: Quick scroll of Dashboard — agent icons, stat cards, recent PRs. Don't stop.

---

## [0:05-0:15] Analyze a PR

> "I paste a GitHub PR URL and hit Analyze. Four AI agents run — Archaeologist maps blast radius, Historian scores risk, Negotiator decides deploy or block, Chronicler records the outcome."

**Action**: Paste `https://github.com/n8n-io/n8n/pull/27066`, click Analyze. Speed up animation in post.

---

## [0:15-0:35] Verdict + Score + Weather

> "Risk score 46 out of 100 — approved. It tells us why: core scheduling logic changed, new database queries, Wait node code removed. Blast radius hit 13 files across 3 services."

**Action**: Show verdict banner, point to "Why this score" factors.

> "Deployment Weather detected 3 concurrent GitHub Actions workflows. No incidents, no high traffic — conditions are acceptable."

**Action**: Quick scroll to Weather card showing "Partly Cloudy" at 20/100.

---

## [0:35-1:00] Tree + Chat

> "The blast radius map shows every file and its dependencies. execution.repository.ts is highest risk at 30 points. I can mark files as I review — green check, red X, or reset."

**Action**: Toggle checkboxes on 2-3 nodes. Click `wait-tracker.ts` to select it.

> "I ask the AI — is this file safe? The verdict is highlighted right in the response. Green for OK, orange for flagged."

**Action**: Type "Is this file safe?" in chat. Show highlighted verdict badge.

---

## [1:00-1:15] Review All + Code

> "Review All goes through every file. I can pause, resume, or stop. Each gets an OK or FLAGGED verdict."

**Action**: Click Review All. Let 2-3 files review with checks appearing. Pause, resume, stop.

> "Full code preview with diffs pulled from GitHub. I can dock it and keep chatting."

**Action**: Click "View diff" on a file. Click Dock. Show side-by-side with chat. Close.

---

## [1:15-1:30] Action Items + Copilot + Observability

> "Prioritized action items — review critical files, verify dependencies, use canary deployment. If issues are flagged, Ask Copilot sends them to GitHub Copilot for fixes."

**Action**: Point to action items, then the purple Ask Copilot button.

> "Every agent is fully observable — the Archaeologist took 24 seconds, total pipeline 29 seconds, total cost less than a cent."

**Action**: Scroll to pipeline timeline. Point to agent cards with times and costs.

---

## [1:30-1:45] GitHub Comment + Teams

> "Results post automatically — a risk summary on the GitHub PR, and an Adaptive Card to Microsoft Teams."

**Action**: Show Teams card preview. Quick flash.

---

## [1:45-2:00] Close

> "13 files, 3 services, risk score 46, deployment weather checked, approved — all in 29 seconds. DriftWatch doesn't tell you what changed. It tells you what breaks — before it reaches production."

**Action**: Scroll to verdict banner. Hold 2 seconds. End.

---

## Timing Budget

| Section | Time | Cut if over |
|---------|------|------------|
| Dashboard | 5s | Trim to 3s |
| Analyze | 10s | Speed up animation more |
| Verdict + Weather | 20s | Skip weather detail |
| Tree + Chat | 25s | **Don't cut** |
| Review All + Code | 15s | Skip dock |
| Items + Copilot + Obs | 15s | Skip cost detail |
| GitHub + Teams | 15s | Skip Teams |
| Close | 15s | Trim to 10s |

## Key Numbers

| Data | Value |
|------|-------|
| Risk Score | 46/100 |
| Decision | Approved |
| Files | 13 |
| Services | 3 |
| Blast Radius | 31/100 |
| Weather | 20/100 |
| Pipeline | 29.3s |
| Cost | ~$0.007 |
| Top Risk | execution.repository.ts (30 pts) |
