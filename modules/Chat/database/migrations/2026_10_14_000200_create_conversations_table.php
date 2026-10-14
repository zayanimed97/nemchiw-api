<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Links to the request, trip and people are nulled, never cascaded: when someone
        // deletes their account the other side keeps the conversation. The trip title and
        // kind are copied for the same reason.
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('request_id')->nullable()->unique()->constrained('trip_requests')->nullOnDelete();
            $table->foreignUlid('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignUlid('camper_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('organiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trip_title', 120);
            $table->string('trip_kind', 20);
            $table->timestamp('trip_starts_at');
            $table->timestamp('last_message_at', 3)->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('created_at', 3)->nullable();
            $table->timestamp('updated_at', 3)->nullable()->index();
            $table->index(['camper_id', 'updated_at']);
            $table->index(['organiser_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
