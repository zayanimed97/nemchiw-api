<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per person who has organised anything (community hosts too) or applied.
        Schema::create('organisers', function (Blueprint $table) {
            $table->ulid('user_id')->primary();
            $table->timestamp('certified_at', 3)->nullable();
            $table->string('public_phone', 16)->nullable();
            $table->unsignedInteger('trips_run')->default(0);
            $table->unsignedInteger('campers_joined')->default(0);
            $table->unsignedInteger('cancelled_trips')->default(0);
            $table->unsignedInteger('cancelled_campers')->default(0);
            $table->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisers');
    }
};
