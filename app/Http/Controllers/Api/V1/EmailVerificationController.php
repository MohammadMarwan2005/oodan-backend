<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuthCodePurpose;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthCodes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailVerificationController extends Controller
{
    public function __construct(private AuthCodes $codes) {}

    public function send(Request $request): Response
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $this->codes->send($user, AuthCodePurpose::VerifyEmail);
        }

        return response()->noContent();
    }

    public function verify(Request $request): UserResource
    {
        $data = $request->validate(['code' => ['required', 'string', 'digits:6']]);
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $this->codes->consume($user, AuthCodePurpose::VerifyEmail, $data['code']);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return new UserResource($user);
    }
}
