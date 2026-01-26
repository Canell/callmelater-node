<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->uuid('replaced_by_action_id')->nullable()->after('idempotency_key');
            $table->foreign('replaced_by_action_id')
                ->references('id')
                ->on('scheduled_actions')
                ->nullOnDelete();
            $table->index('replaced_by_action_id');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropForeign(['replaced_by_action_id']);
            $table->dropColumn('replaced_by_action_id');
        });
    }
};
