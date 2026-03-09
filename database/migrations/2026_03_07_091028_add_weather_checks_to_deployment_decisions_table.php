<?php

// database/migrations/2026_03_07_091028_add_weather_checks_to_deployment_decisions_table.php
// Adds weather_checks JSON column to store individual environmental risk check results.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_decisions', function (Blueprint $table) {
            $table->json('weather_checks')->nullable()->after('weather_score');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_decisions', function (Blueprint $table) {
            $table->dropColumn('weather_checks');
        });
    }
};
