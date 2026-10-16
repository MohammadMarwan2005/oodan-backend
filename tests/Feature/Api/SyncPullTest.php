<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSyncItems;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class SyncPullTest extends TestCase
{
    use BuildsSyncItems, InteractsWithApi, RefreshDatabase;

    #[Test]
    public function it_pages_through_every_change_in_seq_order(): void
    {
        $user = User::factory()->create();
        $shots = array_map(fn () => $this->shotItem(), range(1, 4));

        $this->postJson('/api/v1/sync/push', [
            'shots' => $shots,
            'categories' => [$this->categoryItem(), $this->categoryItem()],
            'settings' => [$this->settingItem('dot_size', 12)],
        ], $this->authHeaders($user))->assertJsonPath('seq', 7);

        // Another user's data never shows up.
        $this->postJson('/api/v1/sync/push', ['shots' => [$this->shotItem()]], $this->authHeaders(User::factory()->create()));

        $since = 0;
        $seen = [];
        $pages = 0;

        do {
            $page = $this->getJson("/api/v1/sync/pull?since={$since}&limit=3", $this->authHeaders($user))->assertOk()->json();

            foreach (['shots', 'categories', 'settings'] as $key) {
                foreach ($page[$key] as $row) {
                    $this->assertGreaterThan($since, $row['seq']);
                    $seen[] = $row['seq'];
                }
            }

            $since = $page['next_since'];
            $pages++;
        } while ($page['has_more']);

        sort($seen);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $seen);
        $this->assertSame(3, $pages);
        $this->assertSame(7, $since);

        $this->assertSame(7, Device::find($this->deviceId)->last_pulled_seq);
    }

    #[Test]
    public function it_returns_updated_rows_again_and_an_empty_page_at_the_end(): void
    {
        $user = User::factory()->create();
        $shot = $this->shotItem();

        $this->postJson('/api/v1/sync/push', ['shots' => [$shot]], $this->authHeaders($user));
        $this->postJson('/api/v1/sync/push', ['shots' => [array_merge($shot, [
            'deleted_at' => '2026-10-16T21:00:00.000Z', 'state_changed_at' => '2026-10-16T21:00:00.000Z',
        ])]], $this->authHeaders($user));

        $this->getJson('/api/v1/sync/pull?since=1', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('shots.0.id', $shot['id'])
            ->assertJsonPath('shots.0.deleted_at', '2026-10-16T21:00:00.000Z')
            ->assertJsonPath('shots.0.seq', 2)
            ->assertJsonPath('next_since', 2)
            ->assertJsonPath('has_more', false);

        $this->getJson('/api/v1/sync/pull?since=2', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('shots', [])
            ->assertJsonPath('next_since', 2)
            ->assertJsonPath('has_more', false);
    }

    #[Test]
    public function rows_above_the_committed_sync_seq_are_left_for_the_next_pull(): void
    {
        $user = User::factory()->create();
        $shot = $this->shotItem();
        $this->postJson('/api/v1/sync/push', ['shots' => [$shot, $this->shotItem()]], $this->authHeaders($user));

        // Simulates a push that wrote a row but has not committed its sync_seq yet.
        User::whereKey($user->id)->update(['sync_seq' => 1]);

        $this->getJson('/api/v1/sync/pull', $this->authHeaders($user))
            ->assertJsonCount(1, 'shots')
            ->assertJsonPath('next_since', 1)
            ->assertJsonPath('has_more', false);
    }

    #[Test]
    public function it_validates_the_query(): void
    {
        $headers = $this->authHeaders(User::factory()->create());

        $this->getJson('/api/v1/sync/pull?limit=501', $headers)->assertUnprocessable();
        $this->getJson('/api/v1/sync/pull?since=-1', $headers)->assertUnprocessable();
    }
}
