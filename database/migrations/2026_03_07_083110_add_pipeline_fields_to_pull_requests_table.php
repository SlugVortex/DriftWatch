<?php

// database/migrations/2026_03_07_083110_add_pipeline_fields_to_pull_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->string('target_environment')->default('production')->after('status');
            $table->string('pipeline_template')->default('full')->after('target_environment');
            $table->boolean('pipeline_paused')->default(false)->after('pipeline_template');
            $table->string('paused_at_stage')->nullable()->after('pipeline_paused');
            $table->timestamp('paused_at')->nullable()->after('paused_at_stage');
            $table->string('paused_reason')->nullable()->after('paused_at');
        });

        Schema::table('deployment_decisions', function (Blueprint $table) {
            $table->json('stacked_pr_ids')->nullable()->after('weather_score');
            $table->integer('combined_blast_radius_score')->nullable()->after('stacked_pr_ids');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn([
                'target_environment',
                'pipeline_template',
                'pipeline_paused',
                'paused_at_stage',
                'paused_at',
                'paused_reason',
            ]);
        });

        Schema::table('deployment_decisions', function (Blueprint $table) {
            $table->dropColumn(['stacked_pr_ids', 'combined_blast_radius_score']);
        });
    }
};
