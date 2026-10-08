<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->ulid('admin_id')->index();
            $table->string('action', 16);
            $table->string('target_kind', 16);
            $table->string('target_id', 64);
            $table->json('changes')->nullable();
            $table->timestamp('created_at', 3);
            $table->index(['target_kind', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
