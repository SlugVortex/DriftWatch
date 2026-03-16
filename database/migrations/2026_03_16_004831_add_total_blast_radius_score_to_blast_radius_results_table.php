<?php

// database/migrations/2026_03_16_004831_add_total_blast_radius_score_to_blast_radius_results_table.php
// Adds blast radius score column so the dependency tree and verdict card can display it.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blast_radius_results', function (Blueprint $table) {
            $table->integer('total_blast_radius_score')->default(0)->after('total_affected_services');
        });
    }

    public function down(): void
    {
        Schema::table('blast_radius_results', function (Blueprint $table) {
            $table->dropColumn('total_blast_radius_score');
        });
    }
};
