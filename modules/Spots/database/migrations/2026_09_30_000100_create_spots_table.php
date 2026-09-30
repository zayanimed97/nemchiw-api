<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spots', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->json('name');
            $table->json('description');
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('governorate', 32);
            $table->string('type', 16);
            $table->string('access', 16);
            $table->boolean('water');
            $table->string('coverage', 8);
            $table->string('permit', 8);
            $table->timestamps(3);
            $table->index(['updated_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spots');
    }
};
