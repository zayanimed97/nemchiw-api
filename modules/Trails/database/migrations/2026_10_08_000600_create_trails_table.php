<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Places\PlaceColumns;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trails', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->json('name');
            $table->json('description');
            $table->decimal('latitude', 9, 6); // the trailhead
            $table->decimal('longitude', 9, 6);
            $table->string('governorate', 32);
            $table->decimal('distance_km', 5, 1);
            $table->unsignedSmallInteger('elevation_gain_m');
            $table->unsignedSmallInteger('duration_min');
            $table->string('difficulty', 16);
            $table->string('shape', 16);
            $table->boolean('water_on_route');
            $table->string('coverage', 8);
            $table->string('permit', 8);
            PlaceColumns::add($table);
            $table->timestamps(3);
            $table->index(['updated_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trails');
    }
};
