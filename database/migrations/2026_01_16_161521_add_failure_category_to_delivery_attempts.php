<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_attempts', function (Blueprint $table) {
            $table->string('failure_category', 20)->nullable()->after('status');
            $table->string('target_domain', 255)->nullable()->after('failure_category');

            // Index for health monitoring queries
            $table->index(['failure_category', 'created_at'], 'idx_failure_category_created');
            $table->index(['target_domain', 'created_at'], 'idx_target_domain_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_failure_category_created');
            $table->dropIndex('idx_target_domain_created');
            $table->dropColumn(['failure_category', 'target_domain']);
        });
    }
};
