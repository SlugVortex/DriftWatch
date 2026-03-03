# Azure Setup Instructions (For Friend with Subscription)

## What You Need To Do

DriftWatch has 4 AI agents written as Python Azure Functions. You need to:
1. Create an Azure OpenAI resource and deploy a model
2. Create an Azure Functions app and deploy the agents
3. Give back the endpoint URLs and keys

---

## Step 1: Login & Resource Group

```bash
az login
az group create --name rg-driftwatch --location eastus
```

---

## Step 2: Azure OpenAI Resource + Model Deployment

```bash
# Create Azure OpenAI resource
az cognitiveservices account create \
  --name driftwatch-ai \
  --resource-group rg-driftwatch \
  --kind OpenAI \
  --sku S0 \
  --location eastus

# Deploy gpt-4.1-mini model (cheapest good model for hackathon)
az cognitiveservices account deployment create \
  --name driftwatch-ai \
  --resource-group rg-driftwatch \
  --deployment-name gpt-4.1-mini \
  --model-name gpt-4.1-mini \
  --model-format OpenAI \
  --sku-capacity 120 \
  --sku-name GlobalStandard
```

### Get the endpoint and key:
```bash
# Get endpoint
az cognitiveservices account show \
  --name driftwatch-ai \
  --resource-group rg-driftwatch \
  --query "properties.endpoint" -o tsv

# Get key
az cognitiveservices account keys list \
  --name driftwatch-ai \
  --resource-group rg-driftwatch \
  --query "key1" -o tsv
```

**Write these down — you'll need them in Step 4.**

---

## Step 3: Create Azure Functions App

```bash
# Create storage account (required by Functions)
az storage account create \
  --name stdriftwatch \
  --resource-group rg-driftwatch \
  --sku Standard_LRS

# Create the Functions app (Python 3.11)
az functionapp create \
  --name driftwatch-agents \
  --resource-group rg-driftwatch \
  --runtime python \
  --runtime-version 3.11 \
  --functions-version 4 \
  --storage-account stdriftwatch \
  --consumption-plan-location eastus \
  --os-type Linux
```

---

## Step 4: Set Environment Variables

Replace the placeholder values with your actual keys:

```bash
az functionapp config appsettings set \
  --name driftwatch-agents \
  --resource-group rg-driftwatch \
  --settings \
    AZURE_OPENAI_ENDPOINT="https://driftwatch-ai.openai.azure.com/" \
    AZURE_OPENAI_API_KEY="YOUR-KEY-FROM-STEP-2" \
    AZURE_OPENAI_DEPLOYMENT="gpt-4.1-mini" \
    GITHUB_TOKEN="YOUR-GITHUB-PAT" \
    LARAVEL_APP_URL="https://YOUR-LARAVEL-APP-URL"
```

The GITHUB_TOKEN needs these scopes: `repo`, `write:discussion` (for posting PR comments).
The LARAVEL_APP_URL is wherever the Laravel dashboard is hosted.

---

## Step 5: Deploy the Agents

From the project root:

```bash
cd agents
func azure functionapp publish driftwatch-agents
```

If you don't have Azure Functions Core Tools installed:
- **Windows:** `winget install Microsoft.Azure.FunctionsCoreTools`
- **macOS:** `brew install azure-functions-core-tools@4`
- **Linux:** See https://learn.microsoft.com/en-us/azure/azure-functions/functions-run-local

---

## Step 6: Verify

```bash
# Check health endpoint
curl https://driftwatch-agents.azurewebsites.net/api/health

# Test archaeologist (should return JSON even without a real PR)
curl -X POST https://driftwatch-agents.azurewebsites.net/api/archaeologist \
  -H "Content-Type: application/json" \
  -d '{"repo_full_name":"test/repo","pr_number":1,"base_branch":"main","head_branch":"feature"}'
```

---

## Step 7: Give Back These Values

Send these back so we can update the Laravel .env:

1. **AZURE_OPENAI_ENDPOINT** = `https://driftwatch-ai.openai.azure.com/`
2. **AZURE_OPENAI_API_KEY** = (the key from Step 2)
3. **Agent base URL** = `https://driftwatch-agents.azurewebsites.net/api`

The Laravel .env entries will be:
```
AGENT_ARCHAEOLOGIST_URL=https://driftwatch-agents.azurewebsites.net/api/archaeologist
AGENT_HISTORIAN_URL=https://driftwatch-agents.azurewebsites.net/api/historian
AGENT_NEGOTIATOR_URL=https://driftwatch-agents.azurewebsites.net/api/negotiator
AGENT_CHRONICLER_URL=https://driftwatch-agents.azurewebsites.net/api/chronicler
```

---

## Optional: Application Insights (for demo points)

```bash
az monitor app-insights component create \
  --app driftwatch-insights \
  --location eastus \
  --resource-group rg-driftwatch

# Get the instrumentation key
az monitor app-insights component show \
  --app driftwatch-insights \
  --resource-group rg-driftwatch \
  --query "instrumentationKey" -o tsv

# Link to Functions app
az functionapp config appsettings set \
  --name driftwatch-agents \
  --resource-group rg-driftwatch \
  --settings APPINSIGHTS_INSTRUMENTATIONKEY="YOUR-KEY"
```

---

## Estimated Cost

- Azure OpenAI (GPT-4.1-mini): ~$0.40/1M input tokens, ~$1.60/1M output tokens
- Azure Functions (Consumption): Free tier covers ~1M executions/month
- Storage Account: < $1/month
- App Insights: Free tier covers 5GB/month

**Total for hackathon demo: < $5**

---

## Troubleshooting

If `func azure functionapp publish` fails:
- Make sure you're in the `agents/` directory
- Make sure `requirements.txt` exists
- Try `func azure functionapp publish driftwatch-agents --build remote`

If agents return errors:
- Check logs: `func azure functionapp logstream driftwatch-agents`
- Verify env vars: `az functionapp config appsettings list --name driftwatch-agents --resource-group rg-driftwatch`
