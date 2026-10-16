<?php

namespace Modules\Parties\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Parties\Actions\CreateParty;
use Modules\Parties\Actions\LookupContact;
use Modules\Parties\Models\Party;
use Modules\Parties\Services\PartyPresenter;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class PartiesController
{
    public function __construct(private readonly PartyPresenter $presenter) {}

    public function mine(Request $request): JsonResponse
    {
        return new JsonResponse($this->presenter->mine(self::userId($request)));
    }

    public function store(Request $request, CreateParty $create): JsonResponse
    {
        $userId = self::userId($request);

        return new JsonResponse($this->presenter->forViewer($create->handle($userId), $userId), 201);
    }

    public function lookup(Request $request, string $id, LookupContact $lookup): JsonResponse
    {
        $data = $request->validate(['contact' => ['required', 'string', 'max:254']]);
        $userId = self::userId($request);
        $party = Party::query()->whereKey(strtolower($id))->where('state', '!=', Party::OVER)->first();
        if ($party === null) {
            throw new ApiException(ApiErrorCode::NotFound, 'Not found');
        }
        if ($party->leader_id !== $userId) {
            throw new ApiException(ApiErrorCode::NotLeader, 'Only the leader can search');
        }

        return new JsonResponse($lookup->handle($party, $userId, $data['contact']));
    }

    public static function userId(Request $request): string
    {
        return (string) $request->user()->getAuthIdentifier();
    }
}
