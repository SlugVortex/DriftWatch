<?php
// app/Models/BlastRadiusResult.php
// Stores the output of Agent 1 (Archaeologist) - which services, files, and endpoints a PR affects.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlastRadiusResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'pull_request_id',
        'affected_files',
        'affected_services',
        'affected_endpoints',
        'dependency_graph',
        'file_descriptions',
        'total_affected_files',
        'total_affected_services',
        'summary',
    ];

    protected $casts = [
        'affected_files' => 'array',
        'affected_services' => 'array',
        'affected_endpoints' => 'array',
        'dependency_graph' => 'array',
        'file_descriptions' => 'array',
        'total_affected_files' => 'integer',
        'total_affected_services' => 'integer',
    ];

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }
}
