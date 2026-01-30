<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_templates', function (Blueprint $table) {
            // Type: 'action' (default, single action) or 'chain' (multi-step workflow)
            $table->string('type')->default('action')->after('mode');

            // Chain step definitions (only used when type='chain')
            $table->json('chain_steps')->nullable()->after('type');

            // Error handling for chains
            $table->string('chain_error_handling')->default('fail_chain')->after('chain_steps');
        });
    }

    public function down(): void
    {
        Schema::table('action_templates', function (Blueprint $table) {
            $table->dropColumn(['type', 'chain_steps', 'chain_error_handling']);
        });
    }
};
