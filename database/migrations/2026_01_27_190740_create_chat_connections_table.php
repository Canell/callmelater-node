<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');

            $table->string('provider'); // 'teams' or 'slack'
            $table->string('name'); // User-friendly name (e.g., "Engineering Team")

            // Teams-specific
            $table->string('teams_tenant_id')->nullable();
            $table->text('teams_webhook_url')->nullable(); // Incoming webhook URL (can be long)

            // Slack-specific (for Phase 2)
            $table->string('slack_team_id')->nullable();
            $table->text('slack_bot_token')->nullable(); // encrypted
            $table->string('slack_signing_secret')->nullable(); // encrypted
            $table->string('slack_channel_id')->nullable();
            $table->string('slack_channel_name')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->index(['account_id', 'provider', 'is_active'], 'idx_chat_connections_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_connections');
    }
};
