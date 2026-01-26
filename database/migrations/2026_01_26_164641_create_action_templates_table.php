<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Template identification
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_token', 64)->unique();

            // Template configuration
            $table->string('mode'); // 'immediate' or 'gated'
            $table->string('timezone')->default('UTC');

            // Request configuration (JSON, may contain {{placeholders}})
            $table->json('request_config')->nullable();

            // Gate configuration (for gated mode)
            $table->json('gate_config')->nullable();

            // Retry & execution settings
            $table->unsignedInteger('max_attempts')->default(5);
            $table->string('retry_strategy')->default('exponential');

            // Coordination (optional)
            $table->json('coordination_config')->nullable();
            $table->json('default_coordination_keys')->nullable();

            // Placeholder definitions
            $table->json('placeholders')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedBigInteger('trigger_count')->default(0);

            $table->timestamps();

            // Indexes
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->index(['account_id', 'is_active']);
        });

        // Add template_id to scheduled_actions
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->uuid('template_id')->nullable()->after('account_id');
            $table->foreign('template_id')->references('id')->on('action_templates')->nullOnDelete();
            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropIndex(['template_id']);
            $table->dropColumn('template_id');
        });

        Schema::dropIfExists('action_templates');
    }
};
