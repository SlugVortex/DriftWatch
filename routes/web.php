<?php

// routes/web.php
// DriftWatch web routes - dashboard, PR details, agent views, settings.

use App\Http\Controllers\DriftWatchController;
use App\Http\Controllers\GitHubWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DriftWatch Routes
|--------------------------------------------------------------------------
| Main application routes for the DriftWatch dashboard.
| Root redirects to the DriftWatch dashboard.
*/

// Root redirects to DriftWatch dashboard
Route::get('/', function () {
    return redirect()->route('driftwatch.index');
});

// DriftWatch Dashboard Routes
Route::prefix('driftwatch')->name('driftwatch.')->group(function () {
    Route::get('/', [DriftWatchController::class, 'index'])->name('index');
    Route::get('/pull-requests', [DriftWatchController::class, 'pullRequests'])->name('pull-requests');
    Route::get('/pr/{pullRequest}', [DriftWatchController::class, 'show'])->name('show');
    Route::post('/pr/{pullRequest}/approve', [DriftWatchController::class, 'approve'])->name('approve');
    Route::post('/pr/{pullRequest}/block', [DriftWatchController::class, 'block'])->name('block');
    Route::post('/analyze', [DriftWatchController::class, 'analyzePr'])->name('analyze');
    Route::post('/pr/{pullRequest}/reanalyze', [DriftWatchController::class, 'reanalyze'])->name('reanalyze');
    Route::post('/pr/{pullRequest}/resume-pipeline', [DriftWatchController::class, 'resumePipeline'])->name('resume-pipeline');
    Route::post('/pr/{pullRequest}/update-environment', [DriftWatchController::class, 'updateEnvironment'])->name('update-environment');
    Route::post('/pr/{pullRequest}/update-template', [DriftWatchController::class, 'updateTemplate'])->name('update-template');
    Route::get('/incidents', [DriftWatchController::class, 'incidents'])->name('incidents');
    Route::get('/analytics', [DriftWatchController::class, 'analytics'])->name('analytics');
    Route::get('/settings', [DriftWatchController::class, 'settings'])->name('settings');
    Route::post('/settings/pipeline', [DriftWatchController::class, 'savePipelineConfig'])->name('settings.pipeline');
    Route::post('/settings/pipeline/reset', [DriftWatchController::class, 'resetPipelineConfig'])->name('settings.pipeline.reset');

    // Agent status pages
    Route::get('/agents/archaeologist', [DriftWatchController::class, 'agentStatus'])->name('agents.archaeologist')->defaults('agent', 'archaeologist');
    Route::get('/agents/historian', [DriftWatchController::class, 'agentStatus'])->name('agents.historian')->defaults('agent', 'historian');
    Route::get('/agents/negotiator', [DriftWatchController::class, 'agentStatus'])->name('agents.negotiator')->defaults('agent', 'negotiator');
    Route::get('/agents/chronicler', [DriftWatchController::class, 'agentStatus'])->name('agents.chronicler')->defaults('agent', 'chronicler');

    // Agent Map visualization
    Route::get('/agent-map', [DriftWatchController::class, 'agentMap'])->name('agent-map');

    // Governance & Responsible AI
    Route::get('/governance', [DriftWatchController::class, 'governance'])->name('governance');

    // Explainability — how scoring works
    Route::get('/explainability', [DriftWatchController::class, 'explainability'])->name('explainability');

    // Repositories
    Route::get('/repositories', [DriftWatchController::class, 'repositories'])->name('repositories');
    Route::post('/repositories/connect', [DriftWatchController::class, 'connectRepository'])->name('repositories.connect');
    Route::get('/repositories/{repository}', [DriftWatchController::class, 'showRepository'])->name('repositories.show');
    Route::post('/repositories/{repository}/sync', [DriftWatchController::class, 'syncRepository'])->name('repositories.sync');
    Route::post('/repositories/{repository}/toggle-auto-analyze', [DriftWatchController::class, 'toggleAutoAnalyze'])->name('repositories.toggle-auto-analyze');
    Route::post('/repositories/{repository}/analyze-all', [DriftWatchController::class, 'analyzeAllPrs'])->name('repositories.analyze-all');
    Route::delete('/repositories/{repository}', [DriftWatchController::class, 'disconnectRepository'])->name('repositories.disconnect');
});

// GitHub Webhook (no auth - verified by signature)
Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])->name('webhooks.github');

// Legacy template routes (kept for reference)
Route::get('/login', function () {
    return view('login');
})->name('login');

Route::get('/register', function () {
    return view('register');
})->name('register');
