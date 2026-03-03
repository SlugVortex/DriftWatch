<?php
// app/Models/DeploymentOutcome.php
// Stores the output of Agent 4 (Chronicler) - post-deploy feedback for accuracy tracking.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentOutcome extends Model
{
    use HasFactory;

    protected $fillable = [
        'pull_request_id',
        'predicted_risk_score',
        'incident_occurred',
        'actual_severity',
        'actual_affected_services',
        'prediction_accurate',
        'post_mortem_notes',
    ];

    protected $casts = [
        'predicted_risk_score' => 'integer',
        'incident_occurred' => 'boolean',
        'actual_severity' => 'integer',
        'actual_affected_services' => 'array',
        'prediction_accurate' => 'boolean',
    ];

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }
}
