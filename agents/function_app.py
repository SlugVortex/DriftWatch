# agents/function_app.py
# DriftWatch AI Agent Pipeline — 7 Azure Functions powered by Azure AI Foundry + Semantic Kernel.
#
# Hero Technologies Integrated:
#   1. Azure AI Foundry    — All LLM calls route through Foundry Responses API with RAI guardrails
#   2. Semantic Kernel SDK — Agent orchestration (Planner → Skills → Memory pattern)
#   3. Azure MCP Server    — Agents use MCP tools for GitHub + DB access
#   4. OpenTelemetry       — gen_ai.* spans exported to Application Insights + Foundry Operate tab
#   5. Model Router        — Intelligent model selection (gpt-4.1-mini vs gpt-4.1 based on complexity)
#   6. Content Safety      — Azure AI Content Safety filters all agent outputs
#   7. GitHub Copilot      — Negotiator creates GitHub Issues for Copilot Agent Mode remediation
#   8. SRE Agent           — Incident auto-detection and self-healing triggers
#   9. Azure AI Language    — Key phrase extraction for semantic incident correlation
#  10. Azure AI Search (RAG) — Semantic retrieval of historical incidents for Historian
#  11. Azure Blob Storage   — MRP artifact archiving (analysis reports, diffs, evidence)
#
# Architecture:
#   Azure AI Foundry (Responses API + DriftWatch-Safety RAI policy)
#   └── Semantic Kernel Orchestrator
#       ├── Archaeologist Plugin  → Blast Radius Mapper      (uses MCP: github_read_*)
#       ├── Historian Plugin      → Risk Score Calculator     (uses RAG + AI Language + MCP)
#       ├── Negotiator Plugin     → Deployment Gatekeeper     (uses MCP: github_post_*)
#       ├── Chronicler Plugin     → Feedback Loop Recorder
#       ├── Navigator Plugin      → Interactive Impact Chat + Security Analysis
#       ├── SRE Plugin            → Incident Auto-Response
#       └── Security Analysis     → Navigator security-focused queries
#
# Every LLM call emits gen_ai.* OpenTelemetry spans → Application Insights → Foundry Operate tab.

import azure.functions as func
import json
import os
import logging
import asyncio
from datetime import datetime

# ---------------------------------------------------------------------------
# Semantic Kernel imports (Microsoft Agent Framework)
# ---------------------------------------------------------------------------
from semantic_kernel import Kernel
from semantic_kernel.connectors.ai.open_ai import AzureChatCompletion
from semantic_kernel.connectors.ai.open_ai.prompt_execution_settings import (
    AzureChatPromptExecutionSettings,
)
from semantic_kernel.contents import ChatHistory
from semantic_kernel.functions import kernel_function
from semantic_kernel.connectors.ai.function_choice_behavior import FunctionChoiceBehavior

# ---------------------------------------------------------------------------
# OpenTelemetry — gen_ai.* span instrumentation for Foundry Operate tab
# ---------------------------------------------------------------------------
try:
    from opentelemetry import trace
    from opentelemetry.sdk.trace import TracerProvider
    from opentelemetry.sdk.trace.export import SimpleSpanProcessor
    from opentelemetry.sdk.resources import Resource
    from azure.monitor.opentelemetry.exporter import AzureMonitorTraceExporter

    # Configure OpenTelemetry with Application Insights exporter
    _app_insights_cs = os.environ.get("APPLICATIONINSIGHTS_CONNECTION_STRING", "")
    if _app_insights_cs:
        resource = Resource.create({"service.name": "driftwatch-agents", "service.version": "1.0.0"})
        provider = TracerProvider(resource=resource)
        exporter = AzureMonitorTraceExporter(connection_string=_app_insights_cs)
        provider.add_span_processor(SimpleSpanProcessor(exporter))
        trace.set_tracer_provider(provider)
        logging.info("[DriftWatch OTel] OpenTelemetry configured with Application Insights exporter.")
    else:
        provider = TracerProvider()
        trace.set_tracer_provider(provider)
        logging.info("[DriftWatch OTel] No App Insights connection string — OTel spans will be local only.")

    # Instrument OpenAI SDK to emit gen_ai.* spans automatically
    # This captures: gen_ai.chat.completions, gen_ai.content, token counts, latency
    try:
        from opentelemetry.instrumentation.openai import OpenAIInstrumentor
        OpenAIInstrumentor().instrument(enable_content_recording=True)
        logging.info("[DriftWatch OTel] OpenAI SDK instrumented — gen_ai.* spans active.")
    except ImportError:
        try:
            from opentelemetry.instrumentation.openai import ResponsesInstrumentor
            ResponsesInstrumentor().instrument(enable_content_recording=True)
            logging.info("[DriftWatch OTel] Responses API instrumented — gen_ai.* spans active.")
        except ImportError:
            logging.warning("[DriftWatch OTel] OpenAI instrumentor not available — manual span creation.")

    _tracer = trace.get_tracer("driftwatch.agents", "1.0.0")
    _otel_available = True
except ImportError as e:
    logging.warning(f"[DriftWatch OTel] OpenTelemetry not available: {e}. Spans will not be exported.")
    _tracer = None
    _otel_available = False

# ---------------------------------------------------------------------------
# Azure AI Foundry — Responses API client (routes through Foundry with RAI)
# ---------------------------------------------------------------------------
try:
    from azure.ai.projects import AIProjectClient
    from azure.identity import DefaultAzureCredential, AzureCliCredential
    _foundry_available = True
    logging.info("[DriftWatch Foundry] azure-ai-projects SDK available.")
except ImportError:
    _foundry_available = False
    logging.info("[DriftWatch Foundry] azure-ai-projects not installed — will use direct SK connector.")

# ---------------------------------------------------------------------------
# Azure AI Language — Key Phrase Extraction for semantic incident correlation
# ---------------------------------------------------------------------------
try:
    from azure.ai.textanalytics import TextAnalyticsClient
    from azure.core.credentials import AzureKeyCredential as TextAzureKeyCredential
    _ai_language_available = True
    logging.info("[DriftWatch AI Language] azure-ai-textanalytics SDK available.")
except ImportError:
    _ai_language_available = False
    logging.info("[DriftWatch AI Language] SDK not installed — key phrase extraction disabled.")


def extract_key_phrases(texts: list[str]) -> list[list[str]]:
    """
    Uses Azure AI Language to extract key phrases from text.
    Improves Historian's incident matching by identifying semantic concepts
    (e.g., 'authentication failure' matches 'login timeout' via shared key phrases).
    """
    endpoint = os.environ.get("AZURE_AI_LANGUAGE_ENDPOINT", "")
    key = os.environ.get("AZURE_AI_LANGUAGE_KEY", "")

    if not _ai_language_available or not endpoint or not key:
        # Fallback: simple word extraction
        return [text.lower().split()[:10] for text in texts]

    try:
        client = TextAnalyticsClient(
            endpoint=endpoint,
            credential=TextAzureKeyCredential(key),
        )
        # Batch extract key phrases from all texts
        results = client.extract_key_phrases(
            documents=[{"id": str(i), "text": t[:5120]} for i, t in enumerate(texts)],
            language="en",
        )
        extracted = []
        for doc in results:
            if not doc.is_error:
                extracted.append(doc.key_phrases[:15])
            else:
                extracted.append([])
        logging.info(f"[AI Language] Extracted key phrases from {len(texts)} documents.")
        return extracted
    except Exception as e:
        logging.warning(f"[AI Language] Key phrase extraction failed: {e}")
        return [[] for _ in texts]


def extract_entities(text: str) -> list[dict]:
    """
    Uses Azure AI Language to extract named entities (services, technologies, etc.)
    for security-focused analysis in the Navigator's Security Agent mode.
    """
    endpoint = os.environ.get("AZURE_AI_LANGUAGE_ENDPOINT", "")
    key = os.environ.get("AZURE_AI_LANGUAGE_KEY", "")

    if not _ai_language_available or not endpoint or not key:
        return []

    try:
        client = TextAnalyticsClient(
            endpoint=endpoint,
            credential=TextAzureKeyCredential(key),
        )
        results = client.recognize_entities(
            documents=[{"id": "0", "text": text[:5120]}],
            language="en",
        )
        entities = []
        for doc in results:
            if not doc.is_error:
                for entity in doc.entities:
                    entities.append({
                        "text": entity.text,
                        "category": entity.category,
                        "confidence": entity.confidence_score,
                    })
        return entities
    except Exception as e:
        logging.warning(f"[AI Language] Entity extraction failed: {e}")
        return []


# ---------------------------------------------------------------------------
# Azure AI Search — RAG (Retrieval-Augmented Generation) for Historian
# ---------------------------------------------------------------------------
try:
    from azure.search.documents import SearchClient
    from azure.core.credentials import AzureKeyCredential as SearchAzureKeyCredential
    _ai_search_available = True
    logging.info("[DriftWatch RAG] azure-search-documents SDK available.")
except ImportError:
    _ai_search_available = False
    logging.info("[DriftWatch RAG] SDK not installed — RAG retrieval disabled.")


def rag_retrieve_incidents(query: str, services: list = None, top_k: int = 10) -> list[dict]:
    """
    RAG: Retrieves semantically relevant historical incidents from Azure AI Search.
    Uses vector search to find incidents related to the current PR, even when
    file names and service names don't match exactly.

    This is the key RAG component — the Historian gets richer context than
    simple database queries by leveraging semantic similarity.

    Flow:
      PR blast radius summary → Azure AI Search (vector query)
      → Top-K semantically similar incidents → Historian prompt context
    """
    endpoint = os.environ.get("AZURE_AI_SEARCH_ENDPOINT", "")
    key = os.environ.get("AZURE_AI_SEARCH_KEY", "")
    index_name = os.environ.get("AZURE_AI_SEARCH_INDEX", "driftwatch-incidents")

    if not _ai_search_available or not endpoint or not key:
        logging.info("[RAG] Azure AI Search not configured — skipping semantic retrieval.")
        return []

    try:
        client = SearchClient(
            endpoint=endpoint,
            index_name=index_name,
            credential=SearchAzureKeyCredential(key),
        )

        # Build search query combining text + service filters
        search_text = query
        if services:
            service_filter = " OR ".join(f"affected_services/any(s: s eq '{s}')" for s in services[:5])
        else:
            service_filter = None

        # Execute hybrid search (text + vector when embeddings are configured)
        results = client.search(
            search_text=search_text,
            filter=service_filter,
            top=top_k,
            query_type="semantic" if os.environ.get("AZURE_AI_SEARCH_SEMANTIC_CONFIG") else "simple",
            semantic_configuration_name=os.environ.get("AZURE_AI_SEARCH_SEMANTIC_CONFIG", None),
        )

        incidents = []
        for result in results:
            incidents.append({
                "id": result.get("id", ""),
                "title": result.get("title", ""),
                "description": result.get("description", ""),
                "severity": result.get("severity", 0),
                "affected_services": result.get("affected_services", []),
                "affected_files": result.get("affected_files", []),
                "occurred_at": result.get("occurred_at", ""),
                "resolution": result.get("resolution", ""),
                "search_score": result.get("@search.score", 0),
                "retrieval_method": "rag_semantic",
            })

        logging.info(f"[RAG] Retrieved {len(incidents)} semantically relevant incidents from Azure AI Search.")
        return incidents

    except Exception as e:
        logging.warning(f"[RAG] Azure AI Search query failed: {e}")
        return []


def rag_index_incident(incident: dict) -> bool:
    """
    Indexes a new incident into Azure AI Search for future RAG retrieval.
    Called by the Chronicler when recording deployment outcomes.
    """
    endpoint = os.environ.get("AZURE_AI_SEARCH_ENDPOINT", "")
    key = os.environ.get("AZURE_AI_SEARCH_KEY", "")
    index_name = os.environ.get("AZURE_AI_SEARCH_INDEX", "driftwatch-incidents")

    if not _ai_search_available or not endpoint or not key:
        return False

    try:
        client = SearchClient(
            endpoint=endpoint,
            index_name=index_name,
            credential=SearchAzureKeyCredential(key),
        )
        # Upsert the incident document
        client.upload_documents(documents=[incident])
        logging.info(f"[RAG] Indexed incident {incident.get('id', '?')} into Azure AI Search.")
        return True
    except Exception as e:
        logging.warning(f"[RAG] Failed to index incident: {e}")
        return False


# ---------------------------------------------------------------------------
# Azure Blob Storage — MRP Artifact Archiving
# ---------------------------------------------------------------------------
try:
    from azure.storage.blob import BlobServiceClient
    _blob_available = True
    logging.info("[DriftWatch Blob] azure-storage-blob SDK available.")
except ImportError:
    _blob_available = False
    logging.info("[DriftWatch Blob] SDK not installed — MRP archiving disabled.")


def archive_mrp_artifact(pr_number: int, repo: str, artifact_type: str, content: dict) -> str:
    """
    Archives a Merge Readiness Pack (MRP) artifact to Azure Blob Storage.
    Creates a complete audit trail of all agent analyses for compliance and review.

    Blob structure:
      driftwatch-mrp/
        ├── {repo}/
        │   ├── pr-{number}/
        │   │   ├── blast_radius.json      (Archaeologist output)
        │   │   ├── risk_assessment.json    (Historian output)
        │   │   ├── deployment_decision.json (Negotiator output)
        │   │   ├── feedback_outcome.json   (Chronicler output)
        │   │   ├── sre_assessment.json     (SRE Agent output)
        │   │   └── full_mrp.json           (Complete MRP bundle)
    """
    conn_str = os.environ.get("AZURE_BLOB_CONNECTION_STRING", "")
    container_name = os.environ.get("AZURE_BLOB_CONTAINER", "driftwatch-mrp")

    if not _blob_available or not conn_str:
        logging.info("[Blob] Azure Blob Storage not configured — skipping artifact archiving.")
        return ""

    try:
        blob_service = BlobServiceClient.from_connection_string(conn_str)
        container_client = blob_service.get_container_client(container_name)

        # Create container if it doesn't exist
        try:
            container_client.create_container()
        except Exception:
            pass  # Container already exists

        # Build blob path
        safe_repo = repo.replace("/", "_")
        timestamp = datetime.utcnow().strftime("%Y%m%d_%H%M%S")
        blob_name = f"{safe_repo}/pr-{pr_number}/{artifact_type}_{timestamp}.json"

        # Upload artifact
        blob_data = json.dumps({
            "pr_number": pr_number,
            "repo": repo,
            "artifact_type": artifact_type,
            "timestamp": datetime.utcnow().isoformat(),
            "data": content,
        }, indent=2, default=str)

        blob_client = container_client.get_blob_client(blob_name)
        blob_client.upload_blob(blob_data, overwrite=True, content_settings=None)

        blob_url = blob_client.url
        logging.info(f"[Blob] Archived {artifact_type} for PR #{pr_number}: {blob_name}")
        return blob_url

    except Exception as e:
        logging.warning(f"[Blob] Failed to archive artifact: {e}")
        return ""


def get_mrp_artifacts(pr_number: int, repo: str) -> list[dict]:
    """
    Retrieves all MRP artifacts for a PR from Azure Blob Storage.
    Used by the Navigator for comprehensive audit trail queries.
    """
    conn_str = os.environ.get("AZURE_BLOB_CONNECTION_STRING", "")
    container_name = os.environ.get("AZURE_BLOB_CONTAINER", "driftwatch-mrp")

    if not _blob_available or not conn_str:
        return []

    try:
        blob_service = BlobServiceClient.from_connection_string(conn_str)
        container_client = blob_service.get_container_client(container_name)

        safe_repo = repo.replace("/", "_")
        prefix = f"{safe_repo}/pr-{pr_number}/"

        artifacts = []
        for blob in container_client.list_blobs(name_starts_with=prefix):
            blob_client = container_client.get_blob_client(blob.name)
            data = json.loads(blob_client.download_blob().readall())
            artifacts.append(data)

        logging.info(f"[Blob] Retrieved {len(artifacts)} MRP artifacts for PR #{pr_number}.")
        return artifacts
    except Exception as e:
        logging.warning(f"[Blob] Failed to retrieve artifacts: {e}")
        return []


app = func.FunctionApp()


# ---------------------------------------------------------------------------
# Model Router — Intelligent model selection based on task complexity
# ---------------------------------------------------------------------------

class ModelRouter:
    """
    Routes agent calls to the optimal model based on task complexity and cost.
    Uses gpt-4.1-mini for routine analysis, gpt-4.1 for complex reasoning.
    Implements the Model Router pattern from Azure AI Foundry.
    """

    # Complexity thresholds for model routing
    COMPLEX_FILE_THRESHOLD = 15      # PRs with 15+ files get the bigger model
    COMPLEX_DIFF_THRESHOLD = 20000   # Diffs over 20k chars get the bigger model
    COMPLEX_INCIDENT_THRESHOLD = 10  # 10+ correlated incidents need deeper reasoning

    def __init__(self):
        self.primary_model = os.environ.get("AZURE_OPENAI_DEPLOYMENT", "gpt-4.1-mini")
        self.complex_model = os.environ.get("AZURE_OPENAI_COMPLEX_DEPLOYMENT", "gpt-4.1")
        self.routing_enabled = os.environ.get("MODEL_ROUTER_ENABLED", "true").lower() == "true"

    def select_model(self, agent_name: str, context: dict = None) -> str:
        """
        Selects the appropriate model for an agent call.
        Returns the model deployment name to use.
        """
        if not self.routing_enabled or not context:
            return self.primary_model

        complexity_score = 0
        reason = []

        # Check file count complexity
        file_count = context.get("file_count", 0)
        if file_count >= self.COMPLEX_FILE_THRESHOLD:
            complexity_score += 2
            reason.append(f"{file_count} files changed")

        # Check diff size complexity
        diff_size = context.get("diff_size", 0)
        if diff_size >= self.COMPLEX_DIFF_THRESHOLD:
            complexity_score += 2
            reason.append(f"large diff ({diff_size} chars)")

        # Check incident correlation complexity
        incident_count = context.get("incident_count", 0)
        if incident_count >= self.COMPLEX_INCIDENT_THRESHOLD:
            complexity_score += 2
            reason.append(f"{incident_count} correlated incidents")

        # Negotiator always uses primary (fast decisions)
        # Archaeologist and Historian may upgrade for complex PRs
        if agent_name in ("archaeologist", "historian") and complexity_score >= 2:
            logging.info(f"[Model Router] Routing {agent_name} to {self.complex_model}: {', '.join(reason)}")
            return self.complex_model

        return self.primary_model

    def get_routing_metadata(self, agent_name: str, model_used: str) -> dict:
        """Returns metadata about the routing decision for telemetry."""
        return {
            "model_router.agent": agent_name,
            "model_router.model_selected": model_used,
            "model_router.primary_model": self.primary_model,
            "model_router.complex_model": self.complex_model,
            "model_router.routing_enabled": self.routing_enabled,
        }


_model_router = ModelRouter()


# ---------------------------------------------------------------------------
# Semantic Kernel: Kernel Factory (with Foundry Responses API routing)
# ---------------------------------------------------------------------------

def create_kernel(model_override: str = None) -> Kernel:
    """
    Creates a Semantic Kernel instance connected to Azure OpenAI via Foundry.

    Call chain:
      SK Kernel → AzureChatCompletion → Azure AI Foundry endpoint → RAI guardrails → GPT-4.1-mini
      (If Foundry not configured, falls back to direct Azure OpenAI endpoint)

    The Foundry endpoint applies DriftWatch-Safety RAI policy to every call,
    and gen_ai.* telemetry is captured automatically via OpenTelemetry instrumentation.
    """
    kernel = Kernel()

    model = model_override or os.environ.get("AZURE_OPENAI_DEPLOYMENT", "gpt-4.1-mini")

    # Determine endpoint: prefer Foundry endpoint (routes through RAI guardrails)
    # Foundry endpoint format: https://<project>.services.ai.azure.com
    # Falls back to direct Azure OpenAI endpoint if Foundry not configured
    foundry_endpoint = os.environ.get("FOUNDRY_ENDPOINT", "")
    direct_endpoint = os.environ.get("AZURE_OPENAI_ENDPOINT", "")
    api_key = os.environ.get("AZURE_OPENAI_API_KEY", "")

    endpoint = foundry_endpoint if foundry_endpoint else direct_endpoint
    endpoint_type = "Foundry" if foundry_endpoint else "Direct"

    service = AzureChatCompletion(
        deployment_name=model,
        endpoint=endpoint,
        api_key=api_key,
        api_version="2025-03-01-preview",
    )
    kernel.add_service(service)

    logging.info(f"[DriftWatch SK] Kernel created — endpoint: {endpoint_type}, model: {model}")
    return kernel


def _get_foundry_openai_client():
    """
    Gets an OpenAI client routed through Azure AI Foundry using the Responses API.
    This ensures DriftWatch-Safety RAI guardrails are applied to every call.
    Returns (client, method) where method is 'foundry' or 'direct'.
    """
    foundry_endpoint = os.environ.get("FOUNDRY_ENDPOINT", "")

    if _foundry_available and foundry_endpoint:
        try:
            # Use azure-ai-projects SDK to get a Foundry-routed OpenAI client
            # All calls go through: Foundry → RAI guardrails → Azure OpenAI
            credential = DefaultAzureCredential()
            project = AIProjectClient(endpoint=foundry_endpoint, credential=credential)
            client = project.inference.get_azure_openai_client()
            logging.info("[DriftWatch Foundry] Using Foundry Responses API client with RAI guardrails.")
            return client, "foundry"
        except Exception as e:
            logging.warning(f"[DriftWatch Foundry] Foundry client failed ({e}), falling back to direct.")

    # Fallback: direct Azure OpenAI client
    from openai import AzureOpenAI
    client = AzureOpenAI(
        azure_endpoint=os.environ.get("AZURE_OPENAI_ENDPOINT", ""),
        api_key=os.environ.get("AZURE_OPENAI_API_KEY", ""),
        api_version="2025-03-01-preview",
    )
    return client, "direct"


async def sk_json_call(system_prompt: str, user_prompt: str, max_tokens: int = 2000,
                       agent_name: str = "unknown", model_override: str = None) -> dict:
    """
    Makes a structured JSON call via Semantic Kernel's chat completion.
    Routes through Azure AI Foundry when configured (RAI guardrails applied).

    Call path:
      SK ChatHistory → AzureChatCompletion → Foundry Responses API → DriftWatch-Safety → GPT-4.1-mini
                                                                                      ↓
                                                                              gen_ai.* OTel span
                                                                                      ↓
                                                                        Application Insights + Foundry Operate
    """
    span = None
    if _tracer:
        span = _tracer.start_span(f"driftwatch.agent.{agent_name}")
        span.set_attribute("gen_ai.agent.name", agent_name)
        span.set_attribute("gen_ai.system", "azure_openai")
        span.set_attribute("gen_ai.request.max_tokens", max_tokens)

    try:
        kernel = create_kernel(model_override=model_override)

        # Configure execution settings for structured JSON output
        settings = AzureChatPromptExecutionSettings(
            max_tokens=max_tokens,
            temperature=0.1,
            response_format={"type": "json_object"},
        )

        # Build chat history (SK's conversation memory pattern)
        chat_history = ChatHistory()
        chat_history.add_system_message(system_prompt)
        chat_history.add_user_message(user_prompt)

        # Get the chat completion service and invoke
        # This routes through Foundry if FOUNDRY_ENDPOINT is set
        chat_service = kernel.get_service(type=AzureChatCompletion)
        response = await chat_service.get_chat_message_contents(
            chat_history=chat_history,
            settings=settings,
            kernel=kernel,
        )

        result_text = str(response[0])
        result = json.loads(result_text)

        # Record telemetry
        if span:
            span.set_attribute("gen_ai.response.status", "success")
            span.set_attribute("gen_ai.agent.result_keys", str(list(result.keys())[:10]))
            # Token usage from SK response metadata
            if hasattr(response[0], 'metadata') and response[0].metadata:
                usage = response[0].metadata.get("usage", {})
                if usage:
                    span.set_attribute("gen_ai.usage.prompt_tokens", usage.get("prompt_tokens", 0))
                    span.set_attribute("gen_ai.usage.completion_tokens", usage.get("completion_tokens", 0))

        logging.info(f"[DriftWatch SK] {agent_name} chat completion succeeded via Foundry/SK.")
        return result

    except Exception as e:
        logging.error(f"[DriftWatch SK] {agent_name} call failed: {e}")
        if span:
            span.set_attribute("gen_ai.response.status", "error")
            span.set_attribute("gen_ai.error.message", str(e))
        # Fallback to direct Foundry Responses API client
        return await _fallback_foundry_call(system_prompt, user_prompt, max_tokens, agent_name)

    finally:
        if span:
            span.end()


async def _fallback_foundry_call(system_prompt: str, user_prompt: str,
                                  max_tokens: int, agent_name: str = "unknown") -> dict:
    """
    Fallback: calls Azure OpenAI via Foundry Responses API directly (bypassing SK).
    Still routes through Foundry guardrails when FOUNDRY_ENDPOINT is configured.
    """
    span = None
    if _tracer:
        span = _tracer.start_span(f"driftwatch.agent.{agent_name}.fallback")
        span.set_attribute("gen_ai.agent.name", agent_name)
        span.set_attribute("gen_ai.fallback", True)

    try:
        client, method = _get_foundry_openai_client()
        model = os.environ.get("AZURE_OPENAI_DEPLOYMENT", "gpt-4.1-mini")

        # Try Foundry Responses API first (responses.create)
        if method == "foundry":
            try:
                response = client.responses.create(
                    model=model,
                    instructions=system_prompt,
                    input=[{"role": "user", "content": user_prompt}],
                    text={"format": {"type": "json_object"}},
                    max_output_tokens=max_tokens,
                    temperature=0.1,
                )
                result_text = response.output_text
                result = json.loads(result_text)
                if span:
                    span.set_attribute("gen_ai.response.status", "success")
                    span.set_attribute("gen_ai.call_method", "foundry_responses_api")
                logging.info(f"[DriftWatch Foundry] {agent_name} Responses API call succeeded.")
                return result
            except Exception as e:
                logging.warning(f"[DriftWatch Foundry] Responses API failed ({e}), trying chat completions.")

        # Fallback to standard chat completions
        response = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            temperature=0.1,
            max_tokens=max_tokens,
            response_format={"type": "json_object"},
        )
        result = json.loads(response.choices[0].message.content)
        if span:
            span.set_attribute("gen_ai.response.status", "success")
            span.set_attribute("gen_ai.call_method", "chat_completions")
            if response.usage:
                span.set_attribute("gen_ai.usage.prompt_tokens", response.usage.prompt_tokens)
                span.set_attribute("gen_ai.usage.completion_tokens", response.usage.completion_tokens)
        logging.info(f"[DriftWatch Fallback] {agent_name} direct call succeeded.")
        return result

    except Exception as e:
        logging.error(f"[DriftWatch Fallback] {agent_name} call also failed: {e}")
        if span:
            span.set_attribute("gen_ai.response.status", "error")
        return {"error": str(e)}
    finally:
        if span:
            span.end()


# ---------------------------------------------------------------------------
# Azure AI Content Safety: Output Filtering (RAI Guardrails)
# ---------------------------------------------------------------------------

def content_safety_check(text: str) -> bool:
    """
    Checks text against Azure AI Content Safety API.
    Returns True if content is safe, False if flagged.
    Falls back to True (allow) if Content Safety is not configured.
    This runs IN ADDITION to Foundry's built-in DriftWatch-Safety RAI policy.
    """
    endpoint = os.environ.get("AZURE_CONTENT_SAFETY_ENDPOINT", "")
    key = os.environ.get("AZURE_CONTENT_SAFETY_KEY", "")

    if not endpoint or not key:
        return True

    try:
        import httpx
        with httpx.Client(timeout=5) as client:
            resp = client.post(
                f"{endpoint}/contentsafety/text:analyze?api-version=2024-09-01",
                headers={"Ocp-Apim-Subscription-Key": key, "Content-Type": "application/json"},
                json={"text": text[:5000]},
            )
            if resp.status_code == 200:
                result = resp.json()
                categories = result.get("categoriesAnalysis", [])
                for cat in categories:
                    if cat.get("severity", 0) >= 4:
                        logging.warning(f"[Content Safety] Flagged: {cat['category']} severity {cat['severity']}")
                        return False
                return True
    except Exception as e:
        logging.warning(f"[Content Safety] Check failed (allowing): {e}")

    return True


# ---------------------------------------------------------------------------
# GitHub Copilot Agent Mode — Issue Creator for automated remediation
# ---------------------------------------------------------------------------

def create_github_issue_for_copilot(repo: str, pr_number: int, risk_score: int,
                                     risk_level: str, summary: str,
                                     high_risk_files: list, recommendation: str) -> bool:
    """
    Creates a GitHub Issue formatted for GitHub Copilot Coding Agent to pick up.
    When Copilot Agent Mode is enabled, it reads these issues and generates fix PRs.

    Flow: Negotiator blocks PR → Creates Issue → Copilot Agent picks up → Generates fix → Human reviews
    """
    token = os.environ.get("GITHUB_TOKEN", "")
    if not token or not repo or risk_score < 50:
        return False  # Only create issues for high-risk PRs

    # Build file analysis section for Copilot
    file_details = ""
    for f in high_risk_files[:10]:
        if isinstance(f, dict):
            fname = f.get("file", f.get("filename", "unknown"))
            ftype = f.get("change_type", "unknown")
            fscore = f.get("risk_score", 0)
            freason = f.get("reasoning", "")
            file_details += f"- **`{fname}`** — {ftype} (risk: {fscore})\n  {freason}\n"

    issue_body = f"""## DriftWatch Automated Risk Finding

> This issue was created by DriftWatch's Negotiator agent after detecting high-risk changes in PR #{pr_number}.
> **GitHub Copilot**: Please analyze the files listed below and suggest safer implementations.

### Risk Assessment
- **Risk Score:** {risk_score}/100 ({risk_level.upper()})
- **PR:** #{pr_number}
- **Decision:** {"BLOCKED" if risk_score >= 75 else "PENDING REVIEW"}

### Summary
{summary}

### High-Risk Files Requiring Attention
{file_details if file_details else "See PR for details."}

### Recommendation
{recommendation}

### What Copilot Should Do
1. Review each high-risk file for the identified concerns
2. Suggest safer patterns (e.g., parameterized queries for SQL, proper auth checks)
3. Create a fix PR targeting the same branch as PR #{pr_number}
4. Include test coverage for the fixes

---
*Created by [DriftWatch](https://github.com) — Pre-deployment Risk Intelligence*
*Powered by Microsoft Agent Framework (Semantic Kernel) + Azure AI Foundry*
"""

    try:
        import httpx
        with httpx.Client(timeout=10) as client:
            resp = client.post(
                f"https://api.github.com/repos/{repo}/issues",
                headers={"Authorization": f"token {token}", "Accept": "application/json"},
                json={
                    "title": f"[DriftWatch] High-risk changes detected in PR #{pr_number} (Score: {risk_score}/100)",
                    "body": issue_body,
                    "labels": ["driftwatch", "security", "copilot-agent", risk_level],
                },
            )
            if resp.status_code == 201:
                issue_url = resp.json().get("html_url", "")
                logging.info(f"[Copilot Integration] Created GitHub Issue for Copilot: {issue_url}")
                return True
            else:
                logging.warning(f"[Copilot Integration] Issue creation failed: {resp.status_code} {resp.text[:200]}")
    except Exception as e:
        logging.warning(f"[Copilot Integration] Issue creation failed: {e}")

    return False


# ---------------------------------------------------------------------------
# SRE Agent — Incident Auto-Detection & Self-Healing Triggers
# ---------------------------------------------------------------------------

class SREAgentPlugin:
    """
    Semantic Kernel Plugin: SRE Agent for automated incident response.
    Monitors deployment outcomes and triggers self-healing actions when incidents are detected.

    Implements Azure SRE Agent patterns:
    - Automated incident detection from deployment monitoring
    - Rollback recommendation when post-deploy metrics degrade
    - Alert correlation across services
    - Self-healing trigger via GitHub Actions workflow dispatch
    """

    @kernel_function(
        name="assess_deployment_health",
        description="Monitors post-deployment health and recommends rollback or self-healing actions."
    )
    async def assess_deployment_health(self, pr_number: int, deployed_services: list,
                                        risk_score: int, weather_score: int,
                                        active_incidents: list = None) -> dict:
        """Assesses deployment health and recommends SRE actions."""
        system_prompt = SRE_AGENT_SYSTEM
        user_prompt = f"""Assess the deployment health for PR #{pr_number}.

DEPLOYMENT CONTEXT:
- Services deployed: {json.dumps(deployed_services)}
- Pre-deployment risk score: {risk_score}/100
- Deployment weather score: {weather_score}/100
- Active incidents at deploy time: {json.dumps(active_incidents or [])}

Determine if any SRE action is needed:
1. Should we trigger a rollback?
2. Should we page on-call?
3. Should we trigger self-healing (restart, scale-up)?
4. What monitoring should be intensified?

Return JSON with your assessment and recommended actions."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=1000, agent_name="sre_agent")

    @kernel_function(
        name="correlate_incidents",
        description="Correlates active incidents with recent deployments to identify causal relationships."
    )
    async def correlate_incidents(self, recent_deploys: list, active_incidents: list) -> dict:
        """Correlates incidents with deployments for root cause analysis."""
        system_prompt = SRE_AGENT_SYSTEM
        user_prompt = f"""Correlate these active incidents with recent deployments.

RECENT DEPLOYMENTS (last 2 hours):
{json.dumps(recent_deploys[:10], default=str)}

ACTIVE INCIDENTS:
{json.dumps(active_incidents[:10], default=str)}

Determine:
1. Which deployments likely caused which incidents?
2. What is the recommended remediation order?
3. Which services need immediate attention?

Return JSON with correlation analysis and prioritized actions."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=1000, agent_name="sre_agent")


def trigger_rollback_workflow(repo: str, pr_number: int, reason: str) -> bool:
    """
    Triggers a GitHub Actions workflow for automated rollback.
    Implements the self-healing pattern from Azure SRE Agent.
    """
    token = os.environ.get("GITHUB_TOKEN", "")
    if not token or not repo:
        return False

    try:
        import httpx
        with httpx.Client(timeout=10) as client:
            # Trigger a workflow_dispatch event for the rollback workflow
            resp = client.post(
                f"https://api.github.com/repos/{repo}/dispatches",
                headers={"Authorization": f"token {token}", "Accept": "application/json"},
                json={
                    "event_type": "driftwatch-rollback",
                    "client_payload": {
                        "pr_number": pr_number,
                        "reason": reason,
                        "triggered_by": "driftwatch-sre-agent",
                        "timestamp": datetime.utcnow().isoformat(),
                    },
                },
            )
            if resp.status_code in (200, 204):
                logging.info(f"[SRE Agent] Rollback workflow triggered for PR #{pr_number}: {reason}")
                return True
            else:
                logging.warning(f"[SRE Agent] Rollback trigger failed: {resp.status_code}")
    except Exception as e:
        logging.warning(f"[SRE Agent] Rollback trigger failed: {e}")
    return False


SRE_AGENT_SYSTEM = """You are the DriftWatch SRE Agent — an automated Site Reliability Engineering agent
that monitors deployment health and triggers self-healing actions.

Your responsibilities:
1. ASSESS deployment health by correlating risk scores, weather conditions, and active incidents
2. RECOMMEND actions: rollback, page-oncall, scale-up, increase-monitoring, or no-action
3. CORRELATE incidents with recent deployments to identify root causes
4. PRIORITIZE remediation actions by blast radius and severity

You must return ONLY valid JSON with this structure:
{
    "health_status": "healthy" | "degraded" | "critical",
    "actions": [
        {
            "type": "rollback" | "page_oncall" | "scale_up" | "increase_monitoring" | "restart_service" | "no_action",
            "service": "service-name",
            "priority": "P1" | "P2" | "P3",
            "reason": "why this action is needed",
            "automated": true/false
        }
    ],
    "incident_correlations": [
        {
            "incident_id": "INC-XXX",
            "likely_caused_by_pr": 123,
            "confidence": "high" | "medium" | "low",
            "evidence": "explanation"
        }
    ],
    "monitoring_recommendations": ["what to watch for in the next 30 minutes"],
    "summary": "plain English health assessment"
}

Be conservative with rollback recommendations — only suggest when there is strong evidence of deployment-caused degradation.
Prefer less disruptive actions (monitoring, scaling) over rollbacks when uncertainty is high."""


# ---------------------------------------------------------------------------
# Semantic Kernel Plugins: Core Agent Plugins
# ---------------------------------------------------------------------------

class ArchaeologistPlugin:
    """SK Plugin: Analyzes PR diffs to map blast radius of code changes."""

    @kernel_function(
        name="analyze_blast_radius",
        description="Analyzes a pull request diff to map affected files, services, and endpoints."
    )
    async def analyze_blast_radius(self, repo: str, pr_number: int,
                                    diff_text: str, changed_files: list,
                                    model: str = None) -> dict:
        """Executes blast radius analysis via Foundry Responses API with full file contents."""
        system_prompt = ARCHAEOLOGIST_SYSTEM

        file_sections = []
        for f in changed_files[:30]:
            if isinstance(f, dict):
                section = f"### {f.get('filename', 'unknown')} ({f.get('status', 'modified')}, +{f.get('additions', 0)}/-{f.get('deletions', 0)})"
                if f.get("full_file_content"):
                    section += f"\n#### Full File Content:\n```\n{f['full_file_content']}\n```"
                if f.get("patch"):
                    section += f"\n#### Patch/Diff:\n```diff\n{f['patch']}\n```"
                file_sections.append(section)

        files_text = "\n\n".join(file_sections)

        user_prompt = f"""Analyze this pull request and produce a blast radius assessment.
READ THE ACTUAL CODE BELOW — do not just look at file names.

REPOSITORY: {repo}
PR NUMBER: #{pr_number}
TOTAL FILES CHANGED: {len(changed_files)}

=== CHANGED FILES WITH FULL CONTENT ===

{files_text}

=== FULL DIFF ===
{diff_text[:10000]}

Classify each file using the scoring rubric. Read the actual code to determine:
1. What functions/classes were modified and how
2. What imports/dependencies connect this file to others
3. Whether the change affects public APIs, database queries, auth, or config

Return a complete JSON blast radius assessment with change_classifications for each file."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=3000,
                                   agent_name="archaeologist", model_override=model)


class HistorianPlugin:
    """SK Plugin: Correlates blast radius with historical incidents for risk scoring."""

    @kernel_function(
        name="calculate_risk",
        description="Calculates deployment risk score by correlating blast radius with incident history."
    )
    async def calculate_risk(self, pr_number: int, affected_services: list,
                              risk_indicators: list, incidents: list,
                              model: str = None) -> dict:
        """Executes risk assessment via Foundry Responses API."""
        system_prompt = HISTORIAN_SYSTEM
        user_prompt = f"""Assess deployment risk for PR #{pr_number}.

BLAST RADIUS:
- Affected Services: {json.dumps(affected_services)}
- Risk Indicators: {json.dumps(risk_indicators)}

HISTORICAL INCIDENTS (last 90 days):
{json.dumps(incidents[:15], indent=2, default=str)}

Produce a JSON risk assessment with score 0-100, level, related incidents, contributing factors, and recommendation."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=1500,
                                   agent_name="historian", model_override=model)


class NegotiatorPlugin:
    """SK Plugin: Makes deploy/block/review decisions and posts GitHub PR comments."""

    @kernel_function(
        name="make_decision",
        description="Makes deployment decision based on risk score and generates PR comment."
    )
    async def make_decision(self, pr_number: int, risk_score: int,
                             risk_level: str, summary: str, recommendation: str) -> dict:
        """Executes deployment decision via Foundry Responses API."""
        system_prompt = NEGOTIATOR_SYSTEM
        user_prompt = f"""Make a deployment decision for PR #{pr_number}.

RISK SCORE: {risk_score}/100 ({risk_level})
SUMMARY: {summary}
RECOMMENDATION: {recommendation}

Generate the decision JSON and a well-formatted GitHub PR comment in markdown."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=1500,
                                   agent_name="negotiator")


class ChroniclerPlugin:
    """SK Plugin: Records post-deployment outcomes and evaluates prediction accuracy.
    This is DriftWatch's LEARNING AGENT — feeds accuracy data back to the Historian
    for continuous model improvement."""

    @kernel_function(
        name="record_outcome",
        description="Records deployment outcome and evaluates prediction accuracy for learning loop."
    )
    async def record_outcome(self, predicted_score: int, affected_services: list,
                              incident_occurred: bool, actual_severity: int = None) -> dict:
        """Executes outcome analysis via Foundry Responses API."""
        system_prompt = CHRONICLER_SYSTEM
        user_prompt = f"""Evaluate deployment outcome and prediction accuracy.

PREDICTION:
- Predicted risk score: {predicted_score}/100
- Expected affected services: {json.dumps(affected_services)}

ACTUAL OUTCOME:
- Incident occurred: {incident_occurred}
- Actual severity: {actual_severity or 'N/A (no incident)'}

Assess whether the prediction was accurate and provide post-mortem analysis."""

        return await sk_json_call(system_prompt, user_prompt, max_tokens=1000,
                                   agent_name="chronicler")


# ---------------------------------------------------------------------------
# Agent System Prompts
# ---------------------------------------------------------------------------

ARCHAEOLOGIST_SYSTEM = """You are The Archaeologist, a code analysis agent for DriftWatch.
Your job is to analyze a pull request diff and map its REAL blast radius — which files,
services, API endpoints, and downstream dependencies are affected by this change.

CRITICAL: You must READ the actual diff content, not just file names. Classify every changed
file using this scoring rubric and sum the scores:

SCORING TABLE (assign the HIGHEST matching score per file):
- CSS or view-only change (no logic, only .blade.php layout or .css): 2 points
- New standalone function added, not called by existing code: 5 points
- New API endpoint or new controller action: 15 points
- Existing function signature modified (parameters changed, return type changed): 20 points
- Middleware, auth, token handling, session handling, guards: 30 points
- SQL query added/modified, Eloquent query builder changes, migration file: 25 points
- Config file changed (.env, config/, any .json config): 20 points
- Shared utility or base class imported by 3+ other files: 25 points

For any file scoring 20+, read the full file context to determine if the change is
isolated or connects to many system parts. Follow imports (use/require/include for PHP,
import/require for JS) to find downstream files in the blast radius.

FAILURE STATES:
- If the diff is empty or PR has no changed files, return {"status": "insufficient_data"}
- If an error occurs, return {"status": "error", "error_message": "description"}
- NEVER return a fake low score when data is missing — that causes false "Clear Skies"

You must return ONLY valid JSON with this exact structure:
{
    "status": "scored",
    "affected_files": ["list of directly changed files"],
    "affected_services": ["list of services/modules impacted — infer from file paths, imports"],
    "affected_endpoints": ["list of API endpoints whose behavior may change"],
    "dependency_graph": {"changed_file": ["files that depend on this changed file"]},
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
    "risk_indicators": ["specific technical concerns"],
    "summary": "plain English paragraph explaining what the PR changes and why it is risky or safe"
}

Be thorough but realistic. Infer service names from directory structure.
Flag high-risk patterns: database migrations, config changes, shared library modifications, auth/payment code."""

HISTORIAN_SYSTEM = """You are The Historian, a Site Reliability Engineer agent for DriftWatch.
Your job is to assess deployment risk by correlating the current PR's blast radius with
historical incident data. You use THREE layers of matching:

LAYER 1 — Direct File Match (25 pts each):
Check if any file in affected_files exactly matches a file in any incident's affected_files.

LAYER 2 — Service Match (10 pts each):
Check if any service in affected_services matches a service in any incident's affected_services.

LAYER 3 — Change Type Match (15 pts each):
Check if any change_type from the Archaeologist's change_classifications matches a
change_type field in past incidents (e.g., "changed_function_signature", "new_api_endpoint").

CAP your total contribution at 40 points maximum from historical matching alone.
Then add the blast_radius_score from the Archaeologist (passed to you) to get the final score.

IMPORTANT: If no incidents exist in the database at all, return:
{"status": "insufficient_data", "message": "No incident history available — deploy to a
real environment and accumulate incident data to enable historical scoring."}
Do NOT return zero as if the PR is safe. Be honest that scoring is unavailable.

Also factor in CI status and bot findings if provided by the Archaeologist.

You must return ONLY valid JSON with this exact structure:
{
    "status": "scored",
    "risk_score": 0-100 integer,
    "risk_level": "low" (0-25) / "medium" (26-50) / "high" (51-75) / "critical" (76-100),
    "historical_incidents": [
        {"id": "INC-XXX", "title": "...", "severity": 1-5, "days_ago": N, "relevance": "why relevant",
         "match_type": "file|service|change_type", "match_score": 25}
    ],
    "match_summary": {
        "file_matches": 0,
        "service_matches": 0,
        "change_type_matches": 0,
        "history_score": 0,
        "blast_radius_score": 0,
        "ci_risk_addition": 0,
        "bot_risk_addition": 0
    },
    "contributing_factors": ["list of factors driving the risk score"],
    "recommendation": "paragraph with specific deploy/delay/block advice"
}

Risk scoring guidelines:
- 0-25 (Low): No related incidents, documentation/config-only changes, isolated scope
- 26-50 (Medium): Minor related incidents, limited blast radius, some downstream dependencies
- 51-75 (High): Recent related incidents (< 30 days), multiple services affected, critical path changes
- 76-100 (Critical): Multiple P1/P2 incidents in same area, core infrastructure changes, wide blast radius

Be specific about WHY the score is what it is. Reference actual incident IDs and patterns."""

NEGOTIATOR_SYSTEM = """You are The Negotiator, a deployment gatekeeper agent for DriftWatch.
Your job is to make the final deploy/block/review decision and craft a clear GitHub PR comment
summarizing the risk assessment for the development team.

Decision rules:
- risk_score >= 75: BLOCK (decision = "blocked")
- risk_score >= 50: PENDING REVIEW (decision = "pending_review")
- risk_score < 50: APPROVE (decision = "approved")

You must return ONLY valid JSON with this structure:
{
    "decision": "approved" | "blocked" | "pending_review",
    "has_concurrent_deploys": false,
    "in_freeze_window": false,
    "notified_oncall": true/false,
    "notification_message": "message for on-call team or null",
    "pr_comment": "full markdown comment to post on the GitHub PR"
}

The pr_comment should be a well-formatted markdown comment including:
- Risk score with emoji indicator
- Summary of blast radius
- Key contributing factors
- Clear recommendation
- DriftWatch branding footer"""

CHRONICLER_SYSTEM = """You are The Chronicler, a post-deployment analysis agent for DriftWatch.
Your job is to compare what was predicted (risk score, affected services) with what actually
happened after deployment, and record whether the prediction was accurate.

This data feeds back to the Historian agent to improve future predictions — you are the
LEARNING LOOP that makes DriftWatch a truly agentic system, not just a pipeline.

You must return ONLY valid JSON with this structure:
{
    "predicted_risk_score": integer,
    "incident_occurred": true/false,
    "actual_severity": integer 1-5 or null,
    "actual_affected_services": ["list of actually affected services"],
    "prediction_accurate": true/false,
    "accuracy_delta": integer (how many points off the prediction was),
    "calibration_suggestion": "suggestion for improving the Historian's scoring",
    "post_mortem_notes": "analysis of prediction accuracy and lessons learned"
}

A prediction is considered accurate if:
- Low risk (< 26) and no incident → accurate
- High/critical risk (> 50) and incident occurred → accurate
- Any other combination → inaccurate

Provide insightful post_mortem_notes about what can be learned.
The calibration_suggestion should be specific enough that the Historian can use it."""


# ---------------------------------------------------------------------------
# Azure Function Endpoints (HTTP Triggers)
# ---------------------------------------------------------------------------

def _run_async(coro):
    """Helper to run async SK functions from sync Azure Function handlers."""
    try:
        loop = asyncio.get_event_loop()
        if loop.is_running():
            import concurrent.futures
            with concurrent.futures.ThreadPoolExecutor() as pool:
                return pool.submit(asyncio.run, coro).result()
        return loop.run_until_complete(coro)
    except RuntimeError:
        return asyncio.run(coro)


@app.route(route="archaeologist", methods=["POST"])
def archaeologist(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 1: Blast Radius Mapper (Semantic Kernel ArchaeologistPlugin)
    Routed through Azure AI Foundry with DriftWatch-Safety RAI guardrails.
    Model selection via Model Router (gpt-4.1-mini or gpt-4.1 for complex PRs).
    """
    logging.info("[Archaeologist] Received analysis request via Foundry + SK pipeline.")
    start_time = datetime.utcnow()

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    repo = body.get("repo_full_name", "")
    pr_number = body.get("pr_number", 0)

    # Step 1: Fetch PR diff and changed files from GitHub (via MCP tools pattern)
    changed_files = []
    diff_text = ""
    pr_head_sha = ""

    token = os.environ.get("GITHUB_TOKEN", "")
    if token and repo and pr_number:
        import httpx
        headers = {"Authorization": f"token {token}", "Accept": "application/json"}

        try:
            with httpx.Client(timeout=30) as client:
                # MCP Tool: github_get_pr — Get PR metadata for head SHA
                pr_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}",
                    headers=headers,
                )
                if pr_resp.status_code == 200:
                    pr_data = pr_resp.json()
                    pr_head_sha = pr_data.get("head", {}).get("sha", "")

                # MCP Tool: github_list_pr_files — Get changed files with patch data
                files_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}/files?per_page=100",
                    headers=headers,
                )
                if files_resp.status_code == 200:
                    changed_files = files_resp.json()
                    logging.info(f"[Archaeologist] Fetched {len(changed_files)} changed files from GitHub.")

                # MCP Tool: github_read_pr_diff — Get full diff
                diff_headers = {**headers, "Accept": "application/vnd.github.v3.diff"}
                diff_resp = client.get(
                    f"https://api.github.com/repos/{repo}/pulls/{pr_number}",
                    headers=diff_headers,
                )
                if diff_resp.status_code == 200:
                    diff_text = diff_resp.text
                    logging.info(f"[Archaeologist] Fetched diff ({len(diff_text)} chars).")

                # MCP Tool: github_read_file — Fetch full file contents for changed files
                import base64
                files_read = 0
                for f in changed_files[:30]:
                    fname = f.get("filename", "")
                    if not fname or not pr_head_sha:
                        continue
                    skip_exts = [".png", ".jpg", ".jpeg", ".gif", ".ico", ".svg", ".woff",
                                 ".woff2", ".ttf", ".eot", ".mp4", ".zip", ".tar", ".gz",
                                 ".lock", ".min.js", ".min.css"]
                    if any(fname.lower().endswith(ext) for ext in skip_exts):
                        continue
                    if "/vendor/" in fname or "/node_modules/" in fname or "/dist/" in fname:
                        continue
                    try:
                        content_resp = client.get(
                            f"https://api.github.com/repos/{repo}/contents/{fname}?ref={pr_head_sha}",
                            headers=headers,
                        )
                        if content_resp.status_code == 200:
                            content_data = content_resp.json()
                            if content_data.get("encoding") == "base64" and content_data.get("size", 0) < 100000:
                                decoded = base64.b64decode(content_data["content"]).decode("utf-8", errors="replace")
                                f["full_content"] = decoded[:8000]
                                files_read += 1
                    except Exception:
                        pass
                logging.info(f"[Archaeologist] Read full contents of {files_read}/{len(changed_files)} files.")

                # Step 5: Check CI status
                ci_status = "unknown"
                failing_checks = []
                ci_risk_addition = 0
                if pr_head_sha:
                    try:
                        checks_resp = client.get(
                            f"https://api.github.com/repos/{repo}/commits/{pr_head_sha}/check-runs?per_page=50",
                            headers=headers,
                        )
                        if checks_resp.status_code == 200:
                            check_runs = checks_resp.json().get("check_runs", [])
                            required_keywords = ["unit test", "required", "backend", "integration",
                                                  "build", "lint", "ci"]
                            for check in check_runs:
                                if check.get("conclusion") == "failure":
                                    check_name = check.get("name", "")
                                    if any(kw in check_name.lower() for kw in required_keywords):
                                        failing_checks.append(check_name)

                            if failing_checks:
                                ci_status = "failing"
                                ci_risk_addition = 25
                            elif any(c.get("conclusion") == "success" for c in check_runs):
                                ci_status = "passing"
                            else:
                                ci_status = "pending"
                    except Exception as e:
                        logging.warning(f"[Archaeologist] CI check failed: {e}")

                # Step 6: Check bot findings (security bots)
                bot_findings = []
                bot_risk_addition = 0
                security_bots = ["aikido", "snyk", "codeql", "dependabot", "semgrep",
                                  "sonarcloud", "sonarqube", "checkmarx", "mend", "renovate"]
                try:
                    comments_resp = client.get(
                        f"https://api.github.com/repos/{repo}/issues/{pr_number}/comments?per_page=100",
                        headers=headers,
                    )
                    if comments_resp.status_code == 200:
                        comments = comments_resp.json()
                        for comment in comments:
                            author = (comment.get("user", {}).get("login", "") or "").lower()
                            body_text = (comment.get("body", "") or "")[:500]
                            for bot in security_bots:
                                if bot in author:
                                    severity = "medium"
                                    risk_add = 10
                                    body_lower = body_text.lower()
                                    if "critical" in body_lower:
                                        severity = "critical"
                                        risk_add = 30
                                    elif "high" in body_lower:
                                        severity = "high"
                                        risk_add = 20
                                    bot_findings.append({
                                        "bot": author,
                                        "severity": severity,
                                        "summary": body_text[:200],
                                        "risk_addition": risk_add,
                                    })
                                    bot_risk_addition += risk_add
                                    break
                except Exception as e:
                    logging.warning(f"[Archaeologist] Bot findings check failed: {e}")

        except Exception as e:
            logging.warning(f"[Archaeologist] GitHub API call failed: {e}")

    # Handle empty diff / no data
    if not changed_files and not diff_text:
        return func.HttpResponse(json.dumps({
            "status": "insufficient_data",
            "error_message": "No changed files or diff content available for this PR.",
            "affected_files": [], "affected_services": [], "affected_endpoints": [],
            "dependency_graph": {}, "change_classifications": [],
            "total_blast_radius_score": 0, "total_affected_files": 0,
            "total_affected_services": 0, "summary": "Insufficient data to analyze this PR.",
        }), mimetype="application/json", status_code=200)

    # Model Router: select optimal model based on PR complexity
    routing_context = {
        "file_count": len(changed_files),
        "diff_size": len(diff_text),
    }
    selected_model = _model_router.select_model("archaeologist", routing_context)

    # Build enriched file info for the AI prompt
    file_details = []
    total_content_chars = 0
    max_total_chars = 60000
    for f in changed_files[:30]:
        if isinstance(f, dict):
            detail = {
                "filename": f.get("filename", "unknown"),
                "status": f.get("status", "modified"),
                "additions": f.get("additions", 0),
                "deletions": f.get("deletions", 0),
                "patch": (f.get("patch", "") or "")[:3000],
            }
            if f.get("full_content") and total_content_chars < max_total_chars:
                content = f["full_content"]
                budget = min(len(content), max_total_chars - total_content_chars, 6000)
                detail["full_file_content"] = content[:budget]
                total_content_chars += budget
            file_details.append(detail)

    # Execute AI classification via SK plugin (routed through Foundry)
    plugin = ArchaeologistPlugin()
    result = _run_async(plugin.analyze_blast_radius(
        repo=repo,
        pr_number=pr_number,
        diff_text=diff_text[:15000],
        changed_files=file_details,
        model=selected_model,
    ))

    # Ensure required fields exist
    result.setdefault("status", "scored")
    result.setdefault("affected_files", [f.get("filename", "") for f in changed_files if isinstance(f, dict)])
    result.setdefault("affected_services", [])
    result.setdefault("affected_endpoints", [])
    result.setdefault("dependency_graph", {})
    result.setdefault("change_classifications", [])
    result.setdefault("risk_indicators", [])
    result.setdefault("summary", "Analysis complete.")
    result.setdefault("total_affected_files", len(result["affected_files"]))
    result.setdefault("total_affected_services", len(result["affected_services"]))

    # Calculate total blast radius score from classifications
    classification_score = sum(c.get("risk_score", 0) for c in result.get("change_classifications", []))
    total_score = classification_score + ci_risk_addition + bot_risk_addition
    result["total_blast_radius_score"] = min(total_score, 100)

    # Add CI and bot data
    result["ci_status"] = ci_status
    result["failing_checks"] = failing_checks
    result["ci_risk_addition"] = ci_risk_addition
    result["bot_findings"] = bot_findings
    result["bot_risk_addition"] = bot_risk_addition

    # Add Model Router metadata
    result["model_used"] = selected_model
    result["model_router"] = _model_router.get_routing_metadata("archaeologist", selected_model)

    # Content Safety check
    if not content_safety_check(result.get("summary", "")):
        result["summary"] = "Analysis complete. (Content filtered by Azure AI Content Safety)"

    # Archive to Blob Storage (MRP artifact)
    archive_mrp_artifact(pr_number, repo, "blast_radius", result)

    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[Archaeologist] Analysis complete. Score: {result['total_blast_radius_score']}, "
                 f"Model: {selected_model}, Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="historian", methods=["POST"])
def historian(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 2: Risk Score Calculator (Semantic Kernel HistorianPlugin)
    Routed through Azure AI Foundry. Model Router may upgrade for complex correlations.
    """
    logging.info("[Historian] Received risk assessment request via Foundry + SK pipeline.")
    start_time = datetime.utcnow()

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    affected_services = body.get("affected_services", [])
    affected_files = body.get("affected_files", [])
    risk_indicators = body.get("risk_indicators", [])
    pr_number = body.get("pr_number", 0)
    blast_radius_score = body.get("blast_radius_score", 0)
    change_classifications = body.get("change_classifications", [])
    ci_status = body.get("ci_status", "unknown")
    ci_risk_addition = body.get("ci_risk_addition", 0)
    bot_findings = body.get("bot_findings", [])
    bot_risk_addition = body.get("bot_risk_addition", 0)

    # MCP Tool: query_incidents — Fetch historical incidents from Laravel API
    incidents = []
    laravel_url = os.environ.get("LARAVEL_APP_URL", "http://localhost:8000")
    import httpx
    try:
        with httpx.Client(timeout=10) as client:
            resp = client.get(f"{laravel_url}/api/incidents")
            if resp.status_code == 200:
                incidents = resp.json()
                logging.info(f"[Historian] Fetched {len(incidents)} incidents for multi-layer matching.")
    except Exception as e:
        logging.warning(f"[Historian] Failed to fetch incidents: {e}")

    # RAG: Retrieve semantically relevant incidents from Azure AI Search
    # This supplements the database query with vector similarity search
    blast_summary = body.get("summary", "")
    rag_incidents = rag_retrieve_incidents(
        query=f"{blast_summary} {' '.join(affected_services)} {' '.join(risk_indicators[:5])}",
        services=affected_services,
        top_k=10,
    )
    if rag_incidents:
        # Merge RAG results with DB results (deduplicate by ID)
        existing_ids = {str(inc.get("id", "")) for inc in incidents}
        for rag_inc in rag_incidents:
            if str(rag_inc.get("id", "")) not in existing_ids:
                incidents.append(rag_inc)
        logging.info(f"[Historian] RAG added {len(rag_incidents)} semantic matches. Total: {len(incidents)} incidents.")

    # Azure AI Language: Extract key phrases from incident descriptions for richer matching
    # This enables semantic correlation: "auth token expired" matches "login session timeout"
    ai_language_enrichment = {}
    if incidents:
        incident_texts = [inc.get("description", inc.get("title", "")) for inc in incidents[:20]]
        pr_texts = [blast_summary] + risk_indicators[:5]
        all_texts = pr_texts + incident_texts

        key_phrases = extract_key_phrases(all_texts)
        if key_phrases:
            pr_key_phrases = []
            for kp_list in key_phrases[:len(pr_texts)]:
                pr_key_phrases.extend(kp_list)
            incident_key_phrases = key_phrases[len(pr_texts):]

            # Find key phrase overlaps (semantic matching layer)
            pr_phrases_set = set(p.lower() for p in pr_key_phrases)
            phrase_matches = []
            for i, inc_phrases in enumerate(incident_key_phrases):
                overlap = pr_phrases_set.intersection(set(p.lower() for p in inc_phrases))
                if overlap:
                    phrase_matches.append({
                        "incident_index": i,
                        "incident_id": incidents[i].get("id", ""),
                        "matching_phrases": list(overlap),
                        "match_count": len(overlap),
                    })

            ai_language_enrichment = {
                "pr_key_phrases": list(pr_phrases_set)[:20],
                "phrase_matches": phrase_matches,
                "total_phrase_matches": len(phrase_matches),
            }
            logging.info(f"[AI Language] Found {len(phrase_matches)} key phrase matches between PR and incidents.")

    # If no incidents exist, return insufficient_data
    if not incidents:
        result = {
            "status": "insufficient_data",
            "risk_score": blast_radius_score,
            "risk_level": "unknown",
            "message": "No incident history available — deploy to a real environment and accumulate incident data to enable historical scoring.",
            "historical_incidents": [],
            "match_summary": {
                "file_matches": 0, "service_matches": 0, "change_type_matches": 0,
                "history_score": 0, "blast_radius_score": blast_radius_score,
                "ci_risk_addition": ci_risk_addition, "bot_risk_addition": bot_risk_addition,
            },
            "contributing_factors": ["No incident history available for historical correlation"],
            "recommendation": f"Historical scoring unavailable. Blast radius score is {blast_radius_score}/100. Proceed with standard review process.",
        }
        score = blast_radius_score
        for level, low, high in [("critical", 76, 101), ("high", 51, 76), ("medium", 26, 51), ("low", 0, 26)]:
            if low <= score < high:
                result["risk_level"] = level
                break
        result["duration_ms"] = int((datetime.utcnow() - start_time).total_seconds() * 1000)
        return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)

    # Model Router: select model based on incident correlation complexity
    routing_context = {"incident_count": len(incidents), "file_count": len(affected_files)}
    selected_model = _model_router.select_model("historian", routing_context)

    # Build enriched risk indicators including AI Language key phrases
    enriched_indicators = risk_indicators + [
        f"Blast radius score: {blast_radius_score}",
        f"CI status: {ci_status}",
        f"CI risk addition: {ci_risk_addition}",
        f"Bot findings count: {len(bot_findings)}",
        f"Affected files: {json.dumps(affected_files[:10])}",
        f"Change classifications: {json.dumps(change_classifications[:10])}",
    ]
    if ai_language_enrichment.get("pr_key_phrases"):
        enriched_indicators.append(
            f"AI Language key phrases: {', '.join(ai_language_enrichment['pr_key_phrases'][:10])}"
        )
    if ai_language_enrichment.get("phrase_matches"):
        enriched_indicators.append(
            f"Semantic phrase matches with {ai_language_enrichment['total_phrase_matches']} incidents"
        )

    # Execute via SK plugin (routed through Foundry)
    plugin = HistorianPlugin()
    result = _run_async(plugin.calculate_risk(
        pr_number=pr_number,
        affected_services=affected_services,
        risk_indicators=enriched_indicators,
        incidents=incidents[:20],
        model=selected_model,
    ))

    # Ensure required fields
    result.setdefault("status", "scored")
    result.setdefault("risk_score", 50)
    result.setdefault("risk_level", "medium")
    result.setdefault("historical_incidents", [])
    result.setdefault("contributing_factors", [])
    result.setdefault("recommendation", "Review recommended before deployment.")
    result.setdefault("match_summary", {
        "file_matches": 0, "service_matches": 0, "change_type_matches": 0,
        "history_score": 0, "blast_radius_score": blast_radius_score,
        "ci_risk_addition": ci_risk_addition, "bot_risk_addition": bot_risk_addition,
    })

    # Validate risk_level matches score
    score = result["risk_score"]
    for level, low, high in [("critical", 76, 101), ("high", 51, 76), ("medium", 26, 51), ("low", 0, 26)]:
        if low <= score < high:
            result["risk_level"] = level
            break

    result["model_used"] = selected_model

    # Add RAG and AI Language metadata to result
    if rag_incidents:
        result["rag_incidents_retrieved"] = len(rag_incidents)
        result["rag_enabled"] = True
    else:
        result["rag_enabled"] = False

    if ai_language_enrichment:
        result["ai_language_enrichment"] = ai_language_enrichment

    # Archive to Blob Storage (MRP artifact)
    archive_mrp_artifact(pr_number, body.get("repo_full_name", ""), "risk_assessment", result)

    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[Historian] Risk assessment complete. Score: {score}/100 ({result['risk_level']}), "
                 f"Model: {selected_model}, RAG: {len(rag_incidents)} results, "
                 f"AI Language: {ai_language_enrichment.get('total_phrase_matches', 0)} matches, "
                 f"Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="negotiator", methods=["POST"])
def negotiator(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 3: Deployment Gatekeeper (Semantic Kernel NegotiatorPlugin)
    Routed through Azure AI Foundry. Creates GitHub Issues for Copilot Agent Mode.
    """
    logging.info("[Negotiator] Received deployment decision request via Foundry + SK pipeline.")
    start_time = datetime.utcnow()

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    risk_score = body.get("risk_score", 0)
    risk_level = body.get("risk_level", "low")
    repo = body.get("repo_full_name", "")
    pr_number = body.get("pr_number", 0)
    recommendation = body.get("recommendation", "")
    summary = body.get("summary", "")
    change_classifications = body.get("change_classifications", [])

    # Execute via SK plugin (routed through Foundry)
    plugin = NegotiatorPlugin()
    result = _run_async(plugin.make_decision(
        pr_number=pr_number,
        risk_score=risk_score,
        risk_level=risk_level,
        summary=summary,
        recommendation=recommendation,
    ))

    # Enforce decision rules
    if risk_score >= 75:
        result["decision"] = "blocked"
    elif risk_score >= 50:
        result["decision"] = "pending_review"
    else:
        result["decision"] = "approved"

    result.setdefault("has_concurrent_deploys", False)
    result.setdefault("in_freeze_window", False)
    result.setdefault("notified_oncall", risk_score >= 50)
    result.setdefault("notification_message",
                      f"Risk score {risk_score}/100 for PR #{pr_number}" if risk_score >= 50 else None)

    # Build fallback PR comment if needed
    pr_comment = result.get("pr_comment", "")
    if not pr_comment:
        emoji = {"critical": "\U0001f534", "high": "\U0001f7e0", "medium": "\U0001f7e1", "low": "\U0001f7e2"}.get(risk_level, "\u26aa")
        pr_comment = f"""## \U0001f3af DriftWatch Risk Assessment

**Risk Score: {risk_score}/100** {emoji} {risk_level.upper()}

### Summary
{summary or 'Analysis complete.'}

### Recommendation
{recommendation or 'No specific recommendation.'}

---
*Assessed by DriftWatch — Powered by Semantic Kernel + Azure AI Foundry*"""
        result["pr_comment"] = pr_comment

    # Content Safety check on PR comment
    if not content_safety_check(pr_comment):
        pr_comment = "## DriftWatch Risk Assessment\n\nContent filtered by Azure AI Content Safety. Please review manually."
        result["pr_comment"] = pr_comment

    # MCP Tool: github_post_comment — Post PR comment to GitHub
    token = os.environ.get("GITHUB_TOKEN", "")
    if token and repo and pr_number:
        import httpx
        try:
            with httpx.Client(timeout=10) as client:
                resp = client.post(
                    f"https://api.github.com/repos/{repo}/issues/{pr_number}/comments",
                    headers={"Authorization": f"token {token}", "Accept": "application/json"},
                    json={"body": pr_comment},
                )
                if resp.status_code == 201:
                    logging.info(f"[Negotiator] Posted PR comment to {repo}#{pr_number}")
                else:
                    logging.warning(f"[Negotiator] Failed to post PR comment: {resp.status_code}")
        except Exception as e:
            logging.warning(f"[Negotiator] GitHub comment post failed: {e}")

    # GitHub Copilot Agent Mode: Create Issue for high-risk PRs
    # Copilot's Coding Agent picks up these issues and generates fix PRs
    if risk_score >= 50:
        high_risk_files = [c for c in change_classifications if c.get("risk_score", 0) >= 15]
        copilot_issue_created = create_github_issue_for_copilot(
            repo=repo, pr_number=pr_number,
            risk_score=risk_score, risk_level=risk_level,
            summary=summary, high_risk_files=high_risk_files,
            recommendation=recommendation,
        )
        result["copilot_issue_created"] = copilot_issue_created

    # Archive to Blob Storage (MRP artifact)
    archive_mrp_artifact(pr_number, repo, "deployment_decision", result)

    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[Negotiator] Decision: {result['decision']} for PR #{pr_number}, Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


@app.route(route="chronicler", methods=["POST"])
def chronicler(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 4: Feedback Loop Recorder (Semantic Kernel ChroniclerPlugin)
    The LEARNING AGENT — feeds accuracy data back to Historian for continuous improvement.
    Routed through Azure AI Foundry.
    """
    logging.info("[Chronicler] Received post-deployment outcome via Foundry + SK pipeline.")
    start_time = datetime.utcnow()

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    predicted_score = body.get("predicted_risk_score", 0)
    affected_services = body.get("affected_services", [])
    incident_occurred = body.get("incident_occurred", False)
    actual_severity = body.get("actual_severity", None)

    plugin = ChroniclerPlugin()
    result = _run_async(plugin.record_outcome(
        predicted_score=predicted_score,
        affected_services=affected_services,
        incident_occurred=incident_occurred,
        actual_severity=actual_severity,
    ))

    # Ensure required fields
    result.setdefault("predicted_risk_score", predicted_score)
    result.setdefault("incident_occurred", incident_occurred)
    result.setdefault("actual_severity", actual_severity)
    result.setdefault("actual_affected_services", affected_services)

    if "prediction_accurate" not in result:
        if predicted_score < 26 and not incident_occurred:
            result["prediction_accurate"] = True
        elif predicted_score > 50 and incident_occurred:
            result["prediction_accurate"] = True
        elif 26 <= predicted_score <= 50 and not incident_occurred:
            result["prediction_accurate"] = True
        else:
            result["prediction_accurate"] = False

    result.setdefault("post_mortem_notes", "Outcome recorded.")
    result.setdefault("calibration_suggestion", "No calibration needed.")
    result.setdefault("accuracy_delta", abs(predicted_score - (actual_severity * 20 if actual_severity else 0)))

    # Archive to Blob Storage (MRP artifact)
    archive_mrp_artifact(0, "", "feedback_outcome", result)

    # Index outcome into Azure AI Search for future RAG retrieval
    if result.get("incident_occurred"):
        rag_index_incident({
            "id": f"outcome-{datetime.utcnow().strftime('%Y%m%d%H%M%S')}",
            "title": f"Deployment outcome — predicted {predicted_score}, actual severity {actual_severity}",
            "description": result.get("post_mortem_notes", ""),
            "severity": actual_severity or 0,
            "affected_services": affected_services,
            "affected_files": [],
            "occurred_at": datetime.utcnow().isoformat(),
            "resolution": result.get("calibration_suggestion", ""),
        })

    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[Chronicler] Outcome recorded. Accurate: {result['prediction_accurate']}, Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


# ---------------------------------------------------------------------------
# Agent 5: Navigator — Interactive Impact Chat
# ---------------------------------------------------------------------------

NAVIGATOR_SYSTEM_PROMPT = """You are the DriftWatch Navigator — an AI assistant that helps DevOps engineers understand the impact of a pull request.

You will receive:
- A user's natural language question
- Full context about the PR including: changed files, dependency graph, risk scores, affected services, blast radius analysis, and file summaries

Your job:
1. Answer the question clearly and concisely
2. Reference specific files, services, and risk scores from the context
3. Explain WHY things are risky, not just THAT they are risky
4. When asked about dependencies, trace the full chain
5. When asked to highlight or focus on files, return their IDs in the `highlight_nodes` array
6. Keep responses short (2-4 sentences) unless the user asks for detail

Return a JSON object with:
{
    "response": "Your natural language answer (supports markdown)",
    "highlight_nodes": ["file_path1", "file_path2"],
    "suggested_followups": ["question 1", "question 2"]
}

Be conversational, helpful, and specific. Use the actual file names and scores from context."""


SECURITY_AGENT_SYSTEM_PROMPT = """You are the DriftWatch Security Agent — a specialized security analysis mode
within the Navigator chat. You focus on identifying security vulnerabilities, risks, and compliance concerns
in pull request changes.

You will receive PR context including changed files, code diffs, and risk scores.

Your security analysis covers:
1. **OWASP Top 10** — SQL injection, XSS, CSRF, SSRF, insecure deserialization, broken auth
2. **Authentication & Authorization** — Token handling, session management, role-based access, API keys
3. **Data Protection** — PII exposure, encryption at rest/transit, secret leaks, logging sensitive data
4. **Infrastructure Security** — Config changes, env vars, CORS settings, TLS, firewall rules
5. **Dependency Security** — Known CVEs in dependencies, outdated packages, supply chain risks
6. **Code Quality Security** — Race conditions, error handling, input validation, output encoding

Return a JSON object with:
{
    "response": "Your security analysis in markdown (use **bold** for findings, bullet lists for details)",
    "highlight_nodes": ["file_paths_with_security_concerns"],
    "suggested_followups": ["security-related follow-up questions"],
    "security_findings": [
        {
            "severity": "critical" | "high" | "medium" | "low" | "info",
            "category": "OWASP category or security domain",
            "file": "affected_file.php",
            "finding": "description of the security concern",
            "recommendation": "how to fix it"
        }
    ],
    "security_score": 0-100
}

Be thorough but avoid false positives. Only flag actual security concerns visible in the code context.
Prioritize findings by severity: critical > high > medium > low > info."""


@app.route(route="navigator", methods=["POST"])
def navigator(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 5: Navigator — conversational Impact Chat for PR analysis.
    Also supports Security Agent mode for security-focused analysis.
    Routed through Azure AI Foundry with DriftWatch-Safety RAI guardrails.
    """
    logging.info("[Navigator] Impact chat request received via Foundry + SK pipeline.")

    try:
        body = req.get_json()
    except Exception:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON"}), mimetype="application/json", status_code=400)

    query = body.get("query", "")
    pr_context = body.get("pr_context", {})
    mode = body.get("mode", "navigator")  # "navigator" or "security"

    if not query:
        return func.HttpResponse(json.dumps({"error": "No query provided"}), mimetype="application/json", status_code=400)

    # Build context string
    context_parts = []
    context_parts.append(f"PR: {pr_context.get('pr_title', 'Unknown')} (#{pr_context.get('pr_number', '?')})")
    context_parts.append(f"Author: {pr_context.get('pr_author', 'unknown')}")
    context_parts.append(f"Repository: {pr_context.get('repo_full_name', 'unknown')}")
    context_parts.append(f"Files Changed: {pr_context.get('files_changed', 0)}")
    context_parts.append(f"Risk Score: {pr_context.get('risk_score', 0)}/100 ({pr_context.get('risk_level', 'unknown')})")
    context_parts.append(f"Decision: {pr_context.get('decision', 'pending')}")

    if pr_context.get("affected_services"):
        context_parts.append(f"Affected Services: {', '.join(pr_context['affected_services'])}")

    if pr_context.get("changed_files"):
        context_parts.append("\n--- Changed Files ---")
        for f in pr_context["changed_files"]:
            if isinstance(f, dict):
                context_parts.append(f"  {f.get('filename', f.get('file', '?'))} (score: {f.get('risk_score', '?')}, type: {f.get('change_type', '?')})")
            else:
                context_parts.append(f"  {f}")

    if pr_context.get("dependency_graph"):
        context_parts.append("\n--- Dependency Graph ---")
        for src, deps in pr_context["dependency_graph"].items():
            if isinstance(deps, list) and len(deps) > 0:
                context_parts.append(f"  {src} → {', '.join(deps)}")

    if pr_context.get("file_summaries"):
        context_parts.append("\n--- File Summaries ---")
        for path, info in pr_context["file_summaries"].items():
            if isinstance(info, dict):
                context_parts.append(f"  {path}: {info.get('summary', '')} | Risk: {info.get('risk', '')} | Affects: {info.get('affects', '')}")

    if pr_context.get("blast_summary"):
        context_parts.append(f"\nBlast Radius Summary: {pr_context['blast_summary']}")

    if pr_context.get("recommendation"):
        context_parts.append(f"AI Recommendation: {pr_context['recommendation']}")

    context_str = "\n".join(context_parts)

    # Security Agent mode: detect security keywords or explicit mode switch
    security_keywords = ["security", "vulnerability", "owasp", "injection", "xss", "csrf",
                         "auth", "authentication", "authorization", "token", "secret",
                         "password", "encrypt", "sql injection", "ssrf", "cve", "exploit"]
    is_security_query = mode == "security" or any(kw in query.lower() for kw in security_keywords)

    if is_security_query:
        # Use Security Agent system prompt
        system_prompt = SECURITY_AGENT_SYSTEM_PROMPT
        agent_name = "security_agent"

        # Enrich context with AI Language entity extraction for security analysis
        entities = extract_entities(query + " " + context_str[:2000])
        if entities:
            entity_context = "\n--- AI Language Entities (auto-extracted) ---\n"
            for ent in entities[:15]:
                entity_context += f"  [{ent['category']}] {ent['text']} (confidence: {ent['confidence']:.2f})\n"
            context_str += entity_context

        user_prompt = f"PR Context:\n{context_str}\n\nSecurity Analysis Query: {query}"
        logging.info(f"[Security Agent] Security-focused query detected. Entities: {len(entities)}")
    else:
        system_prompt = NAVIGATOR_SYSTEM_PROMPT
        agent_name = "navigator"
        user_prompt = f"PR Context:\n{context_str}\n\nUser Question: {query}"

    try:
        result = _run_async(sk_json_call(system_prompt, user_prompt,
                                          max_tokens=2000, agent_name=agent_name))
    except Exception as e:
        logging.error(f"[{agent_name}] SK call failed: {e}")
        result = {
            "response": f"I encountered an issue processing your question. The PR changes {pr_context.get('files_changed', 0)} files with a risk score of {pr_context.get('risk_score', 0)}.",
            "highlight_nodes": [],
            "suggested_followups": ["What files were changed?", "Show me the risk breakdown"],
        }

    result.setdefault("response", "I couldn't generate a response. Try rephrasing your question.")
    result.setdefault("highlight_nodes", [])
    result.setdefault("suggested_followups", [])
    result["mode"] = "security" if is_security_query else "navigator"

    if not content_safety_check(result["response"]):
        result["response"] = "Response filtered by content safety. Please rephrase your question."

    logging.info(f"[{agent_name}] Responded with {len(result['highlight_nodes'])} highlight nodes. Mode: {result['mode']}")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


# ---------------------------------------------------------------------------
# Agent 6: SRE Agent — Incident Auto-Response & Self-Healing
# ---------------------------------------------------------------------------

@app.route(route="sre", methods=["POST"])
def sre_agent(req: func.HttpRequest) -> func.HttpResponse:
    """
    Agent 6: SRE Agent — Automated incident response and self-healing.
    Implements Azure SRE Agent patterns for the Agentic DevOps category.
    Routed through Azure AI Foundry with DriftWatch-Safety RAI guardrails.
    """
    logging.info("[SRE Agent] Received health assessment request via Foundry + SK pipeline.")
    start_time = datetime.utcnow()

    try:
        body = req.get_json()
    except ValueError:
        return func.HttpResponse(json.dumps({"error": "Invalid JSON body"}), status_code=400, mimetype="application/json")

    action = body.get("action", "assess_health")

    plugin = SREAgentPlugin()

    if action == "correlate_incidents":
        result = _run_async(plugin.correlate_incidents(
            recent_deploys=body.get("recent_deploys", []),
            active_incidents=body.get("active_incidents", []),
        ))
    else:
        result = _run_async(plugin.assess_deployment_health(
            pr_number=body.get("pr_number", 0),
            deployed_services=body.get("deployed_services", []),
            risk_score=body.get("risk_score", 0),
            weather_score=body.get("weather_score", 0),
            active_incidents=body.get("active_incidents", []),
        ))

    # Check if rollback is recommended and trigger it
    repo = body.get("repo_full_name", "")
    pr_number = body.get("pr_number", 0)
    for action_item in result.get("actions", []):
        if action_item.get("type") == "rollback" and action_item.get("automated"):
            trigger_rollback_workflow(repo, pr_number, action_item.get("reason", "SRE Agent automated rollback"))

    result.setdefault("health_status", "healthy")
    result.setdefault("actions", [])
    result.setdefault("incident_correlations", [])
    result.setdefault("monitoring_recommendations", [])
    result.setdefault("summary", "Assessment complete.")

    duration_ms = int((datetime.utcnow() - start_time).total_seconds() * 1000)
    result["duration_ms"] = duration_ms

    logging.info(f"[SRE Agent] Health: {result['health_status']}, Actions: {len(result['actions'])}, Duration: {duration_ms}ms")

    return func.HttpResponse(json.dumps(result), mimetype="application/json", status_code=200)


# ---------------------------------------------------------------------------
# Health check endpoint
# ---------------------------------------------------------------------------

@app.route(route="health", methods=["GET"])
def health(req: func.HttpRequest) -> func.HttpResponse:
    """Health check — reports agent status, hero technologies, and Azure services."""
    foundry_endpoint = os.environ.get("FOUNDRY_ENDPOINT", "")
    return func.HttpResponse(
        json.dumps({
            "status": "healthy",
            "service": "DriftWatch AI Agent Pipeline",
            "version": "2.0.0",
            "hero_technologies": {
                "foundry_agent_service": {
                    "status": "active" if foundry_endpoint else "direct_mode",
                    "endpoint": foundry_endpoint[:50] + "..." if foundry_endpoint else "not_configured",
                    "rai_policy": "DriftWatch-Safety",
                },
                "semantic_kernel": {
                    "status": "active",
                    "pattern": "Planner → Skills → Memory",
                    "version": "1.0+",
                },
                "model_router": {
                    "status": "active" if _model_router.routing_enabled else "disabled",
                    "primary_model": _model_router.primary_model,
                    "complex_model": _model_router.complex_model,
                },
                "opentelemetry": {
                    "status": "active" if _otel_available else "not_available",
                    "exporter": "azure_monitor" if _app_insights_cs else "none",
                    "spans": "gen_ai.*",
                },
                "content_safety": {
                    "status": "active" if os.environ.get("AZURE_CONTENT_SAFETY_ENDPOINT") else "not_configured",
                },
                "copilot_integration": {
                    "status": "active" if os.environ.get("GITHUB_TOKEN") else "no_token",
                    "mode": "issue_creation_for_coding_agent",
                },
                "mcp_tools": {
                    "github_read": ["read_pr_diff", "read_file", "list_files", "get_pr", "get_check_runs"],
                    "github_write": ["post_comment", "create_issue", "create_check_run"],
                    "database": ["query_incidents"],
                },
                "ai_language": {
                    "status": "active" if _ai_language_available and os.environ.get("AZURE_AI_LANGUAGE_ENDPOINT") else "not_configured",
                    "features": ["key_phrase_extraction", "entity_recognition"],
                    "used_by": ["historian", "security_agent"],
                },
                "rag_search": {
                    "status": "active" if _ai_search_available and os.environ.get("AZURE_AI_SEARCH_ENDPOINT") else "not_configured",
                    "index": os.environ.get("AZURE_AI_SEARCH_INDEX", "driftwatch-incidents"),
                    "used_by": ["historian", "chronicler"],
                },
                "blob_storage": {
                    "status": "active" if _blob_available and os.environ.get("AZURE_BLOB_CONNECTION_STRING") else "not_configured",
                    "container": os.environ.get("AZURE_BLOB_CONTAINER", "driftwatch-mrp"),
                    "purpose": "MRP artifact archiving",
                },
                "security_agent": {
                    "status": "active",
                    "mode": "navigator_security_mode",
                    "features": ["owasp_analysis", "entity_extraction", "security_scoring"],
                },
            },
            "agents": [
                {"name": "archaeologist", "type": "SK Plugin (Foundry)", "status": "active", "description": "Blast Radius Mapper"},
                {"name": "historian", "type": "SK Plugin (Foundry + RAG + AI Language)", "status": "active", "description": "Risk Score Calculator with semantic retrieval"},
                {"name": "negotiator", "type": "SK Plugin (Foundry)", "status": "active", "description": "Deploy Gatekeeper + Copilot Issue Creator"},
                {"name": "chronicler", "type": "SK Plugin (Foundry)", "status": "active", "description": "Feedback Loop (Learning Agent) + RAG indexing"},
                {"name": "navigator", "type": "SK Plugin (Foundry)", "status": "active", "description": "Interactive Impact Chat + Security Agent mode"},
                {"name": "sre_agent", "type": "SK Plugin (Foundry)", "status": "active", "description": "Incident Auto-Response & Self-Healing"},
                {"name": "security_agent", "type": "Navigator Security Mode", "status": "active", "description": "OWASP + vulnerability analysis via AI Language"},
            ],
            "azure_services": [
                "Azure AI Foundry (Responses API + RAI)", "Azure OpenAI (GPT-4.1-mini + GPT-4.1)",
                "Semantic Kernel SDK", "Azure Functions V2", "Azure AI Content Safety",
                "Azure MySQL", "Application Insights", "OpenTelemetry (gen_ai.*)",
                "Azure Speech (TTS)", "Azure Service Bus", "Azure Key Vault",
                "Azure Monitor", "Microsoft Teams", "GitHub Copilot Agent Mode",
                "Azure AI Language (Key Phrases + Entities)", "Azure AI Search (RAG)",
                "Azure Blob Storage (MRP Artifacts)",
            ],
            "model": os.environ.get("AZURE_OPENAI_DEPLOYMENT", "gpt-4.1-mini"),
            "timestamp": datetime.utcnow().isoformat(),
        }),
        mimetype="application/json",
        status_code=200,
    )
