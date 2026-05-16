<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_salons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('salon_id')->constrained('salons')->onDelete('cascade');
            $table->timestamps();

            // منع تكرار نفس الصالون لنفس العميل
            $table->unique(['customer_id', 'salon_id']);


            $table->index('customer_id');
            $table->index('salon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_salons');
    }
};
