<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('request_id')->constrained('trip_requests')->cascadeOnDelete();
            $table->foreignUlid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 16);
            $table->string('note', 500)->nullable();
            $table->string('status', 24)->index(); // open | closed_no_penalty | closed_organiser | dismissed
            $table->timestamp('resolved_at', 3)->nullable();
            $table->ulid('resolved_by')->nullable(); // admins.id
            $table->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_reports');
    }
};
