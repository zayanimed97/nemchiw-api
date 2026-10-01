<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_identities', function (Blueprint $table) {
            $table->id();
            $table->ulid('user_id')->index();
            $table->string('provider', 16);
            $provider = $table->string('provider_user_id', 191);
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                // Exact, case-sensitive matching: 'abc' and 'ABC' are different people.
                $provider->collation('utf8mb4_bin');
            }
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_identities');
    }
};
