<?php
// app/Models/Incident.php
// Historical incident records used by the Historian agent for risk correlation.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'title',
        'description',
        'severity',
        'affected_services',
        'affected_files',
        'root_cause_file',
        'duration_minutes',
        'engineers_paged',
        'occurred_at',
        'resolved_at',
    ];

    protected $casts = [
        'severity' => 'integer',
        'affected_services' => 'array',
        'affected_files' => 'array',
        'duration_minutes' => 'integer',
        'engineers_paged' => 'integer',
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Severity label for display.
     */
    public function getSeverityLabelAttribute(): string
    {
        return match ($this->severity) {
            1 => 'P1 - Critical',
            2 => 'P2 - High',
            3 => 'P3 - Medium',
            4 => 'P4 - Low',
            5 => 'P5 - Informational',
            default => 'Unknown',
        };
    }

    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            1 => 'danger',
            2 => 'warning',
            3 => 'info',
            4 => 'primary',
            5 => 'secondary',
            default => 'secondary',
        };
    }
}
