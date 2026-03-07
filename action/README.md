# DriftWatch GitHub Action

Integrate DriftWatch pre-deployment risk analysis directly into your CI/CD pipeline.

## Two Modes

### Quick Start: Lightweight Mode (5 minutes, zero infrastructure)

Runs entirely inside the GitHub Actions runner using Azure OpenAI. No DriftWatch backend required.

```yaml
name: DriftWatch Risk Analysis
on:
  pull_request:
    types: [opened, synchronize, reopened]

permissions:
  checks: write
  pull-requests: read
  contents: read

jobs:
  risk-analysis:
    runs-on: ubuntu-latest
    steps:
      - name: DriftWatch Analyze
        uses: your-org/driftwatch/action@v1
        with:
          mode: lightweight
          azure-openai-endpoint: ${{ secrets.AZURE_OPENAI_ENDPOINT }}
          azure-openai-api-key: ${{ secrets.AZURE_OPENAI_API_KEY }}
          risk-threshold: 70
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

### Full Platform Mode (enterprise features)

Connects to a deployed DriftWatch backend for full multi-agent analysis with incident history, deployment weather, and team pattern learning.

```yaml
name: DriftWatch Risk Analysis
on:
  pull_request:
    types: [opened, synchronize, reopened]

jobs:
  risk-analysis:
    runs-on: ubuntu-latest
    steps:
      - name: DriftWatch Analyze
        uses: your-org/driftwatch/action@v1
        with:
          mode: full
          driftwatch-url: ${{ secrets.DRIFTWATCH_URL }}
          api-token: ${{ secrets.DRIFTWATCH_API_TOKEN }}
          risk-threshold: 70
          block-on-critical: true
```

## Inputs

| Input | Required | Default | Description |
|-------|----------|---------|-------------|
| `mode` | No | `full` | `full` or `lightweight` |
| `driftwatch-url` | Full mode | - | DriftWatch API base URL |
| `api-token` | Full mode | - | DriftWatch API token |
| `risk-threshold` | No | `70` | Score above which the action fails |
| `block-on-critical` | No | `true` | Fail the check when threshold exceeded |
| `azure-openai-endpoint` | Lightweight | - | Azure OpenAI endpoint URL |
| `azure-openai-api-key` | Lightweight | - | Azure OpenAI API key |
| `azure-openai-deployment` | No | `gpt-4.1-mini` | Azure OpenAI model deployment |

## Outputs

| Output | Description |
|--------|-------------|
| `risk-score` | Computed risk score (0-100) |
| `risk-level` | Classification: minimal, low, medium, high, critical |
| `decision` | Deployment decision: approved, pending_review, blocked |
| `summary` | Human-readable risk summary |

## How It Works

**Lightweight mode** fetches the PR diff via GitHub API, classifies each file change using DriftWatch's risk scoring rubric, reads high-risk file contents, sends everything to Azure OpenAI for analysis, and creates a native GitHub Check with the results.

**Full mode** triggers the complete 4-agent pipeline (Archaeologist, Historian, Negotiator, Chronicler) on your DriftWatch backend, including incident history matching, deployment weather scoring, and team pattern analysis.
