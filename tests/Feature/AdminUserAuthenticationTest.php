<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_system_user_can_sign_in_with_their_username_and_password(): void
    {
        $this->withSession([
            'superadmin_authenticated' => true,
        ])->post(route('superadmin.users.store'), [
            'name' => 'Jane Admin',
            'username' => 'jane_admin',
            'email' => 'jane@example.com',
            'password' => 'secure-password',
            'role' => 'Desk Officer',
            'status' => 'active',
            'permissions' => ['Visitors'],
        ])->assertSessionHas('status', 'User account created successfully.');

        $user = User::where('username', 'jane_admin')->firstOrFail();
        $this->assertTrue(Hash::check('secure-password', $user->password));

        $this->flushSession();

        $this->post(route('admin.login.submit'), [
            'username' => 'jane_admin',
            'password' => 'secure-password',
        ])->assertRedirect(route('admin.visitors.index'))
            ->assertSessionHas('admin_authenticated', true)
            ->assertSessionHas('admin_user_id', $user->id)
            ->assertSessionHas('admin_permissions', ['Visitors']);
    }

    public function test_suspending_a_signed_in_system_user_revokes_access_immediately(): void
    {
        $user = User::factory()->create([
            'username' => 'active_user',
            'status' => 'suspended',
            'permissions' => ['Visitors'],
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_user_id' => $user->id,
            'admin_permissions' => ['Visitors'],
        ])->get(route('admin.visitors.index'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('authentication');
    }

    public function test_system_user_login_replaces_an_existing_superadmin_session(): void
    {
        $user = User::factory()->create([
            'username' => 'visitor_only',
            'password' => Hash::make('secure-password'),
            'status' => 'active',
            'permissions' => ['Visitors'],
        ]);

        $this->withSession([
            'superadmin_authenticated' => true,
            'superadmin_username' => 'superadmin',
            'admin_authenticated' => true,
        ])->post(route('admin.login.submit'), [
            'username' => 'visitor_only',
            'password' => 'secure-password',
        ])->assertRedirect(route('admin.visitors.index'))
            ->assertSessionHas('admin_user_id', $user->id)
            ->assertSessionMissing('superadmin_authenticated');
    }

    public function test_admin_login_page_is_available_to_a_signed_in_superadmin_for_account_switching(): void
    {
        $this->withSession([
            'superadmin_authenticated' => true,
            'admin_authenticated' => true,
        ])->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Username or Email');
    }

    public function test_superadmin_session_cannot_open_admin_visitor_pages(): void
    {
        $this->withSession([
            'superadmin_authenticated' => true,
        ])->get(route('admin.visitors.index'))
            ->assertRedirect(route('superadmin.dashboard'));
    }

    public function test_visitor_only_user_does_not_see_master_configurations_navigation(): void
    {
        $user = User::factory()->create([
            'username' => 'visitor_only',
            'status' => 'active',
            'permissions' => ['Visitors'],
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_user_id' => $user->id,
        ])->get(route('admin.visitors.index'))
            ->assertOk()
            ->assertSee('Visitors')
            ->assertDontSee('Master Configurations');
    }

    public function test_logout_always_returns_to_admin_login_and_clears_mixed_sessions(): void
    {
        $this->withSession([
            'admin_authenticated' => true,
            'superadmin_authenticated' => true,
            'admin_user_id' => 999,
        ])->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionMissing('admin_authenticated')
            ->assertSessionMissing('superadmin_authenticated');
    }

    public function test_dashboard_and_visitor_categories_permissions_render_only_those_sections(): void
    {
        $user = User::factory()->create([
            'username' => 'test_two',
            'role' => 'Desk Officer',
            'status' => 'active',
            'permissions' => ['Dashboard', 'Visitor Categories'],
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_user_id' => $user->id,
        ])->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Master Configurations')
            ->assertSee('Visitor Categories')
            ->assertDontSee('>Visitors<', false)
            ->assertDontSee('Event Configurations');

        $this->withSession([
            'admin_authenticated' => true,
            'admin_user_id' => $user->id,
        ])->get(route('admin.configurations.categories.index'))
            ->assertOk()
            ->assertSee('Visitor Categories')
            ->assertDontSee('Event Configurations');
    }

    public function test_superadmin_can_delete_the_final_system_user_account(): void
    {
        $user = User::factory()->create();

        $this->withSession([
            'superadmin_authenticated' => true,
        ])->delete(route('superadmin.users.destroy', $user))
            ->assertRedirect(route('superadmin.dashboard'))
            ->assertSessionHas('status', 'User account removed successfully.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
