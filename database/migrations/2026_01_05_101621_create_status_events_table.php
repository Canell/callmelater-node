<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('system_components')->cascadeOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->enum('status', ['operational', 'degraded', 'outage']);
            $table->string('message')->nullable(); // Short human-readable explanation
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['component_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_events');
    }
};
