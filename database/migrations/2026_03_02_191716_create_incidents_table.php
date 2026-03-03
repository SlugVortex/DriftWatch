<?php
// database/migrations/2026_03_02_191716_create_incidents_table.php
// Historical incidents seeded for the Historian agent to reference.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_id')->unique();
            $table->string('title');
            $table->text('description');
            $table->integer('severity');
            $table->json('affected_services');
            $table->json('affected_files');
            $table->string('root_cause_file')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('engineers_paged')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('severity');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
