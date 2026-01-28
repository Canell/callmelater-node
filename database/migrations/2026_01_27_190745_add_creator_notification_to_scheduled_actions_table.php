<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->boolean('notify_creator_on_response')->default(false)->after('gate');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropColumn('notify_creator_on_response');
        });
    }
};
