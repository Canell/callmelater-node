<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_connections', 'slack_team_id')) {
                $table->string('slack_team_id')->nullable()->after('teams_webhook_url');
            }
            if (! Schema::hasColumn('chat_connections', 'slack_bot_token')) {
                $table->text('slack_bot_token')->nullable()->after('slack_team_id');
            }
            if (! Schema::hasColumn('chat_connections', 'slack_signing_secret')) {
                $table->string('slack_signing_secret')->nullable()->after('slack_bot_token');
            }
            if (! Schema::hasColumn('chat_connections', 'slack_channel_id')) {
                $table->string('slack_channel_id')->nullable()->after('slack_signing_secret');
            }
            if (! Schema::hasColumn('chat_connections', 'slack_channel_name')) {
                $table->string('slack_channel_name')->nullable()->after('slack_channel_id');
            }
        });

        Schema::table('reminder_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('reminder_recipients', 'slack_channel_id')) {
                $table->string('slack_channel_id')->nullable()->after('chat_message_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_connections', function (Blueprint $table) {
            $table->dropColumn([
                'slack_team_id',
                'slack_bot_token',
                'slack_signing_secret',
                'slack_channel_id',
                'slack_channel_name',
            ]);
        });

        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->dropColumn('slack_channel_id');
        });
    }
};
