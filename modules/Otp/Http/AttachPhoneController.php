<?php

namespace Modules\Otp\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Contracts\Accounts;
use Modules\Otp\Actions\ConsumeChallenge;
use Modules\Otp\Actions\SendOtp;
use Modules\Otp\Models\OtpChallenge;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class AttachPhoneController
{
    public function send(SendOtpRequest $request, SendOtp $send, Accounts $accounts): JsonResponse
    {
        $userId = $this->userId($request);
        $phone = (string) $request->string('phone');

        $owner = $accounts->ownerOfPhone($phone);
        if ($owner !== null && $owner !== $userId) {
            throw new ApiException(ApiErrorCode::PhoneTaken, 'Phone in use');
        }

        return new JsonResponse($send($phone, OtpChallenge::ATTACH, $userId, (string) $request->string('locale')));
    }

    public function verify(VerifyOtpRequest $request, ConsumeChallenge $consume, Accounts $accounts): JsonResponse
    {
        $userId = $this->userId($request);
        // Scoped to this account: someone else's challenge id is simply "expired" here.
        $challenge = $consume(
            (string) $request->string('challengeId'),
            (string) $request->string('code'),
            OtpChallenge::ATTACH,
            $userId,
        );

        return new JsonResponse($accounts->attachPhone($userId, $challenge->phone));
    }

    private function userId(Request $request): string
    {
        return (string) $request->user()->getAuthIdentifier();
    }
}
