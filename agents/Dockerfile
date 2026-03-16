# agents/Dockerfile
# DriftWatch AI Agents — Azure Functions V2 (Python)
#
# Runs the 7 agent endpoints: archaeologist, historian, negotiator,
# chronicler, navigator, sre, health
#
# Usage:
#   docker build -t driftwatch-agents .
#   docker run -p 7071:7071 --env-file ../.env driftwatch-agents

FROM mcr.microsoft.com/azure-functions/python:4-python3.11

ENV AzureWebJobsScriptRoot=/home/site/wwwroot \
    AzureFunctionsJobHost__Logging__Console__IsEnabled=true

WORKDIR /home/site/wwwroot

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy agent code
COPY function_app.py .
COPY register_agents.py .
COPY host.json* .
COPY local.settings.json* .

EXPOSE 7071
