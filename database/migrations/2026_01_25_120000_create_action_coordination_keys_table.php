<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_coordination_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('action_id');
            $table->string('coordination_key', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('action_id')
                ->references('id')
                ->on('scheduled_actions')
                ->cascadeOnDelete();

            $table->unique(['action_id', 'coordination_key'], 'action_coordination_key_unique');
            $table->index(['coordination_key', 'created_at'], 'idx_coordination_key_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_coordination_keys');
    }
};
