# DriftWatch — Azure AI Foundry Setup Guide

Step-by-step instructions for configuring Azure AI Foundry, Application Insights, Content Safety, and Model Router for DriftWatch.

---

## Prerequisites

| Requirement | Details |
|-------------|---------|
| Azure subscription | With Azure OpenAI access approved |
| Azure CLI | Logged in (`az login`) |
| Python 3.10+ | For running registration script |
| DriftWatch agents deployed | Azure Functions or local dev |

---

## Step 1: Create an Azure AI Foundry Hub + Project

### In the Azure Portal:

1. Go to **portal.azure.com** → search **"Azure AI Foundry"**
2. Click **"+ Create"** → **"Hub"**
3. Fill in:
   - **Name:** `driftwatch-hub`
   - **Resource Group:** Your existing RG (or create `driftwatch-rg`)
   - **Region:** East US (same as your OpenAI resource)
4. Click **Review + Create** → **Create**
5. Once created, go into the hub → click **"+ New Project"**
6. **Project Name:** `driftwatch-agents`
7. Note the **Project Endpoint** — it looks like:
   ```
   https://driftwatch-agents.services.ai.azure.com
   ```

### Set the environment variable:

```bash
# Add to your Azure Functions app settings or local.settings.json
FOUNDRY_ENDPOINT=https://driftwatch-agents.services.ai.azure.com
```

---

## Step 2: Deploy Models in Foundry

### Deploy GPT-4.1-mini (primary model):

1. In your Foundry project → **Models + endpoints** → **+ Deploy model**
2. Select **gpt-4.1-mini** from the Azure OpenAI catalog
3. Deployment name: `gpt-4.1-mini` (must match `AZURE_OPENAI_DEPLOYMENT` env var)
4. Set rate limit to at least **30K TPM** (tokens per minute)
5. Click **Deploy**

### Deploy GPT-4.1 (complex model — for Model Router):

1. Same flow → **+ Deploy model**
2. Select **gpt-4.1**
3. Deployment name: `gpt-4.1` (must match `AZURE_OPENAI_COMPLEX_DEPLOYMENT` env var)
4. Set rate limit to at least **10K TPM**
5. Click **Deploy**

### Set environment variables:

```bash
AZURE_OPENAI_DEPLOYMENT=gpt-4.1-mini
AZURE_OPENAI_COMPLEX_DEPLOYMENT=gpt-4.1
MODEL_ROUTER_ENABLED=true
```

---

## Step 3: Configure DriftWatch-Safety RAI Policy

### In Azure AI Foundry portal:

1. Go to your project → **Safety + security** → **Content filters**
2. Click **+ Create content filter**
3. Name: `DriftWatch-Safety`
4. Configure severity thresholds:

| Category | Input Filter | Output Filter | Action |
|----------|-------------|---------------|--------|
| Hate | Severity ≥ 4 | Severity ≥ 4 | Block |
| Violence | Severity ≥ 4 | Severity ≥ 4 | Block |
| Self-Harm | Severity ≥ 4 | Severity ≥ 4 | Block |
| Sexual | Severity ≥ 4 | Severity ≥ 4 | Block |

5. **Important:** Leave jailbreak detection **enabled** but set to "annotate" not "block" — DriftWatch sends code diffs which can sometimes trigger false positives
6. Click **Create**
7. Go to each model deployment → **Content filter** → assign `DriftWatch-Safety`

> **Why these thresholds?** Severity 4+ blocks clearly harmful content while allowing technical security terms like "SQL injection", "vulnerability", and "exploit" that are normal in DevOps risk analysis.

---

## Step 4: Set Up Azure AI Content Safety (Optional, Additional Layer)

This is a **separate** content safety resource that runs in addition to Foundry's built-in RAI policy. It provides an extra layer of output filtering.

### In Azure Portal:

1. Search **"Content Safety"** → **+ Create**
2. **Name:** `driftwatch-safety`
3. **Region:** Same as your Foundry hub
4. **Pricing tier:** Free (F0) for testing, Standard (S0) for production
5. Click **Create**

### Get the credentials:

1. Go to your Content Safety resource → **Keys and Endpoint**
2. Copy **Endpoint** and **Key 1**

### Set environment variables:

```bash
AZURE_CONTENT_SAFETY_ENDPOINT=https://driftwatch-safety.cognitiveservices.azure.com
AZURE_CONTENT_SAFETY_KEY=<your-key>
```

---

## Step 5: Set Up Application Insights + OpenTelemetry

### Create Application Insights:

1. Azure Portal → **Application Insights** → **+ Create**
2. **Name:** `driftwatch-insights`
3. **Resource Group:** Same as above
4. **Region:** Same as above
5. **Log Analytics Workspace:** Create new or use existing
6. Click **Create**

### Get the connection string:

1. Go to your App Insights resource → **Overview** → **Connection String**
2. Copy the full connection string (starts with `InstrumentationKey=...`)

### Set environment variable:

```bash
APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=abc123...;IngestionEndpoint=https://eastus-...
```

### Verify OpenTelemetry in Foundry:

1. Go to AI Foundry portal → your project → **Operate** tab
2. You should see **gen_ai.*** spans appearing after agent calls
3. These show: model used, token counts, latency, agent name, success/error status

---

## Step 6: Register Agents in Foundry

### Option A: Automated (recommended)

```bash
cd agents/
pip install azure-ai-projects azure-identity

# Set your Foundry endpoint
export FOUNDRY_ENDPOINT=https://driftwatch-agents.services.ai.azure.com

# Run registration script
python register_agents.py
```

This registers all 6 agents:
- DriftWatch-Archaeologist
- DriftWatch-Historian
- DriftWatch-Negotiator
- DriftWatch-Chronicler
- DriftWatch-Navigator
- DriftWatch-SREAgent

### Option B: Manual (via portal)

1. Go to AI Foundry → your project → **Agents** tab
2. Click **+ Create agent** for each:

| Agent Name | Model | Description |
|------------|-------|-------------|
| DriftWatch-Archaeologist | gpt-4.1-mini | Blast Radius Mapper — analyzes PR diffs |
| DriftWatch-Historian | gpt-4.1-mini | Risk Score Calculator — correlates with incidents |
| DriftWatch-Negotiator | gpt-4.1-mini | Deploy Gatekeeper — approve/block/review |
| DriftWatch-Chronicler | gpt-4.1-mini | Feedback Loop — learning agent |
| DriftWatch-Navigator | gpt-4.1-mini | Impact Chat — interactive exploration |
| DriftWatch-SREAgent | gpt-4.1-mini | SRE Agent — incident auto-response |

3. Copy the system prompt from `agents/function_app.py` (the `*_SYSTEM` constants) into each agent's **Instructions** field
4. Assign `DriftWatch-Safety` content filter to each agent

---

## Step 7: Configure GitHub Integration

### GitHub Token (for Copilot Agent Mode + MCP tools):

1. Go to GitHub → **Settings** → **Developer settings** → **Personal access tokens** → **Tokens (classic)**
2. Generate new token with scopes:
   - `repo` (full repository access)
   - `write:discussion` (for PR comments)
3. Set environment variable:

```bash
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxx
```

### Enable Copilot Agent Mode (optional):

1. In your GitHub repo → **Settings** → **Copilot** → **Policies**
2. Enable **"Copilot in PR reviews"**
3. When DriftWatch creates issues with the `copilot-agent` label, Copilot will pick them up

---

## Step 8: Deploy Azure Functions

### Install dependencies:

```bash
cd agents/
pip install -r requirements.txt
```

### Local testing:

```bash
func start
```

### Deploy to Azure:

```bash
func azure functionapp publish driftwatch-agents
```

### Set all environment variables in Azure Functions:

```bash
az functionapp config appsettings set \
  --name driftwatch-agents \
  --resource-group driftwatch-rg \
  --settings \
    AZURE_OPENAI_ENDPOINT="https://eastus.api.cognitive.microsoft.com/" \
    AZURE_OPENAI_API_KEY="<key>" \
    AZURE_OPENAI_DEPLOYMENT="gpt-4.1-mini" \
    AZURE_OPENAI_COMPLEX_DEPLOYMENT="gpt-4.1" \
    FOUNDRY_ENDPOINT="https://driftwatch-agents.services.ai.azure.com" \
    MODEL_ROUTER_ENABLED="true" \
    GITHUB_TOKEN="ghp_xxx" \
    LARAVEL_APP_URL="https://driftwatch.azurewebsites.net" \
    APPLICATIONINSIGHTS_CONNECTION_STRING="InstrumentationKey=..." \
    AZURE_CONTENT_SAFETY_ENDPOINT="https://driftwatch-safety.cognitiveservices.azure.com" \
    AZURE_CONTENT_SAFETY_KEY="<key>"
```

---

## Step 9: Set Up Azure AI Language (Key Phrase Extraction)

### In Azure Portal:

1. Search **"Language"** → click **"Language service"** → **+ Create**
2. **Name:** `driftwatch-language`
3. **Region:** Same as your other resources
4. **Pricing tier:** Free (F0) for testing, Standard (S) for production
5. Click **Create**

### Get the credentials:

1. Go to your Language resource → **Keys and Endpoint**
2. Copy **Endpoint** and **Key 1**

### Set environment variables:

```bash
AZURE_AI_LANGUAGE_ENDPOINT=https://driftwatch-language.cognitiveservices.azure.com
AZURE_AI_LANGUAGE_KEY=<your-key>
```

**What it does:** Extracts key phrases from PR descriptions and incident reports for semantic matching. The Historian uses this to find related incidents even when exact file/service names don't match.

---

## Step 10: Set Up Azure AI Search (RAG)

### In Azure Portal:

1. Search **"AI Search"** → **+ Create**
2. **Name:** `driftwatch-search`
3. **Region:** Same as your other resources
4. **Pricing tier:** Free for testing, Basic for production
5. Click **Create**

### Create the incidents index:

1. Go to your Search resource → **Indexes** → **+ Add index**
2. **Index name:** `driftwatch-incidents`
3. Add these fields:

| Field Name | Type | Searchable | Filterable | Sortable |
|------------|------|------------|------------|----------|
| `id` | Edm.String (key) | No | Yes | No |
| `title` | Edm.String | Yes | No | No |
| `description` | Edm.String | Yes | No | No |
| `severity` | Edm.Int32 | No | Yes | Yes |
| `affected_services` | Collection(Edm.String) | Yes | Yes | No |
| `affected_files` | Collection(Edm.String) | Yes | Yes | No |
| `occurred_at` | Edm.String | No | Yes | Yes |
| `resolution` | Edm.String | Yes | No | No |

4. (Optional) Enable **Semantic configuration** for better search results:
   - Go to **Semantic configurations** → **+ Add**
   - Name: `driftwatch-semantic`
   - Title field: `title`
   - Content fields: `description`, `resolution`

### Get the credentials:

1. Go to your Search resource → **Keys**
2. Copy the **Admin key** (or create a Query key for read-only)

### Set environment variables:

```bash
AZURE_AI_SEARCH_ENDPOINT=https://driftwatch-search.search.windows.net
AZURE_AI_SEARCH_KEY=<your-admin-key>
AZURE_AI_SEARCH_INDEX=driftwatch-incidents
AZURE_AI_SEARCH_SEMANTIC_CONFIG=driftwatch-semantic    # optional, for semantic ranking
```

**What it does:** RAG (Retrieval-Augmented Generation) — the Historian searches this index to find semantically similar historical incidents, even when file names and service names are different. The Chronicler indexes new outcomes here so the knowledge base grows over time.

---

## Step 11: Set Up Azure Blob Storage (MRP Artifacts)

### In Azure Portal:

1. Search **"Storage accounts"** → **+ Create**
2. **Name:** `driftwatchstorage` (must be globally unique, no hyphens)
3. **Region:** Same as your other resources
4. **Performance:** Standard
5. **Redundancy:** LRS (locally redundant) is fine for hackathon
6. Click **Create**

### Get the connection string:

1. Go to your Storage account → **Access keys**
2. Copy the **Connection string** for Key 1

### Set environment variables:

```bash
AZURE_BLOB_CONNECTION_STRING=DefaultEndpointsProtocol=https;AccountName=driftwatchstorage;AccountKey=...
AZURE_BLOB_CONTAINER=driftwatch-mrp
```

**What it does:** Archives every agent's output as JSON files in Blob Storage, creating a complete Merge Readiness Pack (MRP) audit trail. Each PR gets its own folder with timestamped artifacts from every agent.

---

## Complete Environment Variables Reference

| Variable | Required | Description | Example |
|----------|----------|-------------|---------|
| `AZURE_OPENAI_ENDPOINT` | Yes | Azure OpenAI endpoint | `https://eastus.api.cognitive.microsoft.com/` |
| `AZURE_OPENAI_API_KEY` | Yes | Azure OpenAI API key | `abc123...` |
| `AZURE_OPENAI_DEPLOYMENT` | Yes | Primary model deployment name | `gpt-4.1-mini` |
| `AZURE_OPENAI_COMPLEX_DEPLOYMENT` | No | Complex model for Model Router | `gpt-4.1` |
| `FOUNDRY_ENDPOINT` | No* | Azure AI Foundry project endpoint | `https://project.services.ai.azure.com` |
| `MODEL_ROUTER_ENABLED` | No | Enable intelligent model routing | `true` |
| `GITHUB_TOKEN` | Yes | GitHub PAT for API access + Copilot | `ghp_xxx` |
| `LARAVEL_APP_URL` | No | Laravel app URL for DB queries | `http://localhost:8000` |
| `APPLICATIONINSIGHTS_CONNECTION_STRING` | No* | App Insights for OTel export | `InstrumentationKey=...` |
| `AZURE_CONTENT_SAFETY_ENDPOINT` | No | Content Safety resource endpoint | `https://name.cognitiveservices.azure.com` |
| `AZURE_CONTENT_SAFETY_KEY` | No | Content Safety API key | `abc123...` |
| `AZURE_AI_LANGUAGE_ENDPOINT` | No* | AI Language endpoint for key phrase extraction | `https://name.cognitiveservices.azure.com` |
| `AZURE_AI_LANGUAGE_KEY` | No* | AI Language API key | `abc123...` |
| `AZURE_AI_SEARCH_ENDPOINT` | No* | AI Search endpoint for RAG | `https://name.search.windows.net` |
| `AZURE_AI_SEARCH_KEY` | No* | AI Search admin key | `abc123...` |
| `AZURE_AI_SEARCH_INDEX` | No | AI Search index name | `driftwatch-incidents` |
| `AZURE_AI_SEARCH_SEMANTIC_CONFIG` | No | Semantic configuration name | `driftwatch-semantic` |
| `AZURE_BLOB_CONNECTION_STRING` | No* | Blob Storage connection string | `DefaultEndpointsProtocol=https;...` |
| `AZURE_BLOB_CONTAINER` | No | Blob container name | `driftwatch-mrp` |

\* Strongly recommended for hackathon demo — these are hero technologies the judges evaluate.

---

## Verification Checklist

After setup, verify everything works:

```bash
# 1. Check agent health endpoint
curl https://your-functions-url/api/health | python -m json.tool

# 2. Verify hero technologies in response:
#    - foundry_agent_service.status = "active"
#    - semantic_kernel.status = "active"
#    - model_router.status = "active"
#    - opentelemetry.status = "active"
#    - content_safety.status = "active"
#    - copilot_integration.status = "active"
#    - ai_language.status = "active"
#    - rag_search.status = "active"
#    - blob_storage.status = "active"
#    - security_agent.status = "active"

# 3. Check all 7 agents listed (including security_agent)

# 4. Test an agent call
curl -X POST https://your-functions-url/api/archaeologist \
  -H "Content-Type: application/json" \
  -d '{"repo_full_name": "your/repo", "pr_number": 1}'

# 5. Test Security Agent mode
curl -X POST https://your-functions-url/api/navigator \
  -H "Content-Type: application/json" \
  -d '{"query": "are there any security vulnerabilities?", "mode": "security", "pr_context": {}}'
```

### In Azure Portal:

- [ ] AI Foundry → Agents tab shows 6 registered agents
- [ ] AI Foundry → Operate tab shows gen_ai.* spans after a test call
- [ ] App Insights → Transaction search shows DriftWatch traces
- [ ] Content Safety → Activity log shows text analysis calls
- [ ] AI Language → Activity log shows key phrase extraction calls
- [ ] AI Search → Index shows documents after Chronicler runs
- [ ] Blob Storage → Container shows MRP artifacts after pipeline runs
- [ ] Azure Functions → Monitor shows successful function executions

---

## Troubleshooting

### "azure-ai-projects not installed"

```bash
pip install azure-ai-projects azure-identity
```

### "DefaultAzureCredential failed"

Make sure you're logged into Azure CLI:
```bash
az login
az account set --subscription <your-subscription-id>
```

### "OTel spans not appearing in Foundry Operate tab"

1. Verify `APPLICATIONINSIGHTS_CONNECTION_STRING` is set
2. Check the App Insights resource is linked to the Foundry project
3. Spans may take 2-5 minutes to appear

### "Content Safety blocking technical terms"

The DriftWatch-Safety policy is configured to allow severity < 4. If legitimate security terms are being blocked, adjust the severity threshold in the Content Safety content filter settings.

### "Model Router always using primary model"

Check that `MODEL_ROUTER_ENABLED=true` and `AZURE_OPENAI_COMPLEX_DEPLOYMENT` is set. The router only upgrades for Archaeologist and Historian agents when complexity thresholds are met (15+ files, 20k+ diff chars, or 10+ correlated incidents).
