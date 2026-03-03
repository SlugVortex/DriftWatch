<?php
// database/migrations/2026_03_02_191715_create_deployment_decisions_table.php
// Stores output from Agent 3 (Negotiator) - deployment gate decisions.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->enum('decision', ['approved', 'blocked', 'pending_review']);
            $table->string('decided_by')->nullable();
            $table->boolean('has_concurrent_deploys')->default(false);
            $table->boolean('in_freeze_window')->default(false);
            $table->boolean('notified_oncall')->default(false);
            $table->text('notification_message')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_decisions');
    }
};
