<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->string('response_comment', 500)->nullable()->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->dropColumn('response_comment');
        });
    }
};
