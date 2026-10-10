<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organiser_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('organiser_applications')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('path', 255);
            $table->string('mime', 64);
            $table->unsignedInteger('size');
            $table->timestamps(3);
            $table->unique(['application_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organiser_documents');
    }
};
