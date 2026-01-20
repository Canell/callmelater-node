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
        Schema::create('blocked_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('recipient')->unique(); // email or phone number
            $table->string('reason')->nullable(); // abuse, complaint, bounce, etc.
            $table->uuid('blocked_by')->nullable(); // admin user who blocked
            $table->timestamps();

            $table->index('recipient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_recipients');
    }
};
