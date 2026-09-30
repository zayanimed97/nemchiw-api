<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('first_name', 40)->nullable();
            $table->string('last_name', 40)->nullable();
            $table->string('gender', 8)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 16)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email', 254)->nullable();
            $table->string('level', 16)->nullable();
            $table->json('skills')->nullable();
            $table->string('bio', 160)->nullable();
            $table->string('home_governorate', 32)->nullable();
            $table->string('home_city', 60)->nullable();
            $table->unsignedTinyInteger('car_seats')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
