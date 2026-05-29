<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('completion_reminder_sent_at')->nullable()->after('second_reminder_sent_at');
            $table->timestamp('completed_at')->nullable()->after('completion_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['completion_reminder_sent_at', 'completed_at']);
        });
    }
};
