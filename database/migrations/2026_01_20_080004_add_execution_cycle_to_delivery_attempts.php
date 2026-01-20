<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_attempts', function (Blueprint $table) {
            $table->uuid('execution_cycle_id')->nullable()->after('action_id');
            $table->uuid('execution_id')->nullable()->after('execution_cycle_id');

            $table->index('execution_cycle_id');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_attempts', function (Blueprint $table) {
            $table->dropIndex(['execution_cycle_id']);
            $table->dropColumn(['execution_cycle_id', 'execution_id']);
        });
    }
};
