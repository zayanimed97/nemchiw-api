<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\BroadcastAuthController;
use Modules\Chat\Http\ConversationsController;
use Modules\Chat\Http\MessagesController;
use Modules\Chat\Http\PhotoController;
use Modules\Chat\Http\ReadController;
use Modules\Shared\Http\LimitBodySize;

$ulid = '[0-9A-Za-z]{26}'; // Laravel stores ULIDs lowercase

Route::middleware('auth:sanctum')->group(function () use ($ulid) {
    Route::get('me/conversations', ConversationsController::class);
    Route::post('broadcasting/auth', BroadcastAuthController::class)->middleware('throttle:60,1');
    Route::get('conversations/{id}/messages', [MessagesController::class, 'index'])->where('id', $ulid);
    Route::post('conversations/{id}/messages', [MessagesController::class, 'store'])
        ->where('id', $ulid)
        ->withoutMiddleware(LimitBodySize::class)
        ->middleware([LimitBodySize::class.':'.((int) config('chat.photo_max_kb') * 1024 + 262_144), 'throttle:chat-messages']);
    Route::post('conversations/{id}/read', ReadController::class)->where('id', $ulid);
    Route::get('conversations/{id}/photos/{file}', PhotoController::class)
        ->where(['id' => $ulid, 'file' => '[0-9a-f]{32}-(480|1280)\.webp']);
});
