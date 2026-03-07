<?php
// database/migrations/2026_03_06_073130_add_change_type_to_incidents_table.php
// Adds change_type column for multi-layer matching in the Historian agent.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('change_type')->nullable()->after('root_cause_file');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn('change_type');
        });
    }
};
