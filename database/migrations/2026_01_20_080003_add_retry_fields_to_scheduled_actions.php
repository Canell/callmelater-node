<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->uuid('current_execution_cycle_id')->nullable()->after('callback_url');
            $table->unsignedInteger('manual_retry_count')->default(0)->after('current_execution_cycle_id');
            $table->timestamp('last_manual_retry_at')->nullable()->after('manual_retry_count');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropColumn(['current_execution_cycle_id', 'manual_retry_count', 'last_manual_retry_at']);
        });
    }
};
