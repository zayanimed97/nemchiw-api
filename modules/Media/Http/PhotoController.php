<?php

namespace Modules\Media\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Identity\Contracts\Accounts;
use Modules\Media\Images\Reencoder;
use Modules\Media\Models\Photo;
use Modules\Media\Services\PhotoStore;

final class PhotoController
{
    public function __construct(private readonly PhotoStore $photos) {}

    /** POST /me/photo: answers with the updated profile, like the app expects. */
    public function store(Request $request, Reencoder $reencoder, Accounts $accounts): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'max:5120']]);
        $userId = (string) $request->user()->getAuthIdentifier();

        $jpeg = $reencoder->toJpeg((string) file_get_contents($request->file('photo')->getRealPath()));
        $this->photos->replace($userId, $jpeg);

        return new JsonResponse($accounts->profile($userId));
    }

    /** The owner always; anyone else only once the photo is verified. */
    public function show(Request $request, string $photo): Response
    {
        $model = Photo::query()->find($photo);
        $viewer = (string) $request->user()->getAuthIdentifier();
        if ($model === null || ($model->user_id !== $viewer && $model->status !== 'verified')) {
            abort(404);
        }

        return new Response($this->photos->disk()->get($model->path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline',
        ]);
    }
}
