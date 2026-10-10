<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organiser_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('title', 80);
            $table->text('description');
            $table->string('destination_kind', 8);
            $table->string('destination_id', 64);
            $table->json('destination'); // snapshot: name, governorate, latitude, longitude, cover
            $table->string('meetup_url', 500);
            $table->timestamp('meetup_at', 3);
            $table->timestamp('starts_at', 3)->index();
            $table->timestamp('ends_at', 3);
            $table->unsignedTinyInteger('seats');
            $table->unsignedSmallInteger('price_tnd')->nullable();
            $table->boolean('women_only')->default(false);
            $table->string('status', 16)->index();
            $table->timestamp('cancelled_at', 3)->nullable();
            $table->timestamps(3);
            $table->index(['destination_kind', 'destination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
