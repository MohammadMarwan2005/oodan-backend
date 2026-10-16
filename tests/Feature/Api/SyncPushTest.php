<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Shot;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSyncItems;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class SyncPushTest extends TestCase
{
    use BuildsSyncItems, InteractsWithApi, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function push(array $body, ?User $user = null): TestResponse
    {
        return $this->postJson('/api/v1/sync/push', $body, $this->authHeaders($user ?? $this->user));
    }

    #[Test]
    public function it_inserts_new_items_and_allocates_one_seq_per_change(): void
    {
        $shot = $this->shotItem();
        $category = $this->categoryItem();

        $this->push([
            'shots' => [$shot],
            'categories' => [$category],
            'settings' => [$this->settingItem('dot_size', 14)],
        ])
            ->assertOk()
            ->assertJsonPath('accepted.shots', [$shot['id']])
            ->assertJsonPath('accepted.categories', [$category['id']])
            ->assertJsonPath('accepted.settings', ['dot_size'])
            ->assertJsonPath('rejected', [])
            ->assertJsonPath('seq', 3);

        $this->assertSame(3, $this->user->fresh()->sync_seq);
        $this->assertSame(120.5, Shot::find($shot['id'])->x);
    }

    #[Test]
    public function pushing_the_same_items_twice_changes_nothing(): void
    {
        $body = ['shots' => [$this->shotItem()], 'categories' => [$this->categoryItem()], 'settings' => [$this->settingItem('intensity', 50)]];

        $this->push($body)->assertJsonPath('seq', 3);
        $this->push($body)
            ->assertOk()
            ->assertJsonPath('seq', 3)
            ->assertJsonCount(1, 'accepted.shots')
            ->assertJsonPath('rejected', []);

        $this->assertSame(1, Shot::count());
    }

    #[Test]
    public function shot_state_is_last_write_wins_on_state_changed_at(): void
    {
        $shot = $this->shotItem();
        $this->push(['shots' => [$shot]]);

        // Newer: applied.
        $this->push(['shots' => [array_merge($shot, ['undone_at' => '2026-10-16T21:00:00.000Z', 'state_changed_at' => '2026-10-16T21:00:00.000Z'])]])
            ->assertJsonPath('seq', 2);

        // Older: ignored but accepted.
        $this->push(['shots' => [array_merge($shot, ['deleted_at' => '2026-10-16T20:30:00.000Z', 'state_changed_at' => '2026-10-16T20:30:00.000Z'])]])
            ->assertJsonPath('seq', 2)->assertJsonPath('accepted.shots', [$shot['id']]);

        // Tie: the server value stays.
        $this->push(['shots' => [array_merge($shot, ['undone_at' => null, 'state_changed_at' => '2026-10-16T21:00:00.000Z'])]])
            ->assertJsonPath('seq', 2);

        $stored = Shot::find($shot['id']);
        $this->assertSame('2026-10-16T21:00:00.000Z', $stored->undone_at->format('Y-m-d\TH:i:s.v\Z'));
        $this->assertNull($stored->deleted_at);
    }

    #[Test]
    public function shot_meta_is_last_write_wins_on_meta_changed_at_independently_of_state(): void
    {
        $shot = $this->shotItem();
        $this->push(['shots' => [$shot]]);

        $this->push(['shots' => [array_merge($shot, [
            'note' => 'newer note',
            'meta_changed_at' => '2026-10-16T22:00:00.000Z',
            'undone_at' => '2026-10-16T19:00:00.000Z',
            'state_changed_at' => '2026-10-16T19:00:00.000Z',
        ])]])->assertJsonPath('seq', 2);

        $this->push(['shots' => [array_merge($shot, ['note' => 'older note', 'meta_changed_at' => '2026-10-16T21:00:00.000Z'])]])
            ->assertJsonPath('seq', 2);

        $stored = Shot::find($shot['id']);
        $this->assertSame('newer note', $stored->note);
        $this->assertNull($stored->undone_at);
    }

    #[Test]
    public function immutable_shot_fields_are_ignored_on_existing_shots(): void
    {
        $shot = $this->shotItem();
        $this->push(['shots' => [$shot]]);

        $this->push(['shots' => [array_merge($shot, ['x' => 1, 'color' => 'black', 'intensity' => 10, 'state_changed_at' => '2026-10-16T21:00:00.000Z'])]])
            ->assertJsonPath('rejected', []);

        $stored = Shot::find($shot['id']);
        $this->assertSame(120.5, $stored->x);
        $this->assertSame('white', $stored->color);
        $this->assertSame(80, $stored->intensity);
    }

    #[Test]
    public function categories_are_last_write_wins_and_kind_is_immutable(): void
    {
        $category = $this->categoryItem();
        $this->push(['categories' => [$category]]);

        $this->push(['categories' => [array_merge($category, ['name' => 'قديم', 'changed_at' => '2026-10-16T19:00:00.000Z'])]])
            ->assertJsonPath('seq', 1);
        $this->push(['categories' => [array_merge($category, ['name' => 'تعادل', 'changed_at' => '2026-10-16T20:00:00.000Z'])]])
            ->assertJsonPath('seq', 1);
        $this->push(['categories' => [array_merge($category, [
            'name' => 'جديد', 'kind' => 'black', 'deleted_at' => '2026-10-16T21:00:00.000Z', 'changed_at' => '2026-10-16T21:00:00.000Z',
        ])]])->assertJsonPath('seq', 2);

        $stored = Category::find($category['id']);
        $this->assertSame('جديد', $stored->name);
        $this->assertSame('white', $stored->kind);
        $this->assertNotNull($stored->deleted_at);
    }

    #[Test]
    public function settings_are_last_write_wins_per_key(): void
    {
        $this->push(['settings' => [$this->settingItem('sound_enabled', true)]]);
        $this->push(['settings' => [$this->settingItem('sound_enabled', false, '2026-10-16T19:00:00.000Z')]])->assertJsonPath('seq', 1);
        $this->push(['settings' => [$this->settingItem('sound_enabled', false, '2026-10-16T21:00:00.000Z')]])->assertJsonPath('seq', 2);

        $this->assertFalse(UserSetting::where('user_id', $this->user->id)->where('key', 'sound_enabled')->first()->value);
    }

    #[Test]
    public function settings_of_different_users_do_not_collide(): void
    {
        $other = User::factory()->create();

        $this->push(['settings' => [$this->settingItem('dot_size', 10)]]);
        $this->push(['settings' => [$this->settingItem('dot_size', 30)]], $other);
        $this->push(['settings' => [$this->settingItem('dot_size', 20, '2026-10-16T21:00:00.000Z')]]);

        $this->assertSame(20, UserSetting::where('user_id', $this->user->id)->where('key', 'dot_size')->first()->value);
        $this->assertSame(30, UserSetting::where('user_id', $other->id)->where('key', 'dot_size')->first()->value);
    }

    #[Test]
    public function a_shot_can_use_a_category_from_the_same_push(): void
    {
        $category = $this->categoryItem(['kind' => 'black']);
        $shot = $this->shotItem(['color' => 'black', 'category_id' => $category['id']]);

        $this->push(['shots' => [$shot], 'categories' => [$category]])
            ->assertJsonPath('rejected', [])
            ->assertJsonPath('seq', 2);
    }

    #[Test]
    public function invalid_items_are_rejected_one_by_one(): void
    {
        $foreignCategory = $this->categoryItem();
        $this->push(['categories' => [$foreignCategory]], User::factory()->create());

        $blackCategory = $this->categoryItem(['kind' => 'black']);
        $this->push(['categories' => [$blackCategory]]);

        $good = $this->shotItem();
        $cases = [
            'validation_failed' => $this->shotItem(['x' => 400.01]),
            'shot_in_future' => $this->shotItem(['shot_at' => now()->addHours(25)->utc()->format('Y-m-d\TH:i:s.v\Z')]),
            'category_not_found' => $this->shotItem(['category_id' => $foreignCategory['id']]),
            'category_kind_mismatch' => $this->shotItem(['category_id' => $blackCategory['id']]),
        ];

        $response = $this->push(['shots' => [$good, ...array_values($cases)]])
            ->assertOk()
            ->assertJsonPath('accepted.shots', [$good['id']])
            ->assertJsonPath('seq', 2);

        $rejected = collect($response->json('rejected'))->pluck('code', 'id');
        foreach ($cases as $code => $item) {
            $this->assertSame($code, $rejected[$item['id']]);
        }
        $this->assertArrayHasKey('x', $response->json('rejected.0.fields'));
    }

    #[Test]
    public function it_validates_notes_timestamps_and_settings(): void
    {
        $response = $this->push([
            'shots' => [
                $this->shotItem(['note' => str_repeat('ن', 501)]),
                $this->shotItem(['shot_at' => '16/10/2026']),
                $this->shotItem(['intensity' => 9]),
                ['color' => 'white'],
            ],
            'settings' => [
                $this->settingItem('theme', 'dark'),
                $this->settingItem('dot_size', 41),
                $this->settingItem('sound_enabled', 1),
            ],
        ])->assertOk()->assertJsonPath('seq', 0);

        $codes = collect($response->json('rejected'))->map(fn ($r) => "{$r['entity']}:{$r['code']}")->all();

        $this->assertSame([
            'shot:validation_failed', 'shot:validation_failed', 'shot:validation_failed', 'shot:validation_failed',
            'setting:unknown_setting', 'setting:validation_failed', 'setting:validation_failed',
        ], $codes);
        $this->assertSame('', $response->json('rejected.3.id'));
    }

    #[Test]
    public function a_note_of_exactly_500_characters_is_accepted(): void
    {
        $this->push(['shots' => [$this->shotItem(['note' => str_repeat('ن', 500)])]])->assertJsonPath('rejected', []);
    }

    #[Test]
    public function ids_owned_by_another_user_are_rejected(): void
    {
        $shot = $this->shotItem();
        $this->push(['shots' => [$shot]], User::factory()->create());

        $this->push(['shots' => [array_merge($shot, ['state_changed_at' => '2026-10-17T00:00:00.000Z', 'deleted_at' => '2026-10-17T00:00:00.000Z'])]])
            ->assertJsonPath('rejected.0.code', 'id_conflict');

        $this->assertNull(Shot::find($shot['id'])->deleted_at);
    }

    #[Test]
    public function timestamps_with_offsets_are_normalised_to_utc_milliseconds(): void
    {
        $shot = $this->shotItem(['shot_at' => '2026-10-16T23:00:00.123456+03:00']);
        $this->push(['shots' => [$shot]])->assertJsonPath('rejected', []);

        $this->getJson('/api/v1/sync/pull', $this->authHeaders($this->user))
            ->assertJsonPath('shots.0.shot_at', '2026-10-16T20:00:00.123Z');
    }

    #[Test]
    public function a_push_is_limited_to_500_items(): void
    {
        $shots = array_map(fn () => $this->shotItem(), range(1, 300));
        $categories = array_map(fn () => $this->categoryItem(), range(1, 201));

        $this->push(['shots' => $shots, 'categories' => $categories])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'too_many_items');

        $this->assertSame(0, Shot::count());
    }

    #[Test]
    public function seq_values_are_contiguous_per_user(): void
    {
        $other = User::factory()->create();
        $shots = array_map(fn () => $this->shotItem(), range(1, 5));

        $this->push(['shots' => $shots, 'categories' => [$this->categoryItem()]]);
        $this->push(['shots' => [$this->shotItem()]], $other);
        $this->push(['shots' => [array_merge($shots[0], ['undone_at' => '2026-10-16T21:00:00.000Z', 'state_changed_at' => '2026-10-16T21:00:00.000Z'])], 'settings' => [$this->settingItem('dot_size', 9)]]);
        $this->push(['shots' => $shots]);

        $seqs = collect()
            ->merge(Shot::where('user_id', $this->user->id)->pluck('seq'))
            ->merge(Category::where('user_id', $this->user->id)->pluck('seq'))
            ->merge(UserSetting::where('user_id', $this->user->id)->pluck('seq'))
            ->sort()->values()->all();

        // Categories are applied first (seq 1), then shots 2–6. shots[0] later moved from 2 to 7,
        // so 2 is no longer in use; every other value from 1 to 8 is used exactly once.
        $this->assertSame([1, 3, 4, 5, 6, 7, 8], $seqs);
        $this->assertSame(8, $this->user->fresh()->sync_seq);
        $this->assertSame(1, $other->fresh()->sync_seq);
    }

    #[Test]
    public function malformed_arrays_fail_the_whole_request(): void
    {
        $this->push(['shots' => 'nope'])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    }
}
