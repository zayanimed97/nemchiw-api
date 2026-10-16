<?php

namespace Modules\Parties\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Parties\Actions\AnswerInvitation;
use Modules\Parties\Actions\Invite;
use Modules\Parties\Models\Party;
use Modules\Parties\Services\PartyPresenter;

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
}
