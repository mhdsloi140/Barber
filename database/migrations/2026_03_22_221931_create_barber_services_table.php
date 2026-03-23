<?php
// database/migrations/xxxx_create_barber_services_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('barber_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('barber_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
            $table->string('name');
            // $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            // $table->text('description_ar')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('duration_minutes');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['barber_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_services');
    }
};
