<?php
// database/migrations/2026_03_06_073132_create_agent_runs_table.php
// Tracks every agent invocation with input/output, timing, cost, and score contribution.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->string('agent_name');
            $table->string('status');
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->integer('score_contribution')->default(0);
            $table->text('reasoning')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost_usd', 8, 6)->default(0);
            $table->integer('duration_ms')->default(0);
            $table->string('model_used')->nullable();
            $table->string('input_hash')->nullable();
            $table->timestamps();

            $table->index('agent_name');
            $table->index(['pull_request_id', 'agent_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
