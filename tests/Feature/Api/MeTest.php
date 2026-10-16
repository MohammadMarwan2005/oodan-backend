<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class MeTest extends TestCase
{
    use InteractsWithApi, RefreshDatabase;

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }

    #[Test]
    public function it_shows_and_updates_the_user(): void
    {
        $user = User::factory()->create(['name' => 'Old']);

        $this->getJson('/api/v1/me', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email_verified', true);

        $this->patchJson('/api/v1/me', ['name' => 'جديد'], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('name', 'جديد');

        $this->patchJson('/api/v1/me', ['name' => ''], $this->authHeaders($user))->assertUnprocessable();
    }

    #[Test]
    public function delete_requires_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->deleteJson('/api/v1/me', [], $this->authHeaders($user))->assertUnprocessable();

        $this->deleteJson('/api/v1/me', ['password' => 'wrong-password'], $this->authHeaders($user))
            ->assertForbidden()->assertJsonPath('error.code', 'invalid_confirmation');

        $this->deleteJson('/api/v1/me', ['password' => 'secret-password'], $this->authHeaders($user))->assertNoContent();

        $this->assertModelMissing($user);
        $this->getJson('/api/v1/me', $this->authHeaders($user))->assertUnauthorized();
    }
}
