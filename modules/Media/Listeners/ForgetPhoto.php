<?php

namespace Modules\Media\Listeners;

use Modules\Identity\Events\AccountDeleting;
use Modules\Media\Services\PhotoStore;

final class ForgetPhoto
{
    public function __construct(private readonly PhotoStore $photos) {}

    public function handle(AccountDeleting $event): void
    {
        $this->photos->forget($event->userId);
    }
}
