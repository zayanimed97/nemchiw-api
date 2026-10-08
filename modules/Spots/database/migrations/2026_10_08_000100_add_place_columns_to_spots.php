<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Places\PlaceColumns;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            PlaceColumns::add($table);
        });
    }

    public function down(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['status', 'created_by', 'rejection_reason']);
        });
    }
};
