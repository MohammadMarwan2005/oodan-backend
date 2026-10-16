<?php

namespace Tests\Feature\Api;

use App\Mail\AuthCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class SyncAccessTest extends TestCase
{
    use InteractsWithApi, RefreshDatabase;

    #[Test]
    public function sync_requires_a_verified_email(): void
    {
        $headers = $this->authHeaders(User::factory()->unverified()->create());

        $this->postJson('/api/v1/sync/push', [], $headers)->assertForbidden()->assertJsonPath('error.code', 'email_not_verified');
        $this->getJson('/api/v1/sync/pull', $headers)->assertForbidden()->assertJsonPath('error.code', 'email_not_verified');
    }

    #[Test]
    public function sync_requires_device_headers(): void
    {
        $headers = $this->authHeaders(User::factory()->create());
        unset($headers['X-Platform']);

        $this->getJson('/api/v1/sync/pull', $headers)->assertStatus(400)->assertJsonPath('error.code', 'invalid_device_headers');
    }

    #[Test]
    public function register_then_verify_unlocks_sync(): void
    {
        Mail::fake();

        $token = $this->postJson('/api/v1/auth/register', [
            'name' => 'x', 'email' => 'flow@example.com', 'password' => 'secret-password',
        ], $this->deviceHeaders())->json('tokens.access_token');

        $headers = $this->deviceHeaders() + ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/sync/pull', $headers)->assertForbidden();

        $code = null;
        Mail::assertQueued(AuthCodeMail::class, function (AuthCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/v1/auth/email/verify', ['code' => $code], $headers)->assertOk();

        $this->getJson('/api/v1/sync/pull', $headers)->assertOk()->assertJsonPath('has_more', false);
    }

    #[Test]
    public function sync_is_rate_limited_per_user(): void
    {
        $headers = $this->authHeaders(User::factory()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/sync/pull', $headers)->assertOk();
        }

        $this->getJson('/api/v1/sync/pull', $headers)->assertTooManyRequests();
    }
}
