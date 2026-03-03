<?php
// database/migrations/2026_03_02_191715_create_blast_radius_results_table.php
// Stores output from Agent 1 (Archaeologist) - maps the blast radius of a PR.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blast_radius_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->json('affected_files');
            $table->json('affected_services');
            $table->json('affected_endpoints');
            $table->json('dependency_graph');
            $table->integer('total_affected_files')->default(0);
            $table->integer('total_affected_services')->default(0);
            $table->text('summary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blast_radius_results');
    }
};
