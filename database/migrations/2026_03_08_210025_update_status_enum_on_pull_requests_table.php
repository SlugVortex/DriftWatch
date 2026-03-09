<?php

// database/migrations/2026_03_08_210025_update_status_enum_on_pull_requests_table.php
// Expands the status enum to include pending_review, merged, and open.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pull_requests MODIFY COLUMN status ENUM(
            'pending', 'analyzing', 'scored', 'approved',
            'blocked', 'deployed', 'failed',
            'pending_review', 'merged', 'open'
        ) DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pull_requests MODIFY COLUMN status ENUM(
            'pending', 'analyzing', 'scored', 'approved',
            'blocked', 'deployed', 'failed'
        ) DEFAULT 'pending'");
    }
};
