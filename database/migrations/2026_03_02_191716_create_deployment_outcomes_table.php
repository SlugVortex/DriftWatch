<?php
// database/migrations/2026_03_02_191716_create_deployment_outcomes_table.php
// Stores output from Agent 4 (Chronicler) - post-deploy feedback loop.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->integer('predicted_risk_score');
            $table->boolean('incident_occurred')->default(false);
            $table->integer('actual_severity')->nullable();
            $table->json('actual_affected_services')->nullable();
            $table->boolean('prediction_accurate')->nullable();
            $table->text('post_mortem_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_outcomes');
    }
};
