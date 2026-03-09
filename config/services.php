<?php

// config/services.php
// Third party service credentials including GitHub and DriftWatch AI agent URLs.

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // DriftWatch: GitHub integration
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'token' => env('GITHUB_TOKEN'),
    ],

    // DriftWatch: Microsoft Teams incoming webhook for deploy alerts
    'teams' => [
        'webhook_url' => env('TEAMS_WEBHOOK_URL'),
        'notify_above_score' => (int) env('TEAMS_NOTIFY_ABOVE_SCORE', 60),
    ],

    // DriftWatch: AI Agent Azure Function URLs
    'agents' => [
        'archaeologist_url' => env('AGENT_ARCHAEOLOGIST_URL'),
        'historian_url' => env('AGENT_HISTORIAN_URL'),
        'negotiator_url' => env('AGENT_NEGOTIATOR_URL'),
        'chronicler_url' => env('AGENT_CHRONICLER_URL'),
        'navigator_url' => env('AGENT_NAVIGATOR_URL'),
        'security_url' => env('AGENT_SECURITY_URL'),
        'function_key' => env('AZURE_FUNCTION_KEY'),
    ],

    // Azure Speech (Text-to-Speech)
    'azure_speech' => [
        'endpoint' => env('AZURE_SPEECH_ENDPOINT'),
        'key' => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION', 'eastus'),
    ],

    // Azure AI Search (RAG for Security Agent)
    'azure_ai_search' => [
        'endpoint' => env('AZURE_AI_SEARCH_ENDPOINT'),
        'key' => env('AZURE_AI_SEARCH_KEY'),
        'index' => env('AZURE_AI_SEARCH_INDEX', 'security-knowledge'),
    ],

    // Azure OpenAI
    'azure_openai' => [
        'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
        'api_key' => env('AZURE_OPENAI_API_KEY'),
        'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4.1-mini'),
    ],

    // Application Insights
    'app_insights' => [
        'connection_string' => env('APPLICATIONINSIGHTS_CONNECTION_STRING'),
    ],

    // Azure AI Content Safety
    'content_safety' => [
        'endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
        'api_key' => env('AZURE_CONTENT_SAFETY_KEY'),
    ],

    // Azure Key Vault
    'key_vault' => [
        'vault_url' => env('AZURE_KEY_VAULT_URL'),
    ],

    // Semantic Kernel (orchestration pattern config)
    'semantic_kernel' => [
        'planner' => 'sequential',
        'memory_store' => 'azure_mysql',
        'skills' => ['blast_radius', 'risk_scoring', 'deploy_gate', 'feedback_loop'],
    ],

    // Azure AI Foundry
    'azure_ai_foundry' => [
        'endpoint' => env('AZURE_AI_FOUNDRY_ENDPOINT'),
        'project' => env('AZURE_AI_FOUNDRY_PROJECT', 'driftwatch'),
    ],

    // Azure Service Bus
    'service_bus' => [
        'connection_string' => env('AZURE_SERVICE_BUS_CONNECTION'),
        'queue_name' => env('AZURE_SERVICE_BUS_QUEUE', 'agent-pipeline'),
    ],

];
