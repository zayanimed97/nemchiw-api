<?php

namespace Modules\Verification\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Media\Contracts\Photos;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Verification\Didit\DecisionMapper;
use Modules\Verification\Didit\WebhookSignature;
use Modules\Verification\Models\VerificationSession;

final class DiditWebhookController
{
    public function __invoke(Request $request, Photos $photos): JsonResponse
    {
        $signature = new WebhookSignature(
            (string) config('verification.didit.webhook_secret'),
            (int) config('verification.webhook_tolerance_seconds'),
        );
        $raw = $request->getContent();
        if (! $signature->verify($raw, $request->header('X-Timestamp'), $request->header('X-Signature'), $request->header('X-Signature-V2'))) {
            throw new ApiException(ApiErrorCode::Unauthenticated, 'Invalid signature');
        }

        $event = json_decode($raw, true);
        $eventId = is_array($event) ? ($event['event_id'] ?? null) : null;
        $sessionId = is_array($event) ? ($event['session_id'] ?? null) : null;
        if (($event['webhook_type'] ?? null) !== 'status.updated'
            || ! is_string($eventId) || $eventId === '' || strlen($eventId) > 64
            || ! is_string($sessionId) || strlen($sessionId) > 64) {
            // Signed by Didit but not a session event we understand: acknowledge, do nothing.
            return new JsonResponse(['ok' => true]);
        }

        DB::transaction(function () use ($event, $eventId, $sessionId, $photos) {
            $fresh = DB::table('webhook_events')->insertOrIgnore([
                'event_id' => $eventId, 'session_id' => $sessionId, 'received_at' => now(),
            ]);
            // Looked up by the session we created, never by the untrusted vendor_data.
            $session = VerificationSession::query()->lockForUpdate()->find($sessionId);
            if ($fresh === 0 || $session === null || ! is_string($event['status'] ?? null)) {
                return;
            }
            // Didit retries deliveries, so an earlier status can arrive after a later one.
            if (DecisionMapper::rank($event['status']) < DecisionMapper::rank($session->status)) {
                return;
            }
            $session->forceFill(['status' => $event['status']])->save();

            // Several sessions can check the same photo (cancel, then retry). Only the newest
            // one speaks for it: an older one timing out must not undo a newer verdict.
            $newest = VerificationSession::query()->where('photo_id', $session->photo_id)
                ->orderByDesc('created_at')->orderByDesc('session_id')->value('session_id');
            if ($newest !== $session->session_id) {
                return;
            }

            $status = DecisionMapper::photoStatus($event['status'], is_array($event['decision'] ?? null) ? $event['decision'] : null);
            // Only the photo this session checked, and only while it is still the current one.
            if ($status !== null && ($photos->current($session->user_id)['id'] ?? null) === $session->photo_id) {
                $photos->setStatus($session->photo_id, $status);
            }
        });

        return new JsonResponse(['ok' => true]);
    }
}
