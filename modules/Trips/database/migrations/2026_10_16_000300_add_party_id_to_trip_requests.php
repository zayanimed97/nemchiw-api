<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            // The party that asked (a plain id: Parties owns the table, Trips only remembers it).
            $table->ulid('party_id')->nullable()->index()->after('leader_id');
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', fn (Blueprint $table) => $table->dropColumn('party_id'));
    }
};
