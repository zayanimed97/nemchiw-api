<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('phone', 16);
            $table->string('purpose', 8);
            $table->ulid('user_id')->nullable()->index();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('resend_after');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['phone', 'purpose', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
