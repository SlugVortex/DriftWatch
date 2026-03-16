<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->string('pipeline_stage')->nullable()->after('status');
            $table->timestamp('stage_started_at')->nullable()->after('pipeline_stage');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn(['pipeline_stage', 'stage_started_at']);
        });
    }
};
