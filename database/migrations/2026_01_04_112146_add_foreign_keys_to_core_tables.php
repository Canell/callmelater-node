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
        // scheduled_actions -> users (owner)
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->foreign('owner_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('owner_team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');
        });

        // delivery_attempts -> scheduled_actions
        Schema::table('delivery_attempts', function (Blueprint $table) {
            $table->foreign('action_id')
                ->references('id')
                ->on('scheduled_actions')
                ->onDelete('cascade');
        });

        // reminder_events -> scheduled_actions
        Schema::table('reminder_events', function (Blueprint $table) {
            $table->foreign('reminder_id')
                ->references('id')
                ->on('scheduled_actions')
                ->onDelete('cascade');
        });

        // reminder_recipients -> scheduled_actions
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->foreign('action_id')
                ->references('id')
                ->on('scheduled_actions')
                ->onDelete('cascade');
        });

        // teams -> users (owner)
        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // team_user pivot -> teams and users
        Schema::table('team_user', function (Blueprint $table) {
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropForeign(['owner_team_id']);
        });

        Schema::table('delivery_attempts', function (Blueprint $table) {
            $table->dropForeign(['action_id']);
        });

        Schema::table('reminder_events', function (Blueprint $table) {
            $table->dropForeign(['reminder_id']);
        });

        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->dropForeign(['action_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        Schema::table('team_user', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['user_id']);
        });
    }
};
