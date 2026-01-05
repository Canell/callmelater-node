<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('impact', ['minor', 'major', 'critical'])->default('minor');
            $table->enum('status', ['investigating', 'identified', 'monitoring', 'resolved'])->default('investigating');
            $table->text('summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index('resolved_at');
        });

        // Pivot table for incidents affecting multiple components
        Schema::create('incident_component', function (Blueprint $table) {
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('system_components')->cascadeOnDelete();
            $table->primary(['incident_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_component');
        Schema::dropIfExists('incidents');
    }
};
