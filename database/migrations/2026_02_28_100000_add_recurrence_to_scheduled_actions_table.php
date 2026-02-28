<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->json('recurrence_config')->nullable()->after('coordination_reschedule_count');
            $table->unsignedInteger('recurrence_count')->default(0)->after('recurrence_config');
            $table->timestamp('last_executed_at')->nullable()->after('recurrence_count');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropColumn(['recurrence_config', 'recurrence_count', 'last_executed_at']);
        });
    }
};
