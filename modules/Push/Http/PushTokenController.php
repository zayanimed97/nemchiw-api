<?php

namespace Modules\Push\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Push\Models\PushToken;

final class PushTokenController
{
    private const TOKEN = ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[^\]]+\]$/'];

    /** A phone has one token; it belongs to whoever signed in on it last. */
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'token' => self::TOKEN,
            'platform' => ['required', 'in:ios,android'],
            'locale' => ['required', 'in:ar,fr,en'],
        ]);
        PushToken::query()->updateOrCreate(['token' => $data['token']], [
            'user_id' => (string) $request->user()->getAuthIdentifier(),
            'platform' => $data['platform'],
            'locale' => $data['locale'],
            'last_seen_at' => now(),
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['token' => self::TOKEN]);
        PushToken::query()->where('token', $data['token'])->where('user_id', (string) $request->user()->getAuthIdentifier())->delete();

        return response()->noContent();
    }
}
