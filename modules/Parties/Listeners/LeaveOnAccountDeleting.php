<?php

namespace Modules\Parties\Listeners;

use Modules\Identity\Events\AccountDeleting;
use Modules\Parties\Actions\LeaveParty;
use Modules\Parties\Actions\Parties;

/** Runs inside the deletion transaction: the lead passes on (or the party ends) before the user row goes. */
final class LeaveOnAccountDeleting
{
    public function __construct(private readonly LeaveParty $leave) {}

    public function handle(AccountDeleting $event): void
    {
        Parties::lockPerson($event->userId);
        $party = Parties::openFor($event->userId);
        if ($party !== null) {
            ($this->leave)($party, $event->userId);
        }
    }
}
