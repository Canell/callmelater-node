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
        Schema::table('accounts', function (Blueprint $table) {
            // Manual plan override (bypasses Stripe subscription check)
            // Values: null (use Stripe), 'pro', 'business'
            $table->string('manual_plan', 20)->nullable()->after('owner_id');

            // Optional expiration for time-limited overrides
            $table->timestamp('manual_plan_expires_at')->nullable()->after('manual_plan');

            // Reason for the manual override (for admin visibility)
            $table->string('manual_plan_reason')->nullable()->after('manual_plan_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['manual_plan', 'manual_plan_expires_at', 'manual_plan_reason']);
        });
    }
};
