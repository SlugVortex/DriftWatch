<?php

// database/migrations/2026_03_07_083058_create_pipeline_configs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);

            // Agent enable/disable
            $table->boolean('agent_archaeologist')->default(true);
            $table->boolean('agent_historian')->default(true);
            $table->boolean('agent_negotiator')->default(true);
            $table->boolean('agent_chronicler')->default(true);

            // Approval gates
            $table->boolean('require_approval_after_scoring')->default(false);
            $table->integer('auto_approve_below_score')->default(20);
            $table->integer('auto_block_above_score')->default(85);

            // Conditional rules (JSON array of rule objects)
            $table->json('conditional_rules')->nullable();

            // Environment thresholds (JSON: {staging: {threshold: 60}, production: {threshold: 40}})
            $table->json('environment_thresholds')->nullable();

            // Retry settings
            $table->integer('max_retries_per_agent')->default(1);
            $table->boolean('retry_on_timeout')->default(true);

            // High-traffic schedule (JSON array of {day, start_hour, end_hour})
            $table->json('high_traffic_windows')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_configs');
    }
};
