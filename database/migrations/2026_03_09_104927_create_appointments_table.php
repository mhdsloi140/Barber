<?php
// database/migrations/xxxx_create_appointments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('barber_id')->constrained('users');
            $table->foreignId('salon_id')->constrained();
            $table->foreignId('service_id')->constrained();

            // هذين الحقلين مهمين للتاريخ والوقت
            $table->date('appointment_date');      // حقل التاريخ
            $table->time('appointment_time');      // حقل الوقت
            $table->time('end_time')->nullable();

            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->decimal('total_price', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
