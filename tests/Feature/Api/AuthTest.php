<?php

namespace Tests\Feature\Api;

use App\Mail\AuthCodeMail;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use InteractsWithApi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function register_creates_an_unverified_account_and_emails_a_code(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'سارة',
            'email' => 'Sara@Example.com',
            'password' => 'secret-password',
        ], $this->deviceHeaders());

        $response->assertCreated()
            ->assertJsonPath('user.email', 'sara@example.com')
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonPath('user.has_password', true)
            ->assertJsonPath('tokens.token_type', 'Bearer')
            ->assertJsonPath('tokens.expires_in', 900);

        Mail::assertQueued(AuthCodeMail::class, fn (AuthCodeMail $mail) => $mail->hasTo('sara@example.com')
            && preg_match('/^\d{6}$/', $mail->code) === 1);
    }

    #[Test]
    public function register_requires_device_headers(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'x', 'email' => 'x@example.com', 'password' => 'secret-password',
        ])->assertStatus(400)->assertJsonPath('error.code', 'invalid_device_headers');
    }

    #[Test]
    public function register_rejects_a_taken_email_with_field_errors(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'x', 'email' => 'taken@example.com', 'password' => 'short',
        ], $this->deviceHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['email', 'password']]]);
    }

    #[Test]
    public function the_emailed_code_verifies_the_email(): void
    {
        $session = $this->postJson('/api/v1/auth/register', [
            'name' => 'x', 'email' => 'v@example.com', 'password' => 'secret-password',
        ], $this->deviceHeaders())->json();

        $code = null;
        Mail::assertQueued(AuthCodeMail::class, function (AuthCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $headers = ['Authorization' => 'Bearer '.$session['tokens']['access_token']];
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/email/verify', ['code' => $wrong], $headers)
            ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_code');

        $this->postJson('/api/v1/auth/email/verify', ['code' => $code], $headers)
            ->assertOk()->assertJsonPath('email_verified', true);
    }

    #[Test]
    public function a_code_locks_after_five_wrong_attempts(): void
    {
        $user = User::factory()->unverified()->create();
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/auth/email/send-code', [], $headers)->assertNoContent();

        $code = null;
        Mail::assertQueued(AuthCodeMail::class, function (AuthCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $wrong = $code === '000000' ? '111111' : '000000';

        // Stay under the 5 requests/minute limit of the code endpoints.
        $this->travel(61)->seconds();

        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/api/v1/auth/email/verify', ['code' => $wrong], $headers)->assertJsonPath('error.code', 'invalid_code');
        }

        $this->postJson('/api/v1/auth/email/verify', ['code' => $wrong], $headers)->assertJsonPath('error.code', 'too_many_attempts');

        $this->travel(61)->seconds();

        $this->postJson('/api/v1/auth/email/verify', ['code' => $code], $headers)->assertJsonPath('error.code', 'too_many_attempts');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function codes_expire_after_fifteen_minutes(): void
    {
        $user = User::factory()->unverified()->create();
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/auth/email/send-code', [], $headers)->assertNoContent();

        $code = null;
        Mail::assertQueued(AuthCodeMail::class, function (AuthCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->travel(16)->minutes();

        $this->postJson('/api/v1/auth/email/verify', ['code' => $code], $this->authHeaders($user))
            ->assertJsonPath('error.code', 'invalid_code');
    }

    #[Test]
    public function login_returns_a_session_and_rejects_wrong_passwords(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/v1/auth/login', ['email' => 'A@example.com', 'password' => 'secret-password'], $this->deviceHeaders())
            ->assertOk()->assertJsonPath('user.email', 'a@example.com');

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong-password'], $this->deviceHeaders())
            ->assertUnauthorized()->assertJsonPath('error.code', 'invalid_credentials');
    }

    #[Test]
    public function google_only_accounts_cannot_log_in_with_a_password(): void
    {
        User::factory()->google()->create(['email' => 'g@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'g@example.com', 'password' => 'anything'], $this->deviceHeaders())
            ->assertUnauthorized()->assertJsonPath('error.code', 'invalid_credentials');
    }

    #[Test]
    public function login_is_rate_limited_per_ip_and_email(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong-password'], $this->deviceHeaders())
                ->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong-password'], $this->deviceHeaders())
            ->assertTooManyRequests()->assertJsonPath('error.code', 'too_many_requests');

        $this->postJson('/api/v1/auth/login', ['email' => 'b@example.com', 'password' => 'wrong-password'], $this->deviceHeaders())
            ->assertUnauthorized();
    }

    #[Test]
    public function only_one_refresh_token_is_active_per_device(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        foreach ([1, 2] as $_) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'], $this->deviceHeaders())->assertOk();
        }
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'], $this->deviceHeaders('0192f0a0-2222-7000-8000-000000000002'))->assertOk();

        $this->assertSame(1, RefreshToken::active()->where('device_id', $this->deviceId)->count());
        $this->assertSame(2, RefreshToken::active()->count());
    }

    #[Test]
    public function refresh_rotates_the_token_and_reuse_revokes_the_device(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $first = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'], $this->deviceHeaders())
            ->json('tokens.refresh_token');

        $second = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first], $this->deviceHeaders())
            ->assertOk()->json('refresh_token');

        $this->assertNotSame($first, $second);

        // Reusing the rotated token is a theft signal: every token of this device is revoked.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first], $this->deviceHeaders())
            ->assertUnauthorized()->assertJsonPath('error.code', 'invalid_refresh_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $second], $this->deviceHeaders())
            ->assertUnauthorized();
    }

    #[Test]
    public function refresh_rejects_tokens_from_another_device_and_expired_tokens(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'], $this->deviceHeaders())
            ->json('tokens.refresh_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $token], $this->deviceHeaders('0192f0a0-2222-7000-8000-000000000002'))
            ->assertUnauthorized();

        $this->travel(61)->days();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $token], $this->deviceHeaders())
            ->assertUnauthorized();
    }

    #[Test]
    public function access_tokens_expire_after_fifteen_minutes(): void
    {
        $headers = $this->authHeaders(User::factory()->create());

        $this->getJson('/api/v1/me', $headers)->assertOk();

        $this->travel(16)->minutes();

        $this->getJson('/api/v1/me', $headers)->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }

    #[Test]
    public function logout_revokes_only_this_devices_refresh_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $otherDevice = '0192f0a0-2222-7000-8000-000000000002';

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'], $this->deviceHeaders())->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'], $this->deviceHeaders($otherDevice))->assertOk();

        $this->postJson('/api/v1/auth/logout', [], $this->authHeaders($user))->assertNoContent();

        $this->assertSame(0, RefreshToken::active()->where('device_id', $this->deviceId)->count());
        $this->assertSame(1, RefreshToken::active()->where('device_id', $otherDevice)->count());
    }

    #[Test]
    public function a_tampered_access_token_is_rejected(): void
    {
        $headers = $this->authHeaders(User::factory()->create());
        $headers['Authorization'] .= 'x';

        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }
}
