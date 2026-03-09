<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // database/migrations/xxxx_create_working_hours_table.php
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->morphs('workable'); // للصالون أو الحلاق
            $table->enum('day_of_week', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
            $table->boolean('is_open')->default(true);
            $table->time('shift1_start')->nullable();
            $table->time('shift1_end')->nullable();
            $table->time('shift2_start')->nullable();
            $table->time('shift2_end')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->timestamps();

            $table->unique(['workable_type', 'workable_id', 'day_of_week'], 'unique_working_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
