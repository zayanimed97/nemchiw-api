<?php

use Illuminate\Support\Facades\Route;
use Modules\Parties\Http\FriendsController;
use Modules\Parties\Http\PartiesController;
use Modules\Parties\Http\PartyMembersController;

$ulid = '[0-9A-Za-z]{26}'; // Laravel stores ULIDs lowercase

Route::middleware('auth:sanctum')->group(function () use ($ulid) {
    Route::get('me/party', [PartiesController::class, 'mine']);
    Route::post('parties', [PartiesController::class, 'store']);
    Route::get('parties/{id}/readiness', [PartiesController::class, 'readiness'])->where('id', $ulid);
    Route::post('parties/{id}/lookup', [PartiesController::class, 'lookup'])->where('id', $ulid);
    Route::post('parties/{id}/invites', [PartyMembersController::class, 'invite'])->where('id', $ulid);
    Route::post('parties/{id}/accept', [PartyMembersController::class, 'accept'])->where('id', $ulid);
    Route::post('parties/{id}/decline', [PartyMembersController::class, 'decline'])->where('id', $ulid);
    Route::post('parties/{id}/leave', [PartyMembersController::class, 'leave'])->where('id', $ulid);
    Route::post('parties/{id}/end', [PartyMembersController::class, 'end'])->where('id', $ulid);
    Route::post('parties/{id}/members/{memberId}/remind', [PartyMembersController::class, 'remind'])->where(['id' => $ulid, 'memberId' => $ulid]);
    Route::post('parties/{id}/members/{memberId}/remove', [PartyMembersController::class, 'remove'])->where(['id' => $ulid, 'memberId' => $ulid]);
    Route::get('me/friends', [FriendsController::class, 'index']);
    Route::post('me/friends/{userId}/remove', [FriendsController::class, 'remove'])->where('userId', $ulid);
});
