<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The lower-cased email while a provider vouches for it. Unique (MySQL has no partial indexes). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('confirmed_email', 254)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['confirmed_email']);
            $table->dropColumn('confirmed_email');
        });
    }
};
