<?php
// database/migrations/xxxx_fix_appointments_service_id_foreign_key.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });



        // 3. إضافة المفتاح الجديد إلى barber_services
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('service_id')
                  ->references('id')
                  ->on('barber_services')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')->references('id')->on('services');
        });
    }
};
