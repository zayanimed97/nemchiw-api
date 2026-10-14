<?php

namespace Modules\Push\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Push\Models\PushToken;

use function Illuminate\Support\defer;

/** Expo's push service, after the response. A failure is logged and never reaches the caller. */
final class PushSender
{
    /** @param list<Notice> $notices */
    public function send(array $notices): void
    {
        if ($notices === []) {
            return;
        }
        $tokens = PushToken::query()
            ->whereIn('user_id', array_values(array_unique(array_map(fn (Notice $n) => $n->userId, $notices))))
            ->get()
            ->groupBy('user_id');
        $messages = [];
        foreach ($notices as $notice) {
            foreach ($tokens[$notice->userId] ?? [] as $token) {
                $messages[] = ['token' => $token->token, 'message' => $this->render($notice, $token)];
            }
        }
        if ($messages === []) {
            return;
        }
        defer(function () use ($messages) {
            foreach (array_chunk($messages, (int) config('push.batch')) as $chunk) {
                $this->post($chunk);
            }
        });
    }

    /** @return array<string, mixed> */
    private function render(Notice $notice, PushToken $token): array
    {
        $locale = $token->locale;
        $params = array_map(
            fn ($value) => is_array($value) ? (string) ($value[$locale] ?? reset($value)) : (string) $value,
            $notice->params,
        );
        $message = [
            'to' => $token->token,
            'title' => __("push::push.{$notice->title}", $params, $locale),
            'priority' => 'high',
            'sound' => 'default',
            'channelId' => 'default',
            'data' => $notice->data === [] ? new \stdClass : $notice->data, // {} not [] when empty
        ];
        $body = $notice->rawBody !== null
            ? Str::limit($notice->rawBody, 180)
            : ($notice->body !== null ? __("push::push.{$notice->body}", $params, $locale) : null);
        if ($body !== null) {
            $message['body'] = $body;
        }
        if ($notice->thread !== null) {
            $message['threadId'] = $notice->thread; // iOS: one stack per conversation
            $message['tag'] = $notice->thread;      // Android: the newest replaces the last
        }

        return $message;
    }

    /** @param list<array{token: string, message: array<string, mixed>}> $chunk */
    private function post(array $chunk): void
    {
        try {
            $headers = config('push.access_token') ? ['Authorization' => 'Bearer '.config('push.access_token')] : [];
            $response = Http::timeout(10)->acceptJson()->withHeaders($headers)
                ->post((string) config('push.expo_url'), array_column($chunk, 'message'));
            if (! $response->successful()) {
                Log::warning('push.expo_failed', ['status' => $response->status()]);

                return;
            }
            foreach ((array) $response->json('data', []) as $i => $ticket) {
                if (($ticket['status'] ?? null) === 'error' && ($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    PushToken::query()->where('token', $chunk[$i]['token'])->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('push.expo_failed', ['error' => $e->getMessage()]);
        }
    }
}
