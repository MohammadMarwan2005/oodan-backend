<?php

namespace Tests\Feature\Api;

use App\Mail\AuthCodeMail;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use InteractsWithApi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    #[Test]
    public function forgot_is_always_204_and_only_mails_existing_accounts(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])->assertNoContent();
        Mail::assertNothingQueued();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'a@example.com'])->assertNoContent();
        Mail::assertQueued(AuthCodeMail::class, 1);
    }

    #[Test]
    public function reset_changes_the_password_and_revokes_every_refresh_token(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => 'old-password']);
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'old-password'], $this->deviceHeaders())->assertOk();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'a@example.com'])->assertNoContent();

        $code = null;
        Mail::assertQueued(AuthCodeMail::class, function (AuthCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/v1/auth/password/reset', ['email' => 'a@example.com', 'code' => $code, 'password' => 'new-password'])
            ->assertNoContent();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertSame(0, RefreshToken::active()->count());

        // A used code can't be replayed.
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'a@example.com', 'code' => $code, 'password' => 'other-password'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_code');
    }

    #[Test]
    public function reset_for_an_unknown_email_looks_like_a_wrong_code(): void
    {
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'nobody@example.com', 'code' => '123456', 'password' => 'new-password'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_code');
    }
}
