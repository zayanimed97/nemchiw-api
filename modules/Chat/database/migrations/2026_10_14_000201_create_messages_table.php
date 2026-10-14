<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary(); // orders the conversation (server time)
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            // Who caused a system line; it does not count as unread for them.
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('client_id')->nullable();
            $table->string('kind', 10);
            $table->text('body')->nullable();
            $table->string('photo_hash', 32)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->string('system_key', 30)->nullable();
            $table->json('system_params')->nullable();
            $table->timestamp('created_at', 3)->nullable();
            $table->unique(['sender_id', 'client_id']);
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
