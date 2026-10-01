<?php

namespace Modules\Verification\Didit;

/** Didit session status (+ decision) → our photo status. Null means "leave it as it is". */
final class DecisionMapper
{
    /** @param  array<string, mixed>|null  $decision */
    public static function photoStatus(string $status, ?array $decision): ?string
    {
        return match ($status) {
            'In Progress', 'In Review', 'Resubmitted', 'Awaiting User' => 'pending',
            'Approved' => self::allApproved($decision) ? 'verified' : 'rejected',
            'Declined' => 'rejected',
            // A check that was dropped or timed out never shows as failed.
            'Abandoned', 'Expired', 'Kyc Expired', 'Not Started' => 'unverified',
            default => null,
        };
    }

    /** Biometric Authentication: approve only when every liveness check and face match approved. */
    private static function allApproved(?array $decision): bool
    {
        foreach (['liveness_checks', 'face_matches'] as $kind) {
            foreach ((array) ($decision[$kind] ?? []) as $check) {
                if (($check['status'] ?? null) !== 'Approved') {
                    return false;
                }
            }
        }

        return true;
    }
}
