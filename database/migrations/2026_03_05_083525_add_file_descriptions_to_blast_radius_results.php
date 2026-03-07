<?php
// database/migrations/2026_03_05_083525_add_file_descriptions_to_blast_radius_results.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blast_radius_results', function (Blueprint $table) {
            $table->json('file_descriptions')->nullable()->after('dependency_graph');
        });
    }

    public function down(): void
    {
        Schema::table('blast_radius_results', function (Blueprint $table) {
            $table->dropColumn('file_descriptions');
        });
    }
};
