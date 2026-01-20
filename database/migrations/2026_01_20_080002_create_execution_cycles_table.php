<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_cycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('action_id');
            $table->unsignedInteger('cycle_number');
            $table->enum('triggered_by', ['system', 'manual'])->default('system');
            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->enum('result', ['success', 'failed', 'in_progress'])->default('in_progress');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('action_id')
                ->references('id')
                ->on('scheduled_actions')
                ->onDelete('cascade');

            $table->index(['action_id', 'cycle_number'], 'idx_action_cycles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_cycles');
    }
};
