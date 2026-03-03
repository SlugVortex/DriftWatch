<?php
// database/migrations/2026_03_02_191714_create_pull_requests_table.php
// Tracks every PR that DriftWatch analyzes through the agent pipeline.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->string('github_pr_id')->unique();
            $table->string('repo_full_name');
            $table->integer('pr_number');
            $table->string('pr_title');
            $table->string('pr_author');
            $table->string('pr_url');
            $table->string('base_branch');
            $table->string('head_branch');
            $table->integer('files_changed')->default(0);
            $table->integer('additions')->default(0);
            $table->integer('deletions')->default(0);
            $table->enum('status', [
                'pending', 'analyzing', 'scored', 'approved',
                'blocked', 'deployed', 'failed',
            ])->default('pending');
            $table->timestamps();

            $table->index('repo_full_name');
            $table->index('status');
            $table->index('pr_author');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
