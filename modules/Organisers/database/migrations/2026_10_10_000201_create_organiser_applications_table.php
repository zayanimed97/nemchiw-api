<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organiser_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->unique();
            $table->string('name', 80);
            $table->json('regions');
            $table->unsignedTinyInteger('experience_years');
            $table->string('public_phone', 16)->nullable();
            $table->string('status', 16)->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('submitted_at', 3)->nullable();
            $table->timestamp('decided_at', 3)->nullable();
            $table->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organiser_applications');
    }
};
