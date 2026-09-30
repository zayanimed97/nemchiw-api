<?php

namespace Modules\Otp\Http;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Contracts\Accounts;
use Modules\Otp\Actions\ConsumeChallenge;
use Modules\Otp\Actions\SendOtp;
use Modules\Otp\Models\OtpChallenge;

final class SignInOtpController
{
    /** Same answer whether or not the phone has an account: no enumeration. */
    public function send(SendOtpRequest $request, SendOtp $send): JsonResponse
    {
        return new JsonResponse($send(
            (string) $request->string('phone'),
            OtpChallenge::SIGN_IN,
            null,
            (string) $request->string('locale'),
        ));
    }

    public function verify(VerifyOtpRequest $request, ConsumeChallenge $consume, Accounts $accounts): JsonResponse
    {
        $challenge = $consume(
            (string) $request->string('challengeId'),
            (string) $request->string('code'),
            OtpChallenge::SIGN_IN,
            null,
        );

        return new JsonResponse($accounts->signInWithPhone($challenge->phone));
    }
}
