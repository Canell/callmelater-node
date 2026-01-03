<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('action_id');
            $table->string('email');
            $table->string('status')->default('pending'); // pending | confirmed | declined | snoozed
            $table->string('response_token')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->foreign('action_id')->references('id')->on('scheduled_actions')->cascadeOnDelete();
            $table->unique(['action_id', 'email'], 'unique_recipient');
            $table->index(['action_id', 'status'], 'idx_recipient_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_recipients');
    }
};
