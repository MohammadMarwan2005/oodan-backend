<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuthCodePurpose;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthCodes;
use App\Services\Auth\RefreshTokens;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PasswordResetController extends Controller
{
    public function __construct(private AuthCodes $codes) {}

    /**
     * Always 204 so the endpoint can't be used to discover accounts.
     */
    public function forgot(Request $request): Response
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        if ($user = User::where('email', strtolower($data['email']))->first()) {
            $this->codes->send($user, AuthCodePurpose::ResetPassword);
        }

        return response()->noContent();
    }

    public function reset(Request $request, RefreshTokens $refreshTokens): Response
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first()
            ?? throw new ApiException(422, 'invalid_code', 'The code is invalid or has expired.');

        $this->codes->consume($user, AuthCodePurpose::ResetPassword, $data['code']);

        // The code proves the user owns the mailbox, so the email counts as verified.
        $user->forceFill([
            'password' => $data['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $refreshTokens->revokeAll($user);

        return response()->noContent();
    }
}
