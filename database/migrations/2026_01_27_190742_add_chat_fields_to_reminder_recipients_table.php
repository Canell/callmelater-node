<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->string('chat_provider')->nullable()->after('email'); // 'teams' or 'slack'
            $table->text('chat_destination')->nullable()->after('chat_provider'); // User ID, channel ID, or webhook URL
            $table->string('chat_message_id')->nullable()->after('chat_destination'); // For updating messages after response
            $table->string('slack_channel_id')->nullable()->after('chat_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->dropColumn(['chat_provider', 'chat_destination', 'chat_message_id', 'slack_channel_id']);
        });
    }
};
