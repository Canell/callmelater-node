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
        Schema::create('account_plan_overrides', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_id');
            $table->string('plan', 20)->nullable(); // null = revoked, 'pro', 'business'
            $table->timestamp('expires_at')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('set_by_user_id')->nullable(); // Admin who set it
            $table->string('action', 20); // 'set', 'revoked', 'expired'
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('set_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_plan_overrides');
    }
};
