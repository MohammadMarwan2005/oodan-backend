<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesGoogleTokens;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use FakesGoogleTokens, InteractsWithApi, RefreshDatabase;

    #[Test]
    public function a_new_google_user_gets_a_verified_account(): void
    {
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken()], $this->deviceHeaders())
            ->assertCreated()
            ->assertJsonPath('user.email', 'person@gmail.com')
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonPath('user.has_password', false)
            ->assertJsonPath('user.google_linked', true);

        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken()], $this->deviceHeaders())
            ->assertOk();

        $this->assertSame(1, User::count());
    }

    #[Test]
    public function it_links_an_existing_account_when_google_verified_the_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'person@gmail.com']);

        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken()], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonPath('user.has_password', true);

        $this->assertSame('110000000000000000001', $user->fresh()->google_sub);
    }

    #[Test]
    public function it_does_not_link_when_google_has_not_verified_the_email(): void
    {
        User::factory()->create(['email' => 'person@gmail.com']);

        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken(['email_verified' => false])], $this->deviceHeaders())
            ->assertConflict()
            ->assertJsonPath('error.code', 'account_conflict');
    }

    #[Test]
    public function it_rejects_tokens_for_another_audience_issuer_or_signature_or_expired(): void
    {
        $tokens = [
            $this->googleIdToken(['aud' => 'someone-else.apps.googleusercontent.com']),
            $this->googleIdToken(['iss' => 'https://evil.example.com']),
            $this->googleIdToken(['exp' => time() - 3600, 'iat' => time() - 7200]),
            $this->googleIdToken().'x',
            'not-a-jwt',
        ];

        foreach ($tokens as $token) {
            $this->postJson('/api/v1/auth/google', ['id_token' => $token], $this->deviceHeaders())
                ->assertUnauthorized()
                ->assertJsonPath('error.code', 'invalid_google_token');
        }

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function a_google_only_account_confirms_deletion_with_an_id_token(): void
    {
        $user = User::factory()->google('110000000000000000001')->create(['email' => 'person@gmail.com']);

        $this->deleteJson('/api/v1/me', ['id_token' => $this->googleIdToken(['sub' => '999'])], $this->authHeaders($user))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'invalid_confirmation');

        $this->deleteJson('/api/v1/me', ['id_token' => $this->googleIdToken()], $this->authHeaders($user))
            ->assertNoContent();

        $this->assertModelMissing($user);
    }
}
