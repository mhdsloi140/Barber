<?php
// database/migrations/xxxx_create_ratings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();

            // العلاقات
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('barber_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('salon_id')->nullable()->constrained('salons')->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->onDelete('cascade');

            // التقييم
            $table->integer('rating')->unsigned()->min(1)->max(5);
            $table->text('comment')->nullable();

            // حالة التقييم
            $table->boolean('is_approved')->default(true);

            // منع التكرار (زبون واحد يمكنه تقييم نفس الحلاق مرة واحدة فقط لنفس الموعد)
            $table->unique(['customer_id', 'barber_id', 'appointment_id'], 'unique_rating');

            $table->timestamps();

            // فهارس
            $table->index('barber_id');
            $table->index('salon_id');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
