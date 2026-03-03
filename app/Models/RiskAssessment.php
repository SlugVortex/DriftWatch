<?php
// app/Models/RiskAssessment.php
// Stores the output of Agent 2 (Historian) - risk score based on historical incident data.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pull_request_id',
        'risk_score',
        'risk_level',
        'historical_incidents',
        'contributing_factors',
        'recommendation',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'historical_incidents' => 'array',
        'contributing_factors' => 'array',
    ];

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * Returns Bootstrap color class for the risk level.
     */
    public function getRiskColorAttribute(): string
    {
        return match ($this->risk_level) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'success',
            default => 'secondary',
        };
    }
}
