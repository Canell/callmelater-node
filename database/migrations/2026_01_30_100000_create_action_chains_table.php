<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_chains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->json('steps');
            $table->json('input')->nullable();
            $table->json('context')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_step')->default(0);
            $table->string('error_handling')->default('fail_chain');
            $table->string('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_chains');
    }
};
