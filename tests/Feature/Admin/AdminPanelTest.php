<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Widgets\AccountStatsOverview;
use App\Filament\Widgets\ShotsPerDayChart;
use App\Filament\Widgets\SignupsPerWeekChart;
use App\Models\Shot;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSyncItems;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use BuildsSyncItems, InteractsWithApi, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
    }

    /**
     * A user with a note, a named category and a backup — none of which may appear in the panel.
     */
    private function userWithPrivateData(): User
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        $category = $this->categoryItem(['name' => 'SECRET-CATEGORY']);

        $this->postJson('/api/v1/sync/push', [
            'categories' => [$category],
            'shots' => [
                $this->shotItem(['note' => 'SECRET-NOTE', 'category_id' => $category['id'], 'shot_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z')]),
                $this->shotItem(['color' => 'black']),
                $this->shotItem(['deleted_at' => '2026-10-16T21:00:00.000Z']),
            ],
            'backups' => [[
                'id' => (string) Str::uuid7(),
                'reason' => 'clear',
                'shots_count' => 1,
                'created_at' => '2026-10-16T20:00:00.000Z',
                'deleted_at' => null,
                'payload' => ['format' => 'oodan-export', 'version' => 1, 'exported_at' => '2026-10-16T20:00:00.000Z', 'shots' => [], 'categories' => [], 'settings' => ['SECRET-PAYLOAD' => 1]],
            ]],
        ], $this->authHeaders($user))->assertJsonPath('rejected', []);

        return $user;
    }

    #[Test]
    public function only_admins_can_open_the_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin')->assertOk();
    }

    #[Test]
    public function pages_show_counts_but_never_notes_category_names_or_payloads(): void
    {
        $member = $this->userWithPrivateData();
        $this->actingAs($this->admin);

        foreach (['/admin', '/admin/users', "/admin/users/{$member->id}"] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('SECRET-NOTE')
                ->assertDontSee('SECRET-CATEGORY')
                ->assertDontSee('SECRET-PAYLOAD');
        }

        $list = Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$member, $this->admin])
            ->assertTableColumnStateSet('shots_count', 2, $member)
            ->assertTableColumnStateSet('white_shots_count', 1, $member)
            ->assertTableColumnStateSet('black_shots_count', 1, $member)
            ->assertTableColumnStateSet('devices_count', 1, $member);

        foreach ([$list, Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])] as $component) {
            $component->assertDontSee('SECRET-NOTE')->assertDontSee('SECRET-CATEGORY')->assertDontSee('SECRET-PAYLOAD');
        }
    }

    #[Test]
    public function dashboard_widgets_render(): void
    {
        $this->userWithPrivateData();
        $this->actingAs($this->admin);

        Livewire::test(AccountStatsOverview::class)->assertOk()->assertSee('Active (7 days)')->assertSee('50% : 50%');
        Livewire::test(ShotsPerDayChart::class)->assertOk();
        Livewire::test(SignupsPerWeekChart::class)->assertOk();
    }

    #[Test]
    public function admins_can_toggle_admin_and_delete_users_with_the_full_cascade(): void
    {
        $member = $this->userWithPrivateData();
        $this->actingAs($this->admin);

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('toggleAdmin')->table($member))
            ->assertHasNoActionErrors();
        $this->assertTrue($member->fresh()->is_admin);

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('toggleAdmin')->table($this->admin))
            ->assertActionHidden(TestAction::make(DeleteAction::getDefaultName())->table($this->admin));

        Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($member);
        $this->assertSame(0, Shot::count());
        $this->assertDatabaseCount('backups', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    #[Test]
    public function make_admin_promotes_an_existing_account_or_creates_one(): void
    {
        $member = User::factory()->create(['email' => 'member@example.com']);

        $this->artisan('app:make-admin', ['email' => 'Member@Example.com'])->assertSuccessful();
        $this->assertTrue($member->fresh()->is_admin);

        $this->artisan('app:make-admin', ['email' => 'new-admin@example.com', '--no-interaction' => true])
            ->expectsOutputToContain('Generated password:')
            ->assertSuccessful();
        $this->assertTrue(User::where('email', 'new-admin@example.com')->first()->is_admin);

        $this->artisan('app:make-admin', ['email' => 'not-an-email'])->assertFailed();
    }
}
