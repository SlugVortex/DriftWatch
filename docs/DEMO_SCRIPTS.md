# DriftWatch — Demo Scripts for Judges (2 minutes each)

> **Hackathon:** Microsoft AI Dev Days — Challenge 2: Agentic DevOps
> **Product:** DriftWatch — Multi-Agent Pre-Deployment Risk Intelligence

---

## SCRIPT 1: "The Happy Path" — End-to-End PR Analysis

**Audience:** Judges who want to see the full product working.
**Duration:** 2 minutes
**Theme:** Show the complete pipeline from PR to decision.

### Script

**[0:00–0:15] — Hook**
> "Every deployment is a bet. DriftWatch uses 4 AI agents to tell you the odds before you push to prod."

**[0:15–0:35] — Dashboard Overview**
- Open DriftWatch dashboard (`/driftwatch`)
- Point out: stat cards (total PRs, risk scores, decisions), recent PR table
- "This is our command center — every PR that comes through GitHub gets analyzed automatically."

**[0:35–1:00] — PR Detail Deep Dive**
- Click into a PR (e.g., PR #589)
- Show the deploy decision banner at the top (green/red/orange)
- Show the risk score circle — "This PR scored 45 out of 100"
- Scroll to Deployment Weather card — "We also check environmental conditions — are there active incidents? Other deploys in flight?"

**[1:00–1:25] — Blast Radius Visualization**
- Click "Dependency Tree" tab — show the DAG with files and dependencies
- Click "Blast Map" — show the animated concentric radius visualization
- "The Archaeologist agent mapped 8 changed files across 4 services. Red dots are directly changed, amber are in the blast radius."
- Hover over a node to show the tooltip

**[1:25–1:45] — Impact Chat**
- Open the Impact Chat sidebar
- Click a file card — show the AI-generated summary
- Click "View Code" — show the full-screen code modal
- "Developers can explore the blast radius conversationally and review code right here."

**[1:45–2:00] — Close**
> "4 agents, 14 Azure services, one decision: should this code ship? DriftWatch gives you the answer before you find out the hard way."

---

## SCRIPT 2: "The Architecture Story" — Azure Services Deep Dive

**Audience:** Judges evaluating Azure integration depth.
**Duration:** 2 minutes
**Theme:** Show how many Azure services are integrated and why.

### Script

**[0:00–0:15] — Hook**
> "DriftWatch orchestrates 14 Azure services into an agentic DevOps pipeline. Let me show you the architecture."

**[0:15–0:40] — Architecture Diagram**
- Navigate to Settings (`/driftwatch/settings`)
- Scroll to the Azure Architecture Mermaid diagram
- Walk through: "GitHub PR triggers a webhook to our Laravel app on Azure App Service. The Semantic Kernel orchestrator dispatches to 5 agents running as Azure Functions V2."
- Point out: "Each agent calls Azure OpenAI GPT-4.1-mini. The Security Agent uses Azure AI Search for RAG — it queries a knowledge base of OWASP Top 10 and CVE patterns."

**[0:40–1:05] — Agent Pipeline Configuration**
- Scroll up to Pipeline Orchestration
- Show the 3 templates: Full Analysis, Quick Scan, Gated Deployment
- Toggle agent switches on/off — "Teams can customize which agents run. Quick Scan skips historical analysis for low-risk PRs."
- Show environment thresholds — "Production has a higher bar than staging."

**[1:05–1:30] — Live Services Check**
- Show each settings card:
  - GitHub Integration (webhook URL + token status)
  - AI Agent Endpoints (5 agents, connection status)
  - Azure OpenAI (endpoint + model)
  - Azure Speech TTS (text-to-speech on every card)
  - Azure AI Search (Security RAG)
  - App Insights (observability)
  - Teams Notifications (Adaptive Cards with Approve/Block)

**[1:30–1:50] — Teams Integration**
- "When risk exceeds the threshold, an Adaptive Card goes to Teams with Approve and Block buttons. The human decision loops back into the pipeline via signed callbacks."

**[1:50–2:00] — Close**
> "14 services, 5 agents, 3 pipeline templates, zero manual config required. DriftWatch turns Azure into an intelligent deployment safety net."

---

## SCRIPT 3: "The Explainability Story" — Transparent AI Decisions

**Audience:** Judges evaluating responsible AI and explainability.
**Duration:** 2 minutes
**Theme:** Show that every decision is transparent and auditable.

### Script

**[0:00–0:15] — Hook**
> "AI making deployment decisions is scary — unless you can explain exactly why. DriftWatch is fully explainable."

**[0:15–0:40] — Explainability Page**
- Navigate to Explainability (`/driftwatch/explainability`)
- Walk through the 4-agent pipeline diagram
- "Every PR goes through 4 sequential agents. The Archaeologist maps blast radius, the Historian correlates with past incidents, the Negotiator makes the call, and the Chronicler tracks accuracy."

**[0:40–1:05] — Scoring Breakdown**
- Show the Blast Radius Score card — point values per file type
- "A migration is +25 points. An auth middleware change is +30. A CSS file is just +2. Every point is earned, not guessed."
- Show the Risk Score formula — "History is capped at 40 points. The rest comes from blast radius. The formula is right here."

**[1:05–1:30] — Pipeline Gates**
- Show "Why Does the Pipeline Pause?" section
- "This PR scored 45 and paused because the production threshold was 40. The developer can see exactly why and adjust it."
- Show the "Want it more autonomous?" section — "One click in settings and the threshold goes to 60."
- Show the decision flow Mermaid diagram

**[1:30–1:50] — PR Detail Audit Trail**
- Go back to a PR detail page
- Show Score Composition accordion — expand a factor to see agent reasoning
- Show Merge-Readiness Pack — version tracking, audit trail
- Show Pipeline Timeline — duration bars showing how long each agent took
- "Every decision has a full audit trail. You can drill into any agent's reasoning."

**[1:50–2:00] — Close**
> "Responsible AI means showing your work. DriftWatch doesn't just decide — it explains, tracks accuracy, and lets humans override. That's agentic DevOps done right."

---

## SCRIPT 4: "The Developer Experience" — Interactive Features

**Audience:** Judges evaluating UX and developer productivity.
**Duration:** 2 minutes
**Theme:** Show the interactive features that make DriftWatch a daily tool, not just a dashboard.

### Script

**[0:00–0:15] — Hook**
> "DriftWatch isn't just a risk dashboard — it's a code review copilot with AI chat, text-to-speech, and a built-in code editor."

**[0:15–0:40] — Impact Chat**
- Open a PR detail page, scroll to the Impact Chat sidebar
- Type a question: "What's the riskiest file in this PR?"
- Show the AI response with file cards, risk scores, and dependency chains
- Click "Explain this file" quick action — show AI summary
- "Every file card has one-click actions: explain, dependencies, impact, and view code."

**[0:40–1:05] — Full-Screen Code Modal**
- Click "View code" on a file
- Show the Catppuccin-themed code viewer with line numbers
- Click "Dock" — show the modal docking to the LEFT while chat stays visible on the right
- Click "Split" — show side-by-side file comparison
- "Developers can compare the diff with the full source code, or any two files, right in the browser."

**[1:05–1:25] — Text-to-Speech**
- Scroll to a card section
- Click the speaker icon — play the TTS narration
- "Every section has Azure Speech narration. During code review, you can listen while you read."
- Show a chat message speaker icon — "Even chat responses are speakable."

**[1:25–1:45] — Blast Map + Review Checklist**
- Click "Blast Map" tab — show the animated concentric visualization
- Hover over nodes — show impact details
- Scroll to "What to Review" section — expand to show prioritized file checklist
- "The review checklist sorts files by risk — highest first. Each one has a summary, reasoning, and direct links to GitHub."

**[1:45–2:00] — Close**
> "DriftWatch turns code review from a chore into a conversation. AI chat, code inspection, text-to-speech, and beautiful visualizations — all in one place. This is what agentic DevOps looks like for developers."

---

## General Tips for All Scripts

1. **Practice the clicks** — know exactly which PR to open, which files to show
2. **Use PR #589** (or whichever has the richest data) as your demo PR
3. **Keep the browser zoomed to 90%** so judges can see more content
4. **Have Azure Speech configured** so TTS works live (or note it needs the key)
5. **If agents are in mock mode**, that's fine — mention "with live Azure Functions, these scores come from GPT-4.1-mini"
6. **End every script with a strong closing line** — make it memorable
7. **Screen record a backup** in case of live demo issues
