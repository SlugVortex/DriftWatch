<?php
// app/Models/DeploymentDecision.php
// Stores the output of Agent 3 (Negotiator) - deploy gate decision.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'pull_request_id',
        'decision',
        'decided_by',
        'has_concurrent_deploys',
        'in_freeze_window',
        'notified_oncall',
        'notification_message',
        'mrp_payload',
        'mrp_version',
        'weather_score',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'has_concurrent_deploys' => 'boolean',
            'in_freeze_window' => 'boolean',
            'notified_oncall' => 'boolean',
            'mrp_payload' => 'array',
            'mrp_version' => 'integer',
            'weather_score' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function getDecisionColorAttribute(): string
    {
        return match ($this->decision) {
            'approved' => 'success',
            'blocked' => 'danger',
            'pending_review' => 'warning',
            default => 'secondary',
        };
    }
}
