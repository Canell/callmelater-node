<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename team_members table to contacts
        Schema::rename('team_members', 'contacts');

        // Rename team_member_id column in reminder_recipients
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->renameColumn('team_member_id', 'contact_id');
        });
    }

    public function down(): void
    {
        // Rename contacts table back to team_members
        Schema::rename('contacts', 'team_members');

        // Rename contact_id column back to team_member_id
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->renameColumn('contact_id', 'team_member_id');
        });
    }
};
