<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->json('coordination_config')->nullable()->after('replaced_by_action_id');
            $table->unsignedInteger('coordination_reschedule_count')->default(0)->after('coordination_config');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropColumn(['coordination_config', 'coordination_reschedule_count']);
        });
    }
};
