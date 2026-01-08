<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            // Billing (Stripe/Cashier) - moved from users table
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamps();
        });

        // Pivot table for account members
        Schema::create('account_user', function (Blueprint $table) {
            $table->uuid('account_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner, admin, member
            $table->timestamps();

            $table->primary(['account_id', 'user_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');
    }
};
