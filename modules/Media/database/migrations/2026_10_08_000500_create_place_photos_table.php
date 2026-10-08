<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_photos', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->string('place_id', 64);
            $table->char('hash', 32)->index();
            $table->unsignedTinyInteger('position');
            $table->timestamps();
            $table->unique(['kind', 'place_id', 'hash']);
            $table->index(['kind', 'place_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_photos');
    }
};
