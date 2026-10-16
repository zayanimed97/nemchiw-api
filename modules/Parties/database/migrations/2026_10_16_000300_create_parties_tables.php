<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('leader_id')->constrained('users')->cascadeOnDelete();
            $table->string('state', 10)->default('forming'); // forming, asking, going, over
            $table->ulid('request_id')->nullable()->index(); // the trip request, set by Trips' listener
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
            $table->index(['leader_id', 'state']);
        });

        Schema::create('party_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->char('contact_hash', 64)->nullable(); // a placeholder: HMAC of the phone or email
            $table->string('contact_masked', 80)->nullable();
            $table->string('state', 10)->default('invited'); // invited, in, declined, left, removed
            $table->timestamp('invited_at', 3)->nullable();
            $table->timestamp('answered_at', 3)->nullable();
            $table->timestamp('reminded_at', 3)->nullable();
            $table->timestamps();
            $table->unique(['party_id', 'user_id']);
            $table->unique(['party_id', 'contact_hash']);
            $table->index(['user_id', 'state']);
            $table->index('contact_hash');
        });

        Schema::create('friendships', function (Blueprint $table) {
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('friend_id')->constrained('users')->cascadeOnDelete();
            $table->json('reason'); // {kind: party} or {kind: trip, place: string}
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'friend_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
        Schema::dropIfExists('party_members');
        Schema::dropIfExists('parties');
    }
};
