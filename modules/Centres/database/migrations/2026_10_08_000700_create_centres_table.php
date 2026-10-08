<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Places\PlaceColumns;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centres', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->json('name');
            $table->json('description');
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('governorate', 32);
            $table->json('amenities');
            $table->decimal('price_from_tnd', 7, 2)->nullable(); // per person per night, informative
            $table->string('phone', 16); // business number, public
            $table->string('link', 255)->nullable();
            $table->json('open_months');
            PlaceColumns::add($table);
            $table->timestamps(3);
            $table->index(['updated_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centres');
    }
};
