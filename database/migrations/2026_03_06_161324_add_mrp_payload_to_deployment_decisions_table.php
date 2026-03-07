<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_decisions', function (Blueprint $table) {
            $table->json('mrp_payload')->nullable()->after('notification_message');
            $table->integer('mrp_version')->default(1)->after('mrp_payload');
            $table->integer('weather_score')->nullable()->after('mrp_version');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_decisions', function (Blueprint $table) {
            $table->dropColumn(['mrp_payload', 'mrp_version', 'weather_score']);
        });
    }
};
