<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A request is a party of 1..N (parties arrive in 3.4); the leader acts for it.
        Schema::create('trip_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignUlid('leader_id')->constrained('users')->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->string('status', 16)->index();
            $table->text('code_secret')->nullable(); // encrypted, created on accept
            $table->timestamp('accepted_at', 3)->nullable();
            $table->timestamp('joined_at', 3)->nullable();
            $table->timestamp('ended_at', 3)->nullable();
            $table->string('ended_by', 16)->nullable(); // camper | organiser | admin | system
            $table->string('withdrawn_from', 16)->nullable(); // pending | accepted | joined
            $table->timestamps(3);
            $table->index(['trip_id', 'status']);
            $table->index(['leader_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_requests');
    }
};
