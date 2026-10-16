<?php

namespace App\Services\Auth;

use App\Enums\AuthCodePurpose;
use App\Exceptions\ApiException;
use App\Mail\AuthCodeMail;
use App\Models\AuthCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * 6-digit email codes for email verification and password reset.
 * Codes expire after 15 minutes and allow 5 attempts; a new code replaces older ones.
 */
class AuthCodes
{
    public const TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public function send(User $user, AuthCodePurpose $purpose): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $user->authCodes()->where('purpose', $purpose)->whereNull('used_at')->delete();

        $user->authCodes()->create([
            'purpose' => $purpose,
            'code_hash' => $this->hash($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'created_at' => now(),
        ]);

        Mail::to($user)->queue(new AuthCodeMail($purpose, $code));
    }

    /**
     * Consume the user's current code, or throw `invalid_code` / `too_many_attempts`.
     */
    public function consume(User $user, AuthCodePurpose $purpose, string $code): void
    {
        /** @var AuthCode|null $authCode */
        $authCode = $user->authCodes()
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($authCode === null) {
            throw $this->invalidCode();
        }

        if ($authCode->attempts >= self::MAX_ATTEMPTS) {
            throw $this->tooManyAttempts();
        }

        if (! hash_equals($authCode->code_hash, $this->hash($code))) {
            $authCode->increment('attempts');

            throw $authCode->attempts >= self::MAX_ATTEMPTS ? $this->tooManyAttempts() : $this->invalidCode();
        }

        $authCode->update(['used_at' => now()]);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    private function invalidCode(): ApiException
    {
        return new ApiException(422, 'invalid_code', 'The code is invalid or has expired.');
    }

    private function tooManyAttempts(): ApiException
    {
        return new ApiException(422, 'too_many_attempts', 'Too many wrong attempts. Request a new code.');
    }
}
