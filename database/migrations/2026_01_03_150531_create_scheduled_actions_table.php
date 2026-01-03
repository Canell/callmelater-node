<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Multi-tenancy
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('owner_team_id')->nullable();

            // Type & intent
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('type'); // http | reminder
            $table->string('intent_type'); // absolute | wall_clock
            $table->json('intent_payload');
            $table->string('timezone')->nullable();

            // Lifecycle
            $table->string('resolution_status'); // pending_resolution | resolved | awaiting_response | executed | cancelled | expired | failed
            $table->timestamp('execute_at_utc')->nullable();
            $table->timestamp('executed_at_utc')->nullable();
            $table->text('failure_reason')->nullable();

            // HTTP request config (for type=http)
            $table->json('http_request')->nullable(); // method, url, headers, body

            // HTTP retry & idempotency
            $table->string('idempotency_key')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('retry_strategy')->default('exponential'); // exponential | fixed

            // Reminder config (for type=reminder)
            $table->text('message')->nullable();
            $table->string('confirmation_mode')->nullable(); // first_response | all_required
            $table->json('escalation_rules')->nullable();
            $table->unsignedInteger('snooze_count')->default(0);
            $table->unsignedInteger('max_snoozes')->default(5);
            $table->timestamp('token_expires_at')->nullable();

            // Webhook signing
            $table->string('webhook_secret')->nullable();

            $table->timestamps();

            // Foreign key for team
            $table->foreign('owner_team_id')->references('id')->on('teams')->nullOnDelete();

            // Unique constraint for idempotency
            $table->unique(['owner_user_id', 'idempotency_key'], 'unique_idempotency');

            // Indexes for dispatcher queries
            $table->index(['resolution_status', 'execute_at_utc', 'next_retry_at'], 'idx_dispatch_queue');

            // Indexes for dashboard queries
            $table->index(['owner_user_id', 'resolution_status', 'created_at'], 'idx_user_actions');
            $table->index(['owner_team_id', 'resolution_status', 'created_at'], 'idx_team_actions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_actions');
    }
};
