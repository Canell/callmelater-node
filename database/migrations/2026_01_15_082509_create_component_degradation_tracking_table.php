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
        Schema::create('component_degradation_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('system_components')->onDelete('cascade');
            $table->timestamp('degraded_since');
            $table->foreignUuid('reminder_action_id')->nullable()->constrained('scheduled_actions')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique('component_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_degradation_tracking');
    }
};
