<?php
// routes/api.php
// DriftWatch API routes - used by the Python AI agents to fetch incident data.

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Incidents API - used by the Historian agent to fetch historical data
Route::get('/incidents', function (Request $request) {
    $services = array_filter(explode(',', $request->get('services', '')));

    $query = Incident::query();

    if (!empty($services)) {
        $query->where(function ($q) use ($services) {
            foreach ($services as $service) {
                $q->orWhereJsonContains('affected_services', trim($service));
            }
        });
    }

    $incidents = $query->where('occurred_at', '>=', now()->subDays(90))
        ->orderByDesc('occurred_at')
        ->limit(20)
        ->get();

    return response()->json($incidents);
});
