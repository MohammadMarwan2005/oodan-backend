<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeleteUser;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(Request $request): UserResource
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $user = $request->user();
        $user->update(['name' => $data['name']]);

        return new UserResource($user);
    }

    /**
     * Confirmed with the password, or with a Google ID token of the linked Google account.
     */
    public function destroy(Request $request, GoogleIdTokenVerifier $google, DeleteUser $deleteUser): Response
    {
        $data = $request->validate([
            'password' => ['required_without:id_token', 'nullable', 'string'],
            'id_token' => ['required_without:password', 'nullable', 'string'],
        ]);

        $user = $request->user();

        $confirmed = match (true) {
            isset($data['password']) => $user->password !== null && Hash::check($data['password'], $user->password),
            $user->google_sub !== null => $this->googleSubject($google, $data['id_token']) === $user->google_sub,
            default => false,
        };

        if (! $confirmed) {
            throw new ApiException(403, 'invalid_confirmation', 'The confirmation does not match this account.');
        }

        $deleteUser->handle($user);

        return response()->noContent();
    }

    private function googleSubject(GoogleIdTokenVerifier $google, string $idToken): ?string
    {
        try {
            return $google->verify($idToken)['sub'];
        } catch (ApiException) {
            return null;
        }
    }
}
