<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('favorite_barbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('barber_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['customer_id', 'barber_id']); // منع التكرار
        });
    }

    public function down()
    {
        Schema::dropIfExists('favorite_barbers');
    }
};
