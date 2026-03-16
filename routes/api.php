<?php

// routes/api.php
// DriftWatch API routes - used by the Python AI agents, GitHub Action, and external integrations.

use App\Http\Controllers\GitHubWebhookController;
use App\Models\DeploymentDecision;
use App\Models\Incident;
use App\Models\PullRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Incidents API - used by the Historian agent to fetch historical data
Route::get('/incidents', function (Request $request) {
    $services = array_filter(explode(',', $request->get('services', '')));

    $query = Incident::query();

    if (! empty($services)) {
        $query->where(function ($q) use ($services) {
            foreach ($services as $service) {
                $q->orWhereJsonContains('affected_services', trim($service));
            }
        });
    }

    $incidents = $query->where('occurred_at', '>=', now()->subDays(90))
        ->orderByDesc('occurred_at')
        ->limit(30)
        ->get();

    return response()->json($incidents);
});

// === GitHub Action API Endpoints ===

// POST /api/analyze — Trigger analysis for a PR (used by driftwatch-analyze GitHub Action)
Route::post('/analyze', function (Request $request) {
    $request->validate([
        'pr_number' => ['required', 'integer'],
        'repo_full_name' => ['required', 'string', 'regex:#^[^/]+/[^/]+$#'],
    ]);

    $prNumber = $request->input('pr_number');
    $repoFullName = $request->input('repo_full_name');

    Log::info('[API:analyze] Analysis requested.', ['repo' => $repoFullName, 'pr' => $prNumber]);

    // Fetch PR data from GitHub API
    $ghToken = config('services.github.token');
    $prData = null;

    try {
        $ghResponse = Http::withHeaders([
            'Authorization' => "Bearer {$ghToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->timeout(15)->get("https://api.github.com/repos/{$repoFullName}/pulls/{$prNumber}");

        if ($ghResponse->successful()) {
            $prData = $ghResponse->json();
        }
    } catch (\Exception $e) {
        Log::warning('[API:analyze] GitHub API fetch failed.', ['error' => $e->getMessage()]);
    }

    // Create or update the PR record
    $pullRequest = PullRequest::updateOrCreate(
        [
            'repo_full_name' => $repoFullName,
            'pr_number' => $prNumber,
        ],
        [
            'github_pr_id' => (string) ($prData['id'] ?? "{$repoFullName}#{$prNumber}"),
            'pr_title' => $prData['title'] ?? "PR #{$prNumber}",
            'pr_author' => $prData['user']['login'] ?? 'unknown',
            'pr_url' => $prData['html_url'] ?? "https://github.com/{$repoFullName}/pull/{$prNumber}",
            'base_branch' => $prData['base']['ref'] ?? 'main',
            'head_branch' => $prData['head']['ref'] ?? 'unknown',
            'files_changed' => $prData['changed_files'] ?? 0,
            'additions' => $prData['additions'] ?? 0,
            'deletions' => $prData['deletions'] ?? 0,
            'status' => 'analyzing',
        ]
    );

    // Run the agent pipeline synchronously
    $webhookController = app(GitHubWebhookController::class);
    $webhookController->runAgentPipelinePublic($pullRequest);

    $pullRequest->refresh();
    $pullRequest->load(['riskAssessment', 'deploymentDecision', 'blastRadius']);

    return response()->json([
        'job_id' => $pullRequest->id,
        'status' => 'completed',
        'pr_id' => $pullRequest->id,
        'risk_score' => $pullRequest->riskAssessment?->risk_score ?? 0,
        'risk_level' => $pullRequest->riskAssessment?->risk_level ?? 'unknown',
        'decision' => $pullRequest->deploymentDecision?->decision ?? 'pending_review',
        'affected_services' => $pullRequest->blastRadius?->affected_services ?? [],
        'summary' => $pullRequest->blastRadius?->summary ?? 'Analysis complete.',
    ]);
});

// GET /api/jobs/{id}/status — Poll job status (used by GitHub Action + live UI polling)
Route::get('/jobs/{id}/status', function (int $id) {
    $pullRequest = PullRequest::with(['riskAssessment', 'deploymentDecision', 'blastRadius', 'deploymentOutcome'])->find($id);

    if (! $pullRequest) {
        return response()->json(['status' => 'not_found', 'error' => 'Job not found'], 404);
    }

    $isPaused = (bool) $pullRequest->pipeline_paused;
    $isComplete = $pullRequest->status !== 'analyzing' || $isPaused;

    // Determine pipeline status label
    $statusLabel = match (true) {
        $isPaused => 'paused',
        $pullRequest->status === 'analyzing' => 'processing',
        default => 'completed',
    };

    return response()->json([
        'status' => $statusLabel,
        'pr_id' => $pullRequest->id,
        'pr_status' => $pullRequest->status,
        'pipeline_paused' => $isPaused,
        'paused_at_stage' => $pullRequest->paused_at_stage,
        'paused_reason' => $pullRequest->paused_reason,
        'pipeline_stage' => $pullRequest->pipeline_stage,
        'stage_started_at' => $pullRequest->stage_started_at?->toIso8601String(),
        'agents' => [
            'archaeologist' => (bool) $pullRequest->blastRadius,
            'historian' => (bool) $pullRequest->riskAssessment,
            'negotiator' => (bool) $pullRequest->deploymentDecision,
            'chronicler' => (bool) $pullRequest->deploymentOutcome,
        ],
        'risk_score' => $pullRequest->riskAssessment?->risk_score ?? 0,
        'risk_level' => $pullRequest->riskAssessment?->risk_level ?? 'unknown',
        'decision' => $pullRequest->deploymentDecision?->decision ?? 'pending_review',
        'affected_services' => $pullRequest->blastRadius?->affected_services ?? [],
        'summary' => $pullRequest->blastRadius?->summary ?? '',
        'redirect_url' => $isComplete ? route('driftwatch.show', $pullRequest) : null,
    ]);
});

// === File Preview — Fetch source code from GitHub for in-chat code inspection ===
Route::post('/file-preview', function (Request $request) {
    $request->validate([
        'pr_id' => ['required', 'integer'],
        'file_path' => ['required', 'string', 'max:500'],
    ]);

    $pullRequest = PullRequest::find($request->input('pr_id'));
    if (! $pullRequest) {
        return response()->json(['error' => 'PR not found'], 404);
    }

    $filePath = $request->input('file_path');
    $repoFullName = $pullRequest->repo_full_name;
    $headBranch = $pullRequest->head_branch ?: $pullRequest->base_branch ?: 'main';
    $ghToken = config('services.github.token');

    Log::info('[API:file-preview] Fetching file.', ['repo' => $repoFullName, 'file' => $filePath, 'branch' => $headBranch]);

    // Try to fetch the file content from GitHub
    if ($ghToken && $repoFullName && $repoFullName !== 'unknown/unknown') {
        try {
            // Try head branch first, fall back to default branch
            $ghResponse = Http::withHeaders([
                'Authorization' => "Bearer {$ghToken}",
                'Accept' => 'application/vnd.github.v3+json',
            ])->timeout(10)->get("https://api.github.com/repos/{$repoFullName}/contents/{$filePath}", [
                'ref' => $headBranch,
            ]);

            // If head branch fails, try without ref (default branch)
            if (! $ghResponse->successful() && $headBranch !== 'main') {
                $ghResponse = Http::withHeaders([
                    'Authorization' => "Bearer {$ghToken}",
                    'Accept' => 'application/vnd.github.v3+json',
                ])->timeout(10)->get("https://api.github.com/repos/{$repoFullName}/contents/{$filePath}");
            }

            if ($ghResponse->successful()) {
                $data = $ghResponse->json();
                $content = '';

                if (isset($data['content']) && isset($data['encoding']) && $data['encoding'] === 'base64') {
                    $content = base64_decode($data['content']);
                }

                // Truncate very large files
                if (strlen($content) > 50000) {
                    $content = substr($content, 0, 50000)."\n\n// ... truncated (file too large) ...";
                }

                // Also try to fetch the diff for this specific file
                $diffSnippet = '';
                try {
                    $diffResponse = Http::withHeaders([
                        'Authorization' => "Bearer {$ghToken}",
                        'Accept' => 'application/vnd.github.v3.diff',
                    ])->timeout(10)->get("https://api.github.com/repos/{$repoFullName}/pulls/{$pullRequest->pr_number}");

                    if ($diffResponse->successful()) {
                        $fullDiff = $diffResponse->body();
                        // Extract just this file's diff
                        $pattern = '/diff --git a\/'.preg_quote($filePath, '/').'.*?(?=diff --git|\z)/s';
                        if (preg_match($pattern, $fullDiff, $matches)) {
                            $diffSnippet = $matches[0];
                            // Truncate long diffs
                            if (strlen($diffSnippet) > 10000) {
                                $diffSnippet = substr($diffSnippet, 0, 10000)."\n... truncated ...";
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Diff fetch is optional
                }

                return response()->json([
                    'file_path' => $filePath,
                    'content' => $content,
                    'diff' => $diffSnippet,
                    'language' => pathinfo($filePath, PATHINFO_EXTENSION),
                    'size' => $data['size'] ?? strlen($content),
                    'sha' => $data['sha'] ?? '',
                    'source' => 'github',
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[API:file-preview] GitHub fetch failed.', ['error' => $e->getMessage()]);
        }
    }

    // Fallback: check if we have code_analysis stored from the pipeline
    $blastRadius = $pullRequest->blastRadius;
    $codeAnalysis = $blastRadius?->code_analysis ?? [];
    $fileContent = $codeAnalysis['file_contents'][$filePath] ?? null;

    if ($fileContent) {
        return response()->json([
            'file_path' => $filePath,
            'content' => $fileContent,
            'diff' => '',
            'language' => pathinfo($filePath, PATHINFO_EXTENSION),
            'size' => strlen($fileContent),
            'sha' => '',
            'source' => 'cached',
        ]);
    }

    // No content available
    return response()->json([
        'file_path' => $filePath,
        'content' => '',
        'diff' => '',
        'language' => pathinfo($filePath, PATHINFO_EXTENSION),
        'size' => 0,
        'sha' => '',
        'source' => 'unavailable',
        'message' => 'File content not available. Configure GITHUB_TOKEN to enable live code preview.',
    ]);
});

// === Impact Chat — Navigator Agent Endpoint ===
// POST /api/impact-chat — Conversational AI for exploring PR blast radius
Route::post('/impact-chat', function (Request $request) {
    $request->validate([
        'pr_id' => ['required', 'integer'],
        'query' => ['required', 'string', 'max:500'],
    ]);

    $prId = $request->input('pr_id');
    $query = $request->input('query');

    $pullRequest = PullRequest::with(['blastRadius', 'riskAssessment', 'deploymentDecision'])->find($prId);

    if (! $pullRequest) {
        return response()->json(['error' => 'PR not found'], 404);
    }

    Log::info('[API:impact-chat] Chat query received.', ['pr' => $prId, 'query' => $query]);

    // Build PR context payload for the Navigator agent
    $blastRadius = $pullRequest->blastRadius;
    $riskAssessment = $pullRequest->riskAssessment;
    $decision = $pullRequest->deploymentDecision;

    $prContext = [
        'pr_number' => $pullRequest->pr_number,
        'pr_title' => $pullRequest->pr_title,
        'pr_author' => $pullRequest->pr_author,
        'repo_full_name' => $pullRequest->repo_full_name,
        'files_changed' => $pullRequest->files_changed,
        'additions' => $pullRequest->additions,
        'deletions' => $pullRequest->deletions,
        'base_branch' => $pullRequest->base_branch,
        'head_branch' => $pullRequest->head_branch,
        'risk_score' => $riskAssessment?->risk_score ?? 0,
        'risk_level' => $riskAssessment?->risk_level ?? 'unknown',
        'decision' => $decision?->decision ?? 'pending',
        'affected_services' => $blastRadius?->affected_services ?? [],
        'changed_files' => $blastRadius?->change_classifications ?? $blastRadius?->affected_files ?? [],
        'dependency_graph' => $blastRadius?->dependency_graph ?? [],
        'file_summaries' => $blastRadius?->file_descriptions ?? [],
        'blast_summary' => $blastRadius?->summary ?? '',
        'recommendation' => $riskAssessment?->recommendation ?? '',
        'affected_endpoints' => $blastRadius?->affected_endpoints ?? [],
    ];

    // Try 1: Call the Navigator Azure Function agent
    $navigatorUrl = config('services.agents.navigator_url');
    $functionKey = config('services.agents.function_key');

    if ($navigatorUrl) {
        try {
            $agentResponse = Http::withHeaders(array_filter([
                'Content-Type' => 'application/json',
                'x-functions-key' => $functionKey,
            ]))->timeout(20)->post($navigatorUrl, [
                'query' => $query,
                'pr_context' => $prContext,
            ]);

            if ($agentResponse->successful()) {
                Log::info('[API:impact-chat] Navigator agent responded.');
                $agentData = $agentResponse->json();
                $agentData['source'] = 'agent';

                return response()->json($agentData);
            }
        } catch (\Exception $e) {
            Log::warning('[API:impact-chat] Navigator agent call failed.', ['error' => $e->getMessage()]);
        }
    }

    // Try 2: Direct Azure OpenAI call (same prompt as the Navigator agent)
    $aoaiEndpoint = config('services.azure_openai.endpoint');
    $aoaiKey = config('services.azure_openai.api_key');
    $aoaiDeployment = config('services.azure_openai.deployment');

    if ($aoaiEndpoint && $aoaiKey) {
        try {
            $contextStr = "PR: {$pullRequest->pr_title} (#{$pullRequest->pr_number})\n"
                ."Repo: {$pullRequest->repo_full_name}\n"
                ."Files Changed: {$pullRequest->files_changed}\n"
                .'Risk Score: '.($riskAssessment?->risk_score ?? 0).'/100 ('.($riskAssessment?->risk_level ?? 'unknown').")\n"
                .'Decision: '.($decision?->decision ?? 'pending')."\n"
                .'Services: '.implode(', ', $blastRadius?->affected_services ?? [])."\n"
                .'Summary: '.($blastRadius?->summary ?? '')."\n"
                .'Recommendation: '.($riskAssessment?->recommendation ?? '')."\n";

            $changedFiles = $blastRadius?->change_classifications ?? [];
            if (is_array($changedFiles)) {
                $contextStr .= "\nChanged Files:\n";
                foreach ($changedFiles as $cf) {
                    if (is_array($cf)) {
                        $contextStr .= "  {$cf['file']} (score: {$cf['risk_score']}, type: {$cf['change_type']})\n";
                    }
                }
            }

            $depGraph = $blastRadius?->dependency_graph ?? [];
            if (is_array($depGraph)) {
                $contextStr .= "\nDependency Graph:\n";
                foreach ($depGraph as $src => $deps) {
                    if (is_array($deps) && count($deps) > 0) {
                        $contextStr .= "  {$src} → ".implode(', ', $deps)."\n";
                    }
                }
            }

            $fileDescs = $blastRadius?->file_descriptions ?? [];
            if (is_array($fileDescs)) {
                $contextStr .= "\nFile Summaries:\n";
                foreach ($fileDescs as $path => $info) {
                    if (is_array($info)) {
                        $contextStr .= "  {$path}: ".($info['summary'] ?? '')."\n";
                    }
                }
            }

            $systemPrompt = 'You are the DriftWatch Navigator — an AI assistant that helps DevOps engineers understand PR impact. '
                .'Answer concisely (2-4 sentences). Reference specific files and risk scores. '
                .'Return JSON: {"response": "your answer (markdown ok)", "highlight_nodes": ["file_path1"], "suggested_followups": ["q1", "q2"]}';

            $aoaiResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'api-key' => $aoaiKey,
            ])->timeout(25)->post("{$aoaiEndpoint}/openai/deployments/{$aoaiDeployment}/chat/completions?api-version=2025-03-01-preview", [
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "PR Context:\n{$contextStr}\n\nUser Question: {$query}"],
                ],
                'temperature' => 0.2,
                'max_tokens' => 1500,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($aoaiResponse->successful()) {
                $aiResult = $aoaiResponse->json();
                $content = $aiResult['choices'][0]['message']['content'] ?? '{}';
                $parsed = json_decode($content, true) ?? [];

                Log::info('[API:impact-chat] Azure OpenAI direct response.');

                return response()->json([
                    'response' => $parsed['response'] ?? 'Analysis complete.',
                    'highlight_nodes' => $parsed['highlight_nodes'] ?? [],
                    'suggested_followups' => $parsed['suggested_followups'] ?? [],
                    'source' => 'openai',
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[API:impact-chat] Azure OpenAI direct call failed.', ['error' => $e->getMessage()]);
        }
    }

    // Try 3: Smart local mock (always works, no AI needed)
    Log::info('[API:impact-chat] Using local mock response.');

    $mockResponse = app(GitHubWebhookController::class)->generateImpactChatMock($query, $prContext);
    $mockResponse['source'] = 'local';

    return response()->json($mockResponse);
});

// === Decision Callback Endpoints (for Teams notifications) ===

Route::get('/decisions/{id}/approve', function (Request $request, int $id) {
    $decision = DeploymentDecision::with('pullRequest')->findOrFail($id);
    $token = $request->query('token');
    $expectedToken = hash_hmac('sha256', "decision-{$id}", config('app.key'));

    if (! $token || ! hash_equals($expectedToken, $token)) {
        return response('Invalid or missing token.', 403);
    }

    // Append to MRP audit trail
    $mrpPayload = $decision->mrp_payload ?? [];
    $mrpPayload['audit_trail'] = $mrpPayload['audit_trail'] ?? [];
    $mrpPayload['audit_trail'][] = [
        'action' => 'approved',
        'by' => $request->query('approver', 'Teams Callback User'),
        'at' => now()->toIso8601String(),
        'details' => 'Human decision: APPROVED via Teams notification callback.',
    ];

    $decision->update([
        'decision' => 'approved',
        'decided_by' => $request->query('approver', 'Teams Callback'),
        'decided_at' => now(),
        'mrp_payload' => $mrpPayload,
    ]);

    $decision->pullRequest->update(['status' => 'approved']);

    // Resume pipeline if it was paused at approval gate
    if ($decision->pullRequest->pipeline_paused) {
        $webhookController = app(\App\Http\Controllers\GitHubWebhookController::class);
        $webhookController->resumePipeline($decision->pullRequest);
    }

    Log::info("[API:decision] PR #{$decision->pullRequest->pr_number} APPROVED via Teams callback.");

    return response()->view('driftwatch.decision-confirmed', [
        'action' => 'APPROVED',
        'prNumber' => $decision->pullRequest->pr_number,
    ]);
});

Route::get('/decisions/{id}/block', function (Request $request, int $id) {
    $decision = DeploymentDecision::with('pullRequest')->findOrFail($id);
    $token = $request->query('token');
    $expectedToken = hash_hmac('sha256', "decision-{$id}", config('app.key'));

    if (! $token || ! hash_equals($expectedToken, $token)) {
        return response('Invalid or missing token.', 403);
    }

    // Append to MRP audit trail
    $mrpPayload = $decision->mrp_payload ?? [];
    $mrpPayload['audit_trail'] = $mrpPayload['audit_trail'] ?? [];
    $mrpPayload['audit_trail'][] = [
        'action' => 'blocked',
        'by' => $request->query('approver', 'Teams Callback User'),
        'at' => now()->toIso8601String(),
        'details' => 'Human decision: BLOCKED via Teams notification callback.',
    ];

    $decision->update([
        'decision' => 'blocked',
        'decided_by' => $request->query('approver', 'Teams Callback'),
        'decided_at' => now(),
        'mrp_payload' => $mrpPayload,
    ]);

    $decision->pullRequest->update(['status' => 'blocked']);

    Log::info("[API:decision] PR #{$decision->pullRequest->pr_number} BLOCKED via Teams callback.");

    return response()->view('driftwatch.decision-confirmed', [
        'action' => 'BLOCKED',
        'prNumber' => $decision->pullRequest->pr_number,
    ]);
});

// POST /api/file-update — Push code edits back to GitHub (PR author only)
Route::post('/file-update', function (Request $request) {
    $request->validate([
        'pr_id' => ['required', 'integer'],
        'file_path' => ['required', 'string', 'max:500'],
        'content' => ['required', 'string'],
        'sha' => ['required', 'string'],
        'commit_message' => ['nullable', 'string', 'max:300'],
    ]);

    $pullRequest = PullRequest::find($request->input('pr_id'));
    if (! $pullRequest) {
        return response()->json(['error' => 'PR not found'], 404);
    }

    $ghToken = config('services.github.token');
    if (! $ghToken) {
        return response()->json(['error' => 'GitHub token not configured'], 503);
    }

    $filePath = $request->input('file_path');
    $content = $request->input('content');
    $sha = $request->input('sha');
    $commitMsg = $request->input('commit_message', "Update {$filePath} via DriftWatch");
    $branch = $pullRequest->head_branch ?: 'main';
    $repoFullName = $pullRequest->repo_full_name;

    Log::info('[API:file-update] Pushing code edit.', [
        'repo' => $repoFullName,
        'file' => $filePath,
        'branch' => $branch,
    ]);

    try {
        $ghResponse = Http::withHeaders([
            'Authorization' => "Bearer {$ghToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->timeout(15)->put("https://api.github.com/repos/{$repoFullName}/contents/{$filePath}", [
            'message' => $commitMsg,
            'content' => base64_encode($content),
            'sha' => $sha,
            'branch' => $branch,
        ]);

        if ($ghResponse->successful()) {
            $data = $ghResponse->json();
            Log::info('[API:file-update] File pushed successfully.', ['sha' => $data['content']['sha'] ?? '']);

            return response()->json([
                'success' => true,
                'new_sha' => $data['content']['sha'] ?? '',
                'commit_sha' => $data['commit']['sha'] ?? '',
                'message' => "File updated on branch {$branch}",
            ]);
        }

        Log::warning('[API:file-update] GitHub API rejected.', ['status' => $ghResponse->status(), 'body' => $ghResponse->body()]);

        return response()->json([
            'error' => 'GitHub rejected the update: '.$ghResponse->json('message', 'Unknown error'),
        ], $ghResponse->status());

    } catch (\Exception $e) {
        Log::error('[API:file-update] Exception.', ['error' => $e->getMessage()]);

        return response()->json(['error' => 'Failed to push to GitHub: '.$e->getMessage()], 500);
    }
});

// POST /api/review-all — Trigger sequential AI review of all PR files
Route::post('/review-all', function (Request $request) {
    $request->validate([
        'pr_id' => ['required', 'integer'],
        'file_path' => ['required', 'string', 'max:500'],
    ]);

    $pullRequest = PullRequest::with(['blastRadius', 'riskAssessment'])->find($request->input('pr_id'));
    if (! $pullRequest) {
        return response()->json(['error' => 'PR not found'], 404);
    }

    $filePath = $request->input('file_path');
    $blastRadius = $pullRequest->blastRadius;
    $classifications = $blastRadius?->change_classifications ?? [];
    $fileInfo = null;

    foreach ($classifications as $cf) {
        if (is_array($cf) && ($cf['file'] ?? '') === $filePath) {
            $fileInfo = $cf;
            break;
        }
    }

    $depGraph = $blastRadius?->dependency_graph ?? [];
    $fileDeps = $depGraph[$filePath] ?? [];
    $fileDescs = $blastRadius?->file_descriptions ?? [];
    $fileDesc = $fileDescs[$filePath] ?? [];

    $verdict = $fileInfo['verdict'] ?? (($fileInfo['risk_score'] ?? 0) >= 25 ? 'critical' : (($fileInfo['risk_score'] ?? 0) >= 10 ? 'warning' : 'safe'));
    $query = "Review the file '{$filePath}' decisively. "
        .'Risk score: '.($fileInfo['risk_score'] ?? 'unknown').'. '
        .'Change type: '.($fileInfo['change_type'] ?? 'unknown').'. '
        .'Current verdict: '.$verdict.'. '
        .'Dependencies: '.(is_array($fileDeps) ? implode(', ', $fileDeps) : 'none').'. '
        .'Give a DECISIVE verdict: start with "VERDICT: OK" or "VERDICT: FLAGGED". '
        .'Then list concrete findings as bullet points starting with [OK] or [ISSUE]. '
        .'Be specific — say what IS wrong or what IS safe, not what COULD be. '
        .'If there are security issues, say "SECURITY:" followed by the exact issue.';

    // Reuse the impact-chat logic
    $chatRequest = Request::create('/api/impact-chat', 'POST', [
        'pr_id' => $pullRequest->id,
        'query' => $query,
    ]);

    return app()->handle($chatRequest);
});

// POST /api/tts — Azure Speech text-to-speech proxy
Route::post('/tts', function (Request $request) {
    $text = $request->input('text', '');
    if (empty($text)) {
        return response()->json(['error' => 'No text provided'], 400);
    }

    $region = config('services.azure_speech.region');
    $key = config('services.azure_speech.key');

    if (empty($key) || empty($region)) {
        return response()->json(['error' => 'Azure Speech not configured'], 503);
    }

    // Truncate to 3000 chars for reasonable audio length
    $text = mb_substr(strip_tags($text), 0, 3000);

    $ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="en-US">'
        .'<voice name="en-US-JennyNeural">'
        .'<prosody rate="+10%">'
        .htmlspecialchars($text, ENT_XML1, 'UTF-8')
        .'</prosody></voice></speak>';

    try {
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $key,
            'Content-Type' => 'application/ssml+xml',
            'X-Microsoft-OutputFormat' => 'audio-16khz-128kbitrate-mono-mp3',
        ])->timeout(15)->withBody($ssml, 'application/ssml+xml')
            ->post("https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1");

        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', 'audio/mpeg');
        }

        Log::warning('[API:tts] Azure Speech failed.', ['status' => $response->status()]);

        return response()->json(['error' => 'Speech synthesis failed'], 502);
    } catch (\Exception $e) {
        Log::error('[API:tts] Exception.', ['error' => $e->getMessage()]);

        return response()->json(['error' => 'Speech service unavailable'], 503);
    }
});
