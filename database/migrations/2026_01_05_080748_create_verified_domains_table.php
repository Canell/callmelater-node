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
        Schema::create('verified_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('domain')->comment('Normalized lowercase domain');
            $table->string('verification_token', 64);
            $table->enum('method', ['dns', 'file'])->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('verified_at + 12 months');
            $table->timestamps();

            // Foreign key
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();

            // Unique domain per account
            $table->unique(['account_id', 'domain']);

            // Index for looking up by domain
            $table->index('domain');

            // Index for expiry checks
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verified_domains');
    }
};
