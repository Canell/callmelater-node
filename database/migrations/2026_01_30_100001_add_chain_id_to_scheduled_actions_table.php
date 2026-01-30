<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->foreignUuid('chain_id')->nullable()->after('account_id')
                  ->constrained('action_chains')->nullOnDelete();
            $table->unsignedInteger('chain_step')->nullable()->after('chain_id');

            $table->index(['chain_id', 'chain_step']);
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropForeign(['chain_id']);
            $table->dropIndex(['chain_id', 'chain_step']);
            $table->dropColumn(['chain_id', 'chain_step']);
        });
    }
};
