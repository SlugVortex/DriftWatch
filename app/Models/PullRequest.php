<?php
// app/Models/PullRequest.php
// Core model tracking every PR analyzed by DriftWatch's 4-agent pipeline.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PullRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'github_pr_id',
        'repo_full_name',
        'pr_number',
        'pr_title',
        'pr_author',
        'pr_url',
        'base_branch',
        'head_branch',
        'files_changed',
        'additions',
        'deletions',
        'status',
    ];

    protected $casts = [
        'files_changed' => 'integer',
        'additions' => 'integer',
        'deletions' => 'integer',
    ];

    // --- Relationships ---

    public function blastRadius(): HasOne
    {
        return $this->hasOne(BlastRadiusResult::class);
    }

    public function riskAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class);
    }

    public function deploymentDecision(): HasOne
    {
        return $this->hasOne(DeploymentDecision::class);
    }

    public function deploymentOutcome(): HasOne
    {
        return $this->hasOne(DeploymentOutcome::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repo_full_name', 'full_name');
    }

    // --- Accessors ---

    /**
     * Returns the Bootstrap color class for the current status badge.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'secondary',
            'analyzing' => 'info',
            'scored' => 'primary',
            'approved' => 'success',
            'blocked' => 'danger',
            'deployed' => 'success',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Returns the risk score from the related assessment, or null.
     */
    public function getRiskScoreValueAttribute(): ?int
    {
        return $this->riskAssessment?->risk_score;
    }

    /**
     * Returns the Bootstrap color class for risk score display.
     */
    public function getRiskColorAttribute(): string
    {
        $score = $this->risk_score_value;
        if ($score === null) return 'secondary';
        if ($score >= 76) return 'danger';
        if ($score >= 51) return 'warning';
        if ($score >= 26) return 'info';
        return 'success';
    }
}
