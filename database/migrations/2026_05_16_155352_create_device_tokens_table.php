<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('device_token');
            $table->enum('device_type', ['ios', 'android', 'web'])->default('web');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // إضافة indexes لتحسين الأداء
            $table->index(['user_id', 'is_active']);
            $table->unique(['user_id', 'device_token']); // منع تكرار نفس التوكن لنفس المستخدم
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_tokens');
    }
};
