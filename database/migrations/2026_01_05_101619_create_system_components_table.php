<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_components', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Webhook Delivery"
            $table->string('slug')->unique(); // e.g. "webhook-delivery"
            $table->string('description')->nullable(); // Brief description
            $table->enum('current_status', ['operational', 'degraded', 'outage'])->default('operational');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_components');
    }
};
