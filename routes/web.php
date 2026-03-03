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
    Route::get('/incidents', [DriftWatchController::class, 'incidents'])->name('incidents');
    Route::get('/analytics', [DriftWatchController::class, 'analytics'])->name('analytics');
    Route::get('/settings', [DriftWatchController::class, 'settings'])->name('settings');

    // Agent status pages
    Route::get('/agents/archaeologist', [DriftWatchController::class, 'agentStatus'])->name('agents.archaeologist')->defaults('agent', 'archaeologist');
    Route::get('/agents/historian', [DriftWatchController::class, 'agentStatus'])->name('agents.historian')->defaults('agent', 'historian');
    Route::get('/agents/negotiator', [DriftWatchController::class, 'agentStatus'])->name('agents.negotiator')->defaults('agent', 'negotiator');
    Route::get('/agents/chronicler', [DriftWatchController::class, 'agentStatus'])->name('agents.chronicler')->defaults('agent', 'chronicler');
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
