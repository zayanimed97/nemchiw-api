<?php

namespace Modules\Shared\Places;

use Illuminate\Database\Schema\Blueprint;

final class PlaceColumns
{
    /** Status, author, rejection reason and a soft-delete tombstone, for every place table. */
    public static function add(Blueprint $table): void
    {
        $table->string('status', 16)->default(PlaceStatus::LIVE)->index();
        $table->ulid('created_by')->nullable()->index();
        $table->string('rejection_reason', 500)->nullable();
        $table->softDeletes(precision: 3);
    }
}
