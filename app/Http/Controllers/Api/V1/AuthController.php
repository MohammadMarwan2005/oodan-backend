<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuthCodePurpose;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthCodes;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Auth\RefreshTokens;
use App\Services\Devices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private RefreshTokens $refreshTokens, private Devices $devices) {}

    public function register(Request $request, AuthCodes $codes): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
        ]);

        $codes->send($user, AuthCodePurpose::VerifyEmail);

        return $this->session($request, $user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if ($user?->password === null || ! Hash::check($data['password'], $user->password)) {
            throw new ApiException(401, 'invalid_credentials', 'The email or password is wrong.');
        }

        return $this->session($request, $user);
    }

    public function google(Request $request, GoogleIdTokenVerifier $google): JsonResponse
    {
        $data = $request->validate(['id_token' => ['required', 'string']]);

        $claims = $google->verify($data['id_token']);

        if ($user = User::where('google_sub', $claims['sub'])->first()) {
            return $this->session($request, $user);
        }

        if ($claims['email'] === null) {
            throw new ApiException(401, 'invalid_google_token', 'The Google token has no email.');
        }

        if ($user = User::where('email', $claims['email'])->first()) {
            if (! $claims['email_verified']) {
                throw new ApiException(409, 'account_conflict', 'An account with this email already exists.');
            }

            $user->forceFill([
                'google_sub' => $claims['sub'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return $this->session($request, $user);
        }

        $user = User::create([
            'name' => $claims['name'] ?: Str::before($claims['email'], '@'),
            'email' => $claims['email'],
            'google_sub' => $claims['sub'],
            'email_verified_at' => $claims['email_verified'] ? now() : null,
        ]);

        return $this->session($request, $user, 201);
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);

        $deviceId = $request->attributes->get('device_id');
        $user = $this->refreshTokens->consume($data['refresh_token'], $deviceId);

        $this->devices->touch($user, $request);

        return response()->json($this->refreshTokens->issuePair($user, $deviceId));
    }

    public function logout(Request $request): Response
    {
        $this->refreshTokens->revokeDevice($request->user(), $request->attributes->get('token_device_id'));

        return response()->noContent();
    }

    private function session(Request $request, User $user, int $status = 200): JsonResponse
    {
        $this->devices->touch($user, $request);

        return response()->json([
            'tokens' => $this->refreshTokens->issuePair($user, $request->attributes->get('device_id')),
            'user' => new UserResource($user),
        ], $status);
    }
}
