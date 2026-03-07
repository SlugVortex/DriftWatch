# DriftWatch — System Architecture

## High-Level Architecture

```mermaid
flowchart TD
    subgraph GitHub["GitHub"]
        PR["Pull Request"]
        WH["Webhook Event"]
        CMT["PR Comment"]
    end

    subgraph Azure["Microsoft Azure Cloud"]
        subgraph AppService["Azure App Service"]
            LARAVEL["Laravel 11.x<br/>Web Dashboard"]
            ORCH["Agent Orchestrator<br/>(Semantic Kernel Pattern)"]
        end

        subgraph Functions["Azure Functions V2 (Python)"]
            ARCH["🔍 Archaeologist<br/>Blast Radius Mapper"]
            HIST["📊 Historian<br/>Risk Calculator"]
            NEG["⚖️ Negotiator<br/>Deploy Gatekeeper"]
            CHRON["📝 Chronicler<br/>Feedback Recorder"]
        end

        subgraph AI["Azure AI Services"]
            AOAI["Azure OpenAI<br/>GPT-4.1-mini"]
            SAFETY["Azure AI<br/>Content Safety"]
            FOUNDRY["Azure AI Foundry<br/>Model Management"]
        end

        subgraph Data["Azure Data"]
            MYSQL["Azure Database<br/>for MySQL<br/>Flexible Server"]
            KV["Azure Key Vault<br/>Secrets Management"]
        end

        subgraph Observability["Azure Observability"]
            AI_INSIGHTS["Application Insights<br/>Telemetry"]
            MONITOR["Azure Monitor<br/>Alerts & Diagnostics"]
            LOG["Log Analytics<br/>Workspace"]
        end

        SB["Azure Service Bus<br/>Agent Message Queue"]
    end

    %% Flow
    PR -->|"PR opened/updated"| WH
    WH -->|"HMAC-SHA256<br/>verified"| LARAVEL
    LARAVEL --> ORCH
    ORCH -->|"1. Analyze blast radius"| ARCH
    ARCH -->|"Calls GPT-4.1-mini"| AOAI
    ARCH -->|"Returns affected<br/>files, services, endpoints"| ORCH
    ORCH -->|"2. Calculate risk"| HIST
    HIST -->|"Calls GPT-4.1-mini"| AOAI
    HIST -->|"Queries incidents"| MYSQL
    HIST -->|"Returns risk score<br/>0-100"| ORCH
    ORCH -->|"3. Make decision"| NEG
    NEG -->|"Calls GPT-4.1-mini"| AOAI
    NEG -->|"approve/block/review"| ORCH
    NEG -->|"Posts comment"| CMT
    ORCH -->|"4. Record outcome"| CHRON
    CHRON -->|"Calls GPT-4.1-mini"| AOAI

    %% Safety
    ARCH -.->|"Output filtered"| SAFETY
    NEG -.->|"Comment filtered"| SAFETY

    %% Storage
    ORCH -->|"Persist results"| MYSQL

    %% Secrets
    LARAVEL -.->|"API keys"| KV
    Functions -.->|"Connection strings"| KV

    %% Observability
    LARAVEL -.->|"Traces"| AI_INSIGHTS
    Functions -.->|"Spans"| AI_INSIGHTS
    AI_INSIGHTS --> MONITOR
    MONITOR --> LOG

    %% Model Management
    FOUNDRY -.->|"Model deployment"| AOAI

    %% Async
    SB -.->|"Agent queue<br/>(async pattern)"| Functions

    %% Styles
    style PR fill:#24292e,color:#fff
    style WH fill:#24292e,color:#fff
    style CMT fill:#24292e,color:#fff
    style LARAVEL fill:#FF2D20,color:#fff
    style ORCH fill:#605DFF,color:#fff
    style ARCH fill:#0d6efd,color:#fff
    style HIST fill:#fd7e14,color:#fff
    style NEG fill:#dc3545,color:#fff
    style CHRON fill:#198754,color:#fff
    style AOAI fill:#0078D4,color:#fff
    style SAFETY fill:#0078D4,color:#fff
    style FOUNDRY fill:#0078D4,color:#fff
    style MYSQL fill:#0078D4,color:#fff
    style KV fill:#0078D4,color:#fff
    style AI_INSIGHTS fill:#68217A,color:#fff
    style MONITOR fill:#68217A,color:#fff
    style LOG fill:#68217A,color:#fff
    style SB fill:#0078D4,color:#fff
```

## Agent Pipeline Flow

```mermaid
sequenceDiagram
    participant GH as GitHub
    participant LV as Laravel App
    participant SK as Orchestrator
    participant A1 as Archaeologist
    participant A2 as Historian
    participant A3 as Negotiator
    participant A4 as Chronicler
    participant AI as Azure OpenAI
    participant DB as Azure MySQL
    participant CS as Content Safety

    GH->>LV: Webhook (PR opened)
    LV->>LV: Verify HMAC signature
    LV->>SK: Trigger pipeline

    SK->>A1: Analyze blast radius
    A1->>GH: Fetch PR files & diff
    A1->>AI: GPT-4.1-mini analysis
    AI-->>A1: Structured JSON response
    A1-->>SK: affected_files, services, endpoints

    SK->>A2: Calculate risk score
    A2->>DB: Query 90-day incidents
    A2->>AI: GPT-4.1-mini correlation
    AI-->>A2: risk_score, factors
    A2-->>SK: risk_score (0-100), risk_level

    SK->>A3: Make deploy decision
    A3->>AI: GPT-4.1-mini decision
    AI-->>A3: decision, rationale
    A3->>CS: Filter comment output
    CS-->>A3: Approved content
    A3->>GH: Post PR comment
    A3-->>SK: approved/blocked/review

    SK->>DB: Persist all results

    Note over SK,A4: Post-deployment (async)
    SK->>A4: Record outcome
    A4->>AI: Analyze prediction accuracy
    A4-->>SK: prediction_accurate, notes
    SK->>DB: Update outcome
```

## Azure Services Used (10)

| # | Service | Purpose | Status |
|---|---------|---------|--------|
| 1 | **Azure OpenAI** | GPT-4.1-mini powers all 4 agents | Active |
| 2 | **Azure Functions V2** | Serverless Python agent hosting | Active |
| 3 | **Azure Database for MySQL** | Flexible Server for all data | Active |
| 4 | **Application Insights** | Telemetry, traces, and monitoring | Active |
| 5 | **Azure AI Content Safety** | Filter agent inputs/outputs | Configured |
| 6 | **Azure Key Vault** | Secrets and key management | Configured |
| 7 | **Semantic Kernel** | Agent orchestration pattern (Planner → Skills → Memory) | Integrated |
| 8 | **Azure AI Foundry** | Model deployment and management | Configured |
| 9 | **Azure Monitor** | Alerts, diagnostics, Log Analytics | Active |
| 10 | **Azure Service Bus** | Async agent message queue pattern | Configured |

## Tech Stack

- **Backend**: Laravel 11.x (PHP 8.3+) on Azure App Service
- **Frontend**: Trezo Admin Template (Bootstrap 5, Material Symbols, ApexCharts)
- **AI Agents**: Python Azure Functions V2 with Azure OpenAI
- **Visualization**: vis.js (network graphs), Mermaid.js (structural diagrams), ApexCharts
- **Database**: Azure Database for MySQL Flexible Server
- **Observability**: Application Insights + Azure Monitor + OpenTelemetry
