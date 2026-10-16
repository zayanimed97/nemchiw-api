<?php

namespace Modules\Parties\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Parties\Actions\AnswerInvitation;
use Modules\Parties\Actions\EndParty;
use Modules\Parties\Actions\Invite;
use Modules\Parties\Actions\LeaveParty;
use Modules\Parties\Actions\Parties;
use Modules\Parties\Actions\RemindMember;
use Modules\Parties\Actions\RemoveMember;
use Modules\Parties\Models\Party;
use Modules\Parties\Services\PartyPresenter;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class PartyMembersController
{
    public function __construct(private readonly PartyPresenter $presenter) {}

    public function invite(Request $request, string $id, Invite $invite): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['required_without:contact', 'nullable', 'string', 'size:26'],
            'contact' => ['required_without:userId', 'nullable', 'string', 'max:254'],
        ]);
        $userId = PartiesController::userId($request);
        $party = $invite->handle($id, $userId, isset($data['userId']) ? strtolower($data['userId']) : null, $data['contact'] ?? null);

        return new JsonResponse($this->presenter->forViewer(Party::query()->findOrFail($party->id), $userId));
    }

    public function accept(Request $request, string $id, AnswerInvitation $answer): JsonResponse
    {
        $userId = PartiesController::userId($request);
        $answer->accept($id, $userId);

        return new JsonResponse($this->presenter->mine($userId));
    }

    public function decline(Request $request, string $id, AnswerInvitation $answer): JsonResponse
    {
        $userId = PartiesController::userId($request);
        $answer->decline($id, $userId);

        return new JsonResponse($this->presenter->mine($userId));
    }

    public function leave(Request $request, string $id, LeaveParty $leave): JsonResponse
    {
        $userId = PartiesController::userId($request);
        DB::transaction(function () use ($id, $userId, $leave) {
            Parties::lockPerson($userId);
            $party = Parties::lockedOrFail($id);
            if (! Parties::isIn($party, $userId)) {
                throw new ApiException(ApiErrorCode::NotFound, 'Not found');
            }
            $leave($party, $userId);
        }, 3);

        return new JsonResponse($this->presenter->mine($userId));
    }

    public function end(Request $request, string $id, EndParty $end): JsonResponse
    {
        $userId = PartiesController::userId($request);
        $end->handle($id, $userId);

        return new JsonResponse($this->presenter->mine($userId));
    }

    public function remind(Request $request, string $id, string $memberId, RemindMember $remind): JsonResponse
    {
        $userId = PartiesController::userId($request);
        $party = $remind->handle($id, $userId, $memberId);

        return new JsonResponse($this->presenter->forViewer(Party::query()->findOrFail($party->id), $userId));
    }

    public function remove(Request $request, string $id, string $memberId, RemoveMember $remove): JsonResponse
    {
        $userId = PartiesController::userId($request);
        $party = $remove->handle($id, $userId, $memberId);

        return new JsonResponse($this->presenter->forViewer(Party::query()->findOrFail($party->id), $userId));
    }
}
