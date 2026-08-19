<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.index'))->assertRedirect(route('login'));
        $this->post(route('admin.migrate'))->assertRedirect(route('login'));
        $this->post(route('admin.cache'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_owner_dashboard(): void
    {
        $older = User::factory()->create([
            'name' => 'Técnico anterior',
            'email' => 'older@example.com',
            'created_at' => now()->subDay(),
        ]);
        $newer = User::factory()->create([
            'name' => 'Técnico reciente',
            'email' => 'newer@example.com',
            'created_at' => now(),
        ]);

        $this->actingAs($newer)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Index')
                ->has('users', 2)
                ->where('users.0.email', $newer->email)
                ->where('users.1.email', $older->email)
                ->where('users.0.is_admin', false),
            );
    }

    public function test_running_migrations_promotes_the_authenticated_user_to_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->from(route('admin.index'))
            ->post(route('admin.migrate'))
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_admin' => true,
        ]);
    }

    public function test_authenticated_users_can_clear_the_application_cache(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('admin.index'))
            ->post(route('admin.cache'))
            ->assertRedirect(route('admin.index'));
    }

    public function test_admin_middleware_forbids_non_admins(): void
    {
        $this->registerAdminGuardRoute();

        $this->actingAs(User::factory()->create())
            ->get('/__admin-guard')
            ->assertForbidden();
    }

    public function test_admin_middleware_allows_admins(): void
    {
        $this->registerAdminGuardRoute();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/__admin-guard')
            ->assertOk();
    }

    private function registerAdminGuardRoute(): void
    {
        Route::middleware(['web', 'auth', 'admin'])
            ->get('/__admin-guard', fn () => response('ok'));
    }
}
