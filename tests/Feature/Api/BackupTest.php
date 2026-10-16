<?php

namespace Tests\Feature\Api;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSyncItems;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use BuildsSyncItems, InteractsWithApi, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function backupItem(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid7(),
            'reason' => 'clear',
            'shots_count' => 2,
            'created_at' => '2026-10-16T20:00:00.000Z',
            'deleted_at' => null,
            'payload' => [
                'format' => 'oodan-export',
                'version' => 1,
                'exported_at' => '2026-10-16T20:00:00.000Z',
                'shots' => [$this->shotItem(), $this->shotItem(['color' => 'black', 'note' => 'ملاحظة'])],
                'categories' => [$this->categoryItem()],
                'settings' => ['dot_size' => 14, 'intensity' => 80, 'sound_enabled' => true],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function push(array $body): TestResponse
    {
        return $this->postJson('/api/v1/sync/push', $body, $this->authHeaders($this->user));
    }

    #[Test]
    public function a_pushed_backup_is_pulled_as_metadata_and_fetched_with_its_payload(): void
    {
        $backup = $this->backupItem();

        $this->push(['backups' => [$backup]])
            ->assertOk()
            ->assertJsonPath('accepted.backups', [$backup['id']])
            ->assertJsonPath('seq', 1);

        $pull = $this->getJson('/api/v1/sync/pull', $this->authHeaders($this->user))
            ->assertOk()
            ->assertJsonPath('backups.0.id', $backup['id'])
            ->assertJsonPath('backups.0.shots_count', 2)
            ->assertJsonPath('backups.0.seq', 1);
        $this->assertArrayNotHasKey('payload', $pull->json('backups.0'));

        $this->getJson("/api/v1/backups/{$backup['id']}", $this->authHeaders($this->user))
            ->assertOk()
            ->assertJsonPath('payload.format', 'oodan-export')
            ->assertJsonPath('payload.shots.1.note', 'ملاحظة')
            ->assertJsonPath('payload.settings.dot_size', 14);
    }

    #[Test]
    public function an_empty_settings_object_stays_an_object(): void
    {
        $backup = $this->backupItem();
        $backup['payload']['settings'] = [];

        $this->push(['backups' => [$backup]])->assertJsonPath('rejected', []);

        $response = $this->getJson("/api/v1/backups/{$backup['id']}", $this->authHeaders($this->user))->assertOk();
        $this->assertStringContainsString('"settings":{}', $response->getContent());
    }

    #[Test]
    public function a_new_backup_needs_a_payload(): void
    {
        $backup = $this->backupItem();
        unset($backup['payload']);

        $this->push(['backups' => [$backup]])->assertJsonPath('rejected.0.code', 'payload_required');

        $this->push(['backups' => [$this->backupItem(['payload' => ['format' => 'other']])]])
            ->assertJsonPath('rejected.0.code', 'validation_failed');
    }

    #[Test]
    public function only_deleted_at_changes_once_and_deleting_drops_the_payload(): void
    {
        $backup = $this->backupItem();
        $this->push(['backups' => [$backup]]);

        // Immutable fields are ignored.
        $this->push(['backups' => [array_merge($backup, ['reason' => 'restore', 'shots_count' => 99])]])->assertJsonPath('seq', 1);

        $this->push(['backups' => [array_merge($backup, ['deleted_at' => '2026-10-16T21:00:00.000Z'])]])->assertJsonPath('seq', 2);
        $this->push(['backups' => [array_merge($backup, ['deleted_at' => '2026-10-16T22:00:00.000Z'])]])->assertJsonPath('seq', 2);
        $this->push(['backups' => [array_merge($backup, ['deleted_at' => null])]])->assertJsonPath('seq', 2);

        $stored = Backup::find($backup['id']);
        $this->assertSame('clear', $stored->reason);
        $this->assertSame('2026-10-16T21:00:00.000Z', $stored->deleted_at->format('Y-m-d\TH:i:s.v\Z'));
        $this->assertNull($stored->payload);

        $this->getJson("/api/v1/backups/{$backup['id']}", $this->authHeaders($this->user))
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    #[Test]
    public function other_users_backups_are_not_found(): void
    {
        $backup = $this->backupItem();
        $this->push(['backups' => [$backup]]);

        $this->getJson("/api/v1/backups/{$backup['id']}", $this->authHeaders(User::factory()->create()))->assertNotFound();
        $this->getJson('/api/v1/backups/'.Str::uuid7(), $this->authHeaders($this->user))->assertNotFound();
    }

    #[Test]
    public function account_deletion_cascades_everything(): void
    {
        $category = $this->categoryItem();
        $this->push([
            'categories' => [$category],
            'shots' => [$this->shotItem(['category_id' => $category['id']])],
            'settings' => [$this->settingItem('dot_size', 14)],
            'backups' => [$this->backupItem()],
        ])->assertJsonPath('seq', 4);

        $this->deleteJson('/api/v1/me', ['password' => 'password'], $this->authHeaders($this->user))->assertNoContent();

        foreach (['shots', 'categories', 'user_settings', 'backups', 'devices', 'refresh_tokens', 'auth_codes'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
