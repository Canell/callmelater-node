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
        Schema::create('notification_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->comment('Normalized lowercase email');
            $table->enum('status', ['pending', 'opted_in', 'opted_out'])->default('pending');
            $table->string('consent_token', 64)->comment('Token for accept/decline links');
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            // Rate limiting fields
            $table->timestamp('last_optin_sent_at')->nullable();
            $table->unsignedSmallInteger('optin_count_24h')->default(0);
            $table->unsignedSmallInteger('optin_count_7d')->default(0);
            $table->unsignedSmallInteger('optin_count_30d')->default(0);
            $table->timestamp('counters_reset_at')->nullable()->comment('When to reset counters');

            // Suppression
            $table->boolean('suppressed')->default(false);
            $table->string('suppression_reason')->nullable();

            $table->timestamps();

            // One record per email
            $table->unique('email');

            // Index for token lookup
            $table->index('consent_token');

            // Index for status queries
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_consents');
    }
};
