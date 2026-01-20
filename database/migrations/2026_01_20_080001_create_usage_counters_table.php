<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_id');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedInteger('actions_created')->default(0);
            $table->unsignedInteger('sms_sent')->default(0);
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('cascade');

            $table->unique(['account_id', 'period_year', 'period_month'], 'idx_account_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
