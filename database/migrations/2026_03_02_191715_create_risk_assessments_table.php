<?php
// database/migrations/2026_03_02_191715_create_risk_assessments_table.php
// Stores output from Agent 2 (Historian) - historical risk scoring.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->integer('risk_score');
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical']);
            $table->json('historical_incidents');
            $table->json('contributing_factors');
            $table->text('recommendation');
            $table->timestamps();

            $table->index('risk_score');
            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
