<?php

namespace Modules\Chat\Http;

use Illuminate\Http\Request;
use Modules\Chat\Models\Message;
use Modules\Media\Contracts\ChatPhotos;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** GET /conversations/{id}/photos/{hash}-{size}.webp, for the two participants only. */
final class PhotoController
{
    public function __invoke(Request $request, string $id, string $file, ChatPhotos $photos): BinaryFileResponse
    {
        $conversation = ConversationAccess::for($request, $id);
        [$hash, $size] = explode('-', basename($file, '.webp'));
        $known = Message::query()->where('conversation_id', $conversation->id)->where('photo_hash', $hash)->exists();
        $path = $known ? $photos->path($conversation->id, $hash, (int) $size) : null;
        if ($path === null) {
            throw new ApiException(ApiErrorCode::NotFound, 'No such photo');
        }

        return response()->file($path, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
