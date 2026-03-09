# DriftWatch — Security Agent Setup Guide

This guide helps you set up the Security Agent with Azure AI Search (RAG) for vulnerability-aware PR analysis.

## Overview

The Security Agent is the 5th agent in the DriftWatch pipeline. It uses Retrieval Augmented Generation (RAG) to check PR code changes against a knowledge base of security vulnerabilities (OWASP Top 10, CVEs, CWEs).

```
PR Code Changes → Security Agent → Azure AI Search (RAG) → Azure OpenAI → Security Report
                                        ↓
                                   Knowledge Base
                                   (OWASP, CVE, CWE)
```

## What You Need to Deploy

### 1. Azure AI Search Service

1. Go to Azure Portal → Create a resource → "Azure AI Search"
2. Choose:
   - **Name**: `driftwatch-search` (or your preference)
   - **Pricing tier**: Free (F) works for dev, Basic (B) for production
   - **Region**: Same as your other Azure resources (e.g., East US)
3. After creation, grab:
   - **Endpoint**: `https://driftwatch-search.search.windows.net`
   - **Admin Key**: Settings → Keys → Primary admin key

### 2. Create the Search Index

Create an index called `security-knowledge` with this schema:

```json
{
  "name": "security-knowledge",
  "fields": [
    { "name": "id", "type": "Edm.String", "key": true, "filterable": true },
    { "name": "title", "type": "Edm.String", "searchable": true, "filterable": true },
    { "name": "category", "type": "Edm.String", "filterable": true, "facetable": true },
    { "name": "severity", "type": "Edm.String", "filterable": true },
    { "name": "description", "type": "Edm.String", "searchable": true },
    { "name": "code_patterns", "type": "Edm.String", "searchable": true },
    { "name": "remediation", "type": "Edm.String", "searchable": true },
    { "name": "references", "type": "Collection(Edm.String)" },
    { "name": "cwe_id", "type": "Edm.String", "filterable": true },
    { "name": "owasp_category", "type": "Edm.String", "filterable": true },
    { "name": "content_vector", "type": "Collection(Edm.Single)", "searchable": true, "dimensions": 1536, "vectorSearchProfile": "default-profile" }
  ],
  "vectorSearch": {
    "algorithms": [{ "name": "default-algo", "kind": "hnsw" }],
    "profiles": [{ "name": "default-profile", "algorithm": "default-algo" }]
  }
}
```

You can create this via the Azure Portal UI or using the REST API:

```bash
curl -X PUT "https://driftwatch-search.search.windows.net/indexes/security-knowledge?api-version=2024-07-01" \
  -H "Content-Type: application/json" \
  -H "api-key: YOUR_ADMIN_KEY" \
  -d @index-schema.json
```

### 3. Populate the Knowledge Base

Upload security knowledge documents. Here's an example document:

```json
{
  "value": [
    {
      "@search.action": "upload",
      "id": "owasp-a01-broken-access",
      "title": "Broken Access Control",
      "category": "OWASP",
      "severity": "Critical",
      "description": "Access control enforces policy such that users cannot act outside of their intended permissions. Failures typically lead to unauthorized information disclosure, modification, or destruction of data.",
      "code_patterns": "middleware bypass, missing auth check, role escalation, direct object reference, CORS misconfiguration, JWT validation skip, missing @auth decorator, public route to sensitive endpoint",
      "remediation": "Implement proper access control checks. Use middleware for authentication. Validate permissions on every request. Deny by default.",
      "references": ["https://owasp.org/Top10/A01_2021-Broken_Access_Control/"],
      "cwe_id": "CWE-284",
      "owasp_category": "A01:2021"
    }
  ]
}
```

Upload via REST API:

```bash
curl -X POST "https://driftwatch-search.search.windows.net/indexes/security-knowledge/docs/index?api-version=2024-07-01" \
  -H "Content-Type: application/json" \
  -H "api-key: YOUR_ADMIN_KEY" \
  -d @documents.json
```

### 4. Deploy the Security Agent Azure Function

Add a new function to the existing `agents/function_app.py`:

```python
@app.function_name(name="security")
@app.route(route="security", methods=["POST"])
async def security_agent(req: func.HttpRequest) -> func.HttpResponse:
    """Security Agent — RAG-based vulnerability detection."""
    body = req.get_json()

    # 1. Extract code changes from the request
    changed_files = body.get("changed_files", [])
    diff_text = body.get("diff_text", "")

    # 2. Query Azure AI Search for relevant security knowledge
    search_results = query_security_knowledge(diff_text, changed_files)

    # 3. Send to Azure OpenAI with RAG context
    security_report = await analyze_with_openai(
        code_changes=diff_text,
        security_context=search_results,
        system_prompt=SECURITY_SYSTEM_PROMPT
    )

    return func.HttpResponse(
        json.dumps(security_report),
        mimetype="application/json"
    )
```

### 5. Environment Variables

Once everything is deployed, give these values to put in the DriftWatch `.env` file:

```env
# Security Agent Azure Function URL
AGENT_SECURITY_URL=https://driftwatch-agents.azurewebsites.net/api/security

# Azure AI Search (RAG for Security Agent)
AZURE_AI_SEARCH_ENDPOINT=https://driftwatch-search.search.windows.net
AZURE_AI_SEARCH_KEY=your-admin-key-here
AZURE_AI_SEARCH_INDEX=security-knowledge
```

## Architecture

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  DriftWatch  │────▶│  Security Agent   │────▶│  Azure OpenAI   │
│  Laravel App │     │  (Azure Function) │     │  GPT-4.1-mini   │
└─────────────┘     └────────┬─────────┘     └─────────────────┘
                             │
                             ▼
                    ┌──────────────────┐
                    │  Azure AI Search  │
                    │  (RAG Retrieval)  │
                    └────────┬─────────┘
                             │
                             ▼
                    ┌──────────────────┐
                    │ Security Knowledge│
                    │  Base (Index)     │
                    │  - OWASP Top 10  │
                    │  - CVE Database   │
                    │  - CWE Patterns   │
                    │  - Best Practices │
                    └──────────────────┘
```

## What the Security Agent Returns

```json
{
  "security_score": 25,
  "vulnerabilities_found": [
    {
      "severity": "high",
      "type": "SQL Injection",
      "file": "app/Models/User.php",
      "line": 42,
      "description": "Raw SQL query with user input",
      "cwe": "CWE-89",
      "remediation": "Use Eloquent query builder or parameterized queries"
    }
  ],
  "owasp_categories_flagged": ["A03:2021 - Injection"],
  "summary": "Found 1 high-severity vulnerability in code changes.",
  "recommendation": "Block deployment until SQL injection risk is addressed."
}
```

## Quick Test

After setup, test the Security Agent endpoint:

```bash
curl -X POST "https://driftwatch-agents.azurewebsites.net/api/security?code=YOUR_FUNCTION_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "pr_number": 42,
    "repo": "owner/repo",
    "changed_files": ["app/Http/Controllers/UserController.php"],
    "diff_text": "- $user = User::find($id);\n+ $user = DB::select(\"SELECT * FROM users WHERE id = \" . $request->input(\"id\"));"
  }'
```

## Checklist

- [ ] Create Azure AI Search service
- [ ] Create `security-knowledge` index with vector search
- [ ] Upload OWASP/CVE/CWE knowledge documents
- [ ] Add security function to `agents/function_app.py`
- [ ] Deploy updated Azure Functions
- [ ] Set `AGENT_SECURITY_URL` in DriftWatch `.env`
- [ ] Set `AZURE_AI_SEARCH_ENDPOINT` and `AZURE_AI_SEARCH_KEY` in `.env`
- [ ] Test the endpoint with a sample PR payload
