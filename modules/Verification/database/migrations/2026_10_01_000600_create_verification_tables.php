<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_sessions', function (Blueprint $table) {
            $table->string('session_id', 64)->primary();
            $table->ulid('user_id')->index();
            $table->ulid('photo_id');
            $table->string('status', 32)->default('Not Started');
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('event_id', 64)->primary();
            $table->string('session_id', 64)->nullable();
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('verification_sessions');
    }
};
