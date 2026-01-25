<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->uuid('team_member_id')->nullable()->after('action_id');
            $table->foreign('team_member_id')->references('id')->on('team_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->dropForeign(['team_member_id']);
            $table->dropColumn('team_member_id');
        });
    }
};
