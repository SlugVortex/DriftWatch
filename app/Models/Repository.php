<?php

// app/Models/Repository.php
// Represents a connected GitHub repository tracked by DriftWatch.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repository extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'full_name',
        'owner',
        'default_branch',
        'github_url',
        'webhook_secret',
        'is_active',
        'auto_analyze',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_analyze' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function pullRequests(): HasMany
    {
        return $this->hasMany(PullRequest::class, 'repo_full_name', 'full_name');
    }
}
