<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reminder_id');
            $table->string('event_type'); // sent | snoozed | confirmed | declined | escalated | expired
            $table->string('actor_email')->nullable();
            $table->string('captured_timezone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('reminder_id')->references('id')->on('scheduled_actions')->cascadeOnDelete();
            $table->index(['reminder_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_events');
    }
};
