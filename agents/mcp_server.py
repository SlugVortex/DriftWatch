# agents/mcp_server.py
# DriftWatch — Azure MCP Server (Model Context Protocol)
#
# Provides agents with structured tool access to GitHub and the database.
# Implements the MCP pattern: agents call tools through this server rather
# than making raw API calls, enabling tool discovery, validation, and auditing.
#
# Usage:
#   python mcp_server.py                     # Start MCP server (default port 8100)
#   MCP_PORT=8200 python mcp_server.py       # Custom port
#
# Tools provided:
#   GitHub Read:  read_pr_diff, read_file, list_files, get_pr, get_check_runs
#   GitHub Write: post_comment, create_issue, create_check_run, update_file
#   Database:     query_incidents, query_outcomes

import os
import json
import logging
import base64
from datetime import datetime

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("driftwatch.mcp")

# ---------------------------------------------------------------------------
# Try FastMCP (preferred), fall back to basic HTTP server
# ---------------------------------------------------------------------------

try:
    from fastmcp import FastMCP
    _fastmcp_available = True
except ImportError:
    _fastmcp_available = False
    logger.info("[MCP] FastMCP not installed — using standalone HTTP server mode.")

try:
    import httpx
except ImportError:
    import urllib.request
    httpx = None


# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

GITHUB_TOKEN = os.environ.get("GITHUB_TOKEN", "")
GITHUB_API = "https://api.github.com"
LARAVEL_URL = os.environ.get("LARAVEL_APP_URL", "http://localhost:8000")


def _github_headers(accept: str = "application/json") -> dict:
    """Standard GitHub API headers with token auth."""
    return {
        "Authorization": f"token {GITHUB_TOKEN}",
        "Accept": accept,
        "User-Agent": "DriftWatch-MCP-Server/1.0",
    }


def _github_get(path: str, accept: str = "application/json") -> dict | str | None:
    """Makes a GET request to GitHub API."""
    url = f"{GITHUB_API}{path}"
    if httpx:
        with httpx.Client(timeout=30) as client:
            resp = client.get(url, headers=_github_headers(accept))
            if resp.status_code == 200:
                if "json" in accept:
                    return resp.json()
                return resp.text
            logger.warning(f"[MCP GitHub] GET {path} → {resp.status_code}")
            return None
    else:
        req = urllib.request.Request(url, headers=_github_headers(accept))
        with urllib.request.urlopen(req, timeout=30) as resp:
            data = resp.read().decode()
            if "json" in accept:
                return json.loads(data)
            return data


def _github_post(path: str, body: dict) -> dict | None:
    """Makes a POST request to GitHub API."""
    url = f"{GITHUB_API}{path}"
    if httpx:
        with httpx.Client(timeout=15) as client:
            resp = client.post(url, headers=_github_headers(), json=body)
            if resp.status_code in (200, 201):
                return resp.json()
            logger.warning(f"[MCP GitHub] POST {path} → {resp.status_code}: {resp.text[:200]}")
            return None
    return None


# ---------------------------------------------------------------------------
# GitHub Read Tools
# ---------------------------------------------------------------------------

def read_pr_diff(repo: str, pr_number: int) -> str:
    """
    MCP Tool: read_pr_diff
    Fetches the unified diff for a pull request.

    Args:
        repo: Repository full name (e.g., "owner/repo")
        pr_number: Pull request number

    Returns:
        Unified diff text
    """
    result = _github_get(
        f"/repos/{repo}/pulls/{pr_number}",
        accept="application/vnd.github.v3.diff"
    )
    return result or ""


def read_file(repo: str, path: str, ref: str = "main") -> str:
    """
    MCP Tool: read_file
    Fetches file contents from a repository at a specific ref (branch/SHA).

    Args:
        repo: Repository full name
        path: File path within the repository
        ref: Git ref (branch name or commit SHA)

    Returns:
        Decoded file contents (UTF-8)
    """
    result = _github_get(f"/repos/{repo}/contents/{path}?ref={ref}")
    if result and isinstance(result, dict):
        if result.get("encoding") == "base64" and result.get("content"):
            return base64.b64decode(result["content"]).decode("utf-8", errors="replace")
    return ""


def list_files(repo: str, pr_number: int) -> list:
    """
    MCP Tool: list_files
    Lists all changed files in a pull request with patch data.

    Args:
        repo: Repository full name
        pr_number: Pull request number

    Returns:
        List of file objects with filename, status, additions, deletions, patch
    """
    result = _github_get(f"/repos/{repo}/pulls/{pr_number}/files?per_page=100")
    if result and isinstance(result, list):
        return [
            {
                "filename": f.get("filename", ""),
                "status": f.get("status", ""),
                "additions": f.get("additions", 0),
                "deletions": f.get("deletions", 0),
                "changes": f.get("changes", 0),
                "patch": (f.get("patch", "") or "")[:3000],
            }
            for f in result
        ]
    return []


def get_pr(repo: str, pr_number: int) -> dict:
    """
    MCP Tool: get_pr
    Fetches pull request metadata (title, author, branch, stats, head SHA).

    Args:
        repo: Repository full name
        pr_number: Pull request number

    Returns:
        PR metadata object
    """
    result = _github_get(f"/repos/{repo}/pulls/{pr_number}")
    if result and isinstance(result, dict):
        return {
            "number": result.get("number"),
            "title": result.get("title", ""),
            "state": result.get("state", ""),
            "author": result.get("user", {}).get("login", ""),
            "head_sha": result.get("head", {}).get("sha", ""),
            "head_ref": result.get("head", {}).get("ref", ""),
            "base_ref": result.get("base", {}).get("ref", ""),
            "additions": result.get("additions", 0),
            "deletions": result.get("deletions", 0),
            "changed_files": result.get("changed_files", 0),
            "created_at": result.get("created_at", ""),
            "updated_at": result.get("updated_at", ""),
        }
    return {}


def get_check_runs(repo: str, sha: str) -> list:
    """
    MCP Tool: get_check_runs
    Fetches CI check runs for a specific commit SHA.

    Args:
        repo: Repository full name
        sha: Commit SHA

    Returns:
        List of check run objects with name, status, conclusion
    """
    result = _github_get(f"/repos/{repo}/commits/{sha}/check-runs?per_page=50")
    if result and isinstance(result, dict):
        return [
            {
                "name": c.get("name", ""),
                "status": c.get("status", ""),
                "conclusion": c.get("conclusion", ""),
                "started_at": c.get("started_at", ""),
                "completed_at": c.get("completed_at", ""),
            }
            for c in result.get("check_runs", [])
        ]
    return []


# ---------------------------------------------------------------------------
# GitHub Write Tools
# ---------------------------------------------------------------------------

def post_comment(repo: str, pr_number: int, body: str) -> dict:
    """
    MCP Tool: post_comment
    Posts a comment on a pull request / issue.

    Args:
        repo: Repository full name
        pr_number: PR / issue number
        body: Comment body (markdown)

    Returns:
        Created comment object with id and html_url
    """
    result = _github_post(f"/repos/{repo}/issues/{pr_number}/comments", {"body": body})
    if result:
        return {"id": result.get("id"), "html_url": result.get("html_url", "")}
    return {"error": "Failed to post comment"}


def create_issue(repo: str, title: str, body: str, labels: list = None) -> dict:
    """
    MCP Tool: create_issue
    Creates a GitHub Issue (used for Copilot Agent Mode remediation).

    Args:
        repo: Repository full name
        title: Issue title
        body: Issue body (markdown)
        labels: List of label strings

    Returns:
        Created issue object with number and html_url
    """
    payload = {"title": title, "body": body}
    if labels:
        payload["labels"] = labels
    result = _github_post(f"/repos/{repo}/issues", payload)
    if result:
        return {"number": result.get("number"), "html_url": result.get("html_url", "")}
    return {"error": "Failed to create issue"}


def create_check_run(repo: str, sha: str, name: str, status: str,
                     conclusion: str = None, summary: str = "") -> dict:
    """
    MCP Tool: create_check_run
    Creates a GitHub Check Run on a commit (pass/fail/neutral).

    Args:
        repo: Repository full name
        sha: Commit SHA
        name: Check run name (e.g., "DriftWatch Risk Assessment")
        status: "queued", "in_progress", or "completed"
        conclusion: "success", "failure", "neutral", "action_required"
        summary: Check run summary text

    Returns:
        Created check run object
    """
    payload = {"name": name, "head_sha": sha, "status": status}
    if conclusion:
        payload["conclusion"] = conclusion
    if summary:
        payload["output"] = {
            "title": name,
            "summary": summary[:65535],
        }
    result = _github_post(f"/repos/{repo}/check-runs", payload)
    if result:
        return {"id": result.get("id"), "html_url": result.get("html_url", "")}
    return {"error": "Failed to create check run"}


# ---------------------------------------------------------------------------
# Database Tools (via Laravel API)
# ---------------------------------------------------------------------------

def query_incidents(services: list = None, days: int = 90) -> list:
    """
    MCP Tool: query_incidents
    Queries historical incidents from the DriftWatch database via Laravel API.
    Optionally filters by affected services and time window.

    Args:
        services: List of service names to filter by (optional)
        days: Number of days to look back (default: 90)

    Returns:
        List of incident objects with title, severity, affected_services, occurred_at
    """
    try:
        url = f"{LARAVEL_URL}/api/incidents"
        if httpx:
            with httpx.Client(timeout=10) as client:
                resp = client.get(url)
                if resp.status_code == 200:
                    incidents = resp.json()
                    # Filter by services if specified
                    if services:
                        filtered = []
                        for inc in incidents:
                            inc_services = inc.get("affected_services", [])
                            if isinstance(inc_services, str):
                                inc_services = json.loads(inc_services) if inc_services.startswith("[") else [inc_services]
                            if any(s in inc_services for s in services):
                                filtered.append(inc)
                        return filtered
                    return incidents
    except Exception as e:
        logger.warning(f"[MCP DB] query_incidents failed: {e}")
    return []


def query_outcomes(pr_number: int = None, limit: int = 50) -> list:
    """
    MCP Tool: query_outcomes
    Queries past deployment outcomes and prediction accuracy data.
    Used by the Chronicler's feedback loop.

    Args:
        pr_number: Optional PR number to filter by
        limit: Maximum results to return

    Returns:
        List of outcome objects with predicted_score, actual_severity, prediction_accurate
    """
    try:
        url = f"{LARAVEL_URL}/api/outcomes"
        if pr_number:
            url += f"?pr_number={pr_number}"
        if httpx:
            with httpx.Client(timeout=10) as client:
                resp = client.get(url)
                if resp.status_code == 200:
                    return resp.json()[:limit]
    except Exception as e:
        logger.warning(f"[MCP DB] query_outcomes failed: {e}")
    return []


# ---------------------------------------------------------------------------
# MCP Server Setup
# ---------------------------------------------------------------------------

# Tool registry for discovery
TOOL_REGISTRY = {
    "github_read": {
        "read_pr_diff": {
            "description": "Fetch unified diff for a pull request",
            "parameters": {"repo": "string", "pr_number": "integer"},
            "function": read_pr_diff,
        },
        "read_file": {
            "description": "Fetch file contents from repository at specific ref",
            "parameters": {"repo": "string", "path": "string", "ref": "string"},
            "function": read_file,
        },
        "list_files": {
            "description": "List changed files in a pull request with patches",
            "parameters": {"repo": "string", "pr_number": "integer"},
            "function": list_files,
        },
        "get_pr": {
            "description": "Fetch PR metadata (title, author, branch, stats)",
            "parameters": {"repo": "string", "pr_number": "integer"},
            "function": get_pr,
        },
        "get_check_runs": {
            "description": "Fetch CI check runs for a commit SHA",
            "parameters": {"repo": "string", "sha": "string"},
            "function": get_check_runs,
        },
    },
    "github_write": {
        "post_comment": {
            "description": "Post comment on a PR / issue",
            "parameters": {"repo": "string", "pr_number": "integer", "body": "string"},
            "function": post_comment,
        },
        "create_issue": {
            "description": "Create GitHub Issue for Copilot Agent Mode",
            "parameters": {"repo": "string", "title": "string", "body": "string", "labels": "array"},
            "function": create_issue,
        },
        "create_check_run": {
            "description": "Create GitHub Check Run (pass/fail)",
            "parameters": {"repo": "string", "sha": "string", "name": "string", "status": "string"},
            "function": create_check_run,
        },
    },
    "database": {
        "query_incidents": {
            "description": "Query historical incidents (90-day window)",
            "parameters": {"services": "array", "days": "integer"},
            "function": query_incidents,
        },
        "query_outcomes": {
            "description": "Query deployment outcome prediction accuracy",
            "parameters": {"pr_number": "integer", "limit": "integer"},
            "function": query_outcomes,
        },
    },
}


def create_fastmcp_server() -> "FastMCP":
    """Creates a FastMCP server with all DriftWatch tools registered."""
    mcp = FastMCP(
        "DriftWatch MCP Server",
        description="Model Context Protocol server for DriftWatch agents — provides GitHub and database tools.",
    )

    # Register GitHub Read tools
    mcp.tool()(read_pr_diff)
    mcp.tool()(read_file)
    mcp.tool()(list_files)
    mcp.tool()(get_pr)
    mcp.tool()(get_check_runs)

    # Register GitHub Write tools
    mcp.tool()(post_comment)
    mcp.tool()(create_issue)
    mcp.tool()(create_check_run)

    # Register Database tools
    mcp.tool()(query_incidents)
    mcp.tool()(query_outcomes)

    logger.info("[MCP] FastMCP server created with 10 tools (5 read, 3 write, 2 database).")
    return mcp


def run_http_server(port: int = 8100):
    """Runs a basic HTTP server for tool discovery and invocation."""
    from http.server import HTTPServer, BaseHTTPRequestHandler

    class MCPHandler(BaseHTTPRequestHandler):
        def do_GET(self):
            """Tool discovery endpoint."""
            if self.path == "/tools" or self.path == "/":
                tools = {}
                for category, category_tools in TOOL_REGISTRY.items():
                    for name, tool_def in category_tools.items():
                        tools[name] = {
                            "category": category,
                            "description": tool_def["description"],
                            "parameters": tool_def["parameters"],
                        }
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps({
                    "server": "DriftWatch MCP Server",
                    "version": "1.0.0",
                    "tools": tools,
                    "tool_count": len(tools),
                }).encode())
            elif self.path == "/health":
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps({
                    "status": "healthy",
                    "github_token": "configured" if GITHUB_TOKEN else "missing",
                    "laravel_url": LARAVEL_URL,
                }).encode())
            else:
                self.send_response(404)
                self.end_headers()

        def do_POST(self):
            """Tool invocation endpoint: POST /invoke/{tool_name}"""
            if self.path.startswith("/invoke/"):
                tool_name = self.path.split("/invoke/")[1]
                content_length = int(self.headers.get("Content-Length", 0))
                body = json.loads(self.rfile.read(content_length)) if content_length > 0 else {}

                # Find the tool
                tool_func = None
                for category_tools in TOOL_REGISTRY.values():
                    if tool_name in category_tools:
                        tool_func = category_tools[tool_name]["function"]
                        break

                if tool_func:
                    try:
                        result = tool_func(**body)
                        self.send_response(200)
                        self.send_header("Content-Type", "application/json")
                        self.end_headers()
                        self.wfile.write(json.dumps({"result": result}).encode())
                    except Exception as e:
                        self.send_response(500)
                        self.send_header("Content-Type", "application/json")
                        self.end_headers()
                        self.wfile.write(json.dumps({"error": str(e)}).encode())
                else:
                    self.send_response(404)
                    self.send_header("Content-Type", "application/json")
                    self.end_headers()
                    self.wfile.write(json.dumps({"error": f"Tool '{tool_name}' not found"}).encode())
            else:
                self.send_response(404)
                self.end_headers()

        def log_message(self, format, *args):
            logger.info(f"[MCP HTTP] {format % args}")

    server = HTTPServer(("0.0.0.0", port), MCPHandler)
    logger.info(f"[MCP] HTTP server running on port {port}")
    logger.info(f"[MCP] Tool discovery:    GET  http://localhost:{port}/tools")
    logger.info(f"[MCP] Tool invocation:   POST http://localhost:{port}/invoke/{{tool_name}}")
    logger.info(f"[MCP] Health check:      GET  http://localhost:{port}/health")
    server.serve_forever()


if __name__ == "__main__":
    port = int(os.environ.get("MCP_PORT", "8100"))

    if _fastmcp_available:
        logger.info("[MCP] Starting FastMCP server...")
        server = create_fastmcp_server()
        server.run()
    else:
        logger.info("[MCP] Starting HTTP MCP server...")
        run_http_server(port)
