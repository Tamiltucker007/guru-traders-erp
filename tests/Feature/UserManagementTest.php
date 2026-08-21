<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionsSeeder::class, RolesSeeder::class]);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['status' => true])->assignRole($role);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_open_every_user_management_screen(): void
    {
        $admin = $this->userWithRole('Super Admin');
        $role  = Role::firstWhere('name', 'Packing');

        $this->actingAs($admin)->get(route('user-management.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('user-management.users.create'))->assertOk();
        $this->actingAs($admin)->get(route('user-management.users.show', $admin))->assertOk();
        $this->actingAs($admin)->get(route('user-management.users.edit', $admin))->assertOk();

        $this->actingAs($admin)->get(route('user-management.roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('user-management.roles.create'))->assertOk();
        $this->actingAs($admin)->get(route('user-management.roles.show', $role))->assertOk();
        $this->actingAs($admin)->get(route('user-management.roles.edit', $role))->assertOk();

        $this->actingAs($admin)->get(route('user-management.permissions.index'))->assertOk();
    }

    public function test_super_admin_sidebar_links_user_management_to_users_index(): void
    {
        $admin = $this->userWithRole('Super Admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('user-management.users.index', absolute: false), false)
            ->assertSee(route('user-management.roles.index', absolute: false), false)
            ->assertSee(route('user-management.permissions.index', absolute: false), false)
            ->assertDontSee('User Management<i class="nav-arrow', false);
    }

    public function test_dashboard_renders_for_every_seeded_role(): void
    {
        foreach (config('permissions.roles') as $roleName => $config) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    */

    public function test_role_without_user_view_permission_is_denied(): void
    {
        $packer = $this->userWithRole('Packing');

        $this->actingAs($packer)
            ->get(route('user-management.users.index'))
            ->assertForbidden();
    }

    public function test_jobworker_cannot_reach_role_management(): void
    {
        $jobworker = $this->userWithRole('Jobworker');

        $this->actingAs($jobworker)->get(route('user-management.roles.index'))->assertForbidden();
        $this->actingAs($jobworker)->get(route('user-management.permissions.index'))->assertForbidden();
    }

    public function test_super_admin_bypasses_permission_checks(): void
    {
        $admin = $this->userWithRole('Super Admin');

        // Never declared in config, so it exists nowhere — the gate still allows it.
        $this->assertTrue($admin->can('some.permission.that.does.not.exist'));
    }

    /*
    |--------------------------------------------------------------------------
    | Guard rails
    |--------------------------------------------------------------------------
    */

    public function test_system_role_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('Super Admin');
        $role  = Role::firstWhere('name', 'Accounts');

        $this->actingAs($admin)
            ->delete(route('user-management.roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => 'Accounts']);
    }

    public function test_system_role_cannot_be_renamed(): void
    {
        $admin = $this->userWithRole('Super Admin');
        $role  = Role::firstWhere('name', 'Packing');

        $this->actingAs($admin)
            ->put(route('user-management.roles.update', $role), [
                'name'        => 'Renamed Packing',
                'permissions' => ['packing.view'],
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('roles', ['name' => 'Packing']);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('Super Admin');

        $custom = Role::create(['name' => 'Temp Role', 'guard_name' => 'web']);
        $this->userWithRole('Temp Role');

        $this->actingAs($admin)
            ->delete(route('user-management.roles.destroy', $custom))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => 'Temp Role']);
    }

    public function test_unused_custom_role_can_be_deleted(): void
    {
        $admin  = $this->userWithRole('Super Admin');
        $custom = Role::create(['name' => 'Temp Role', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->delete(route('user-management.roles.destroy', $custom))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['name' => 'Temp Role']);
    }

    public function test_protected_super_admin_account_cannot_be_deleted(): void
    {
        $this->seed(\Database\Seeders\SuperAdminSeeder::class);

        $seeded = User::firstWhere('email', config('permissions.super_admin.email'));
        $other  = $this->userWithRole('Super Admin');

        $this->actingAs($other)
            ->delete(route('user-management.users.destroy', $seeded))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $seeded->id]);
    }

    public function test_user_cannot_delete_their_own_account(): void
    {
        $admin = $this->userWithRole('Super Admin');

        $this->actingAs($admin)
            ->delete(route('user-management.users.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->userWithRole('Super Admin');

        $this->actingAs($admin)
            ->put(route('user-management.users.update', $admin), [
                'name'   => $admin->name,
                'email'  => $admin->email,
                'roles'  => ['Super Admin'],
                'status' => 0,
            ])
            ->assertSessionHasErrors('status');
    }

    /*
    |--------------------------------------------------------------------------
    | Permission integrity
    |--------------------------------------------------------------------------
    */

    public function test_a_role_cannot_be_given_a_permission_that_is_not_registered(): void
    {
        $admin = $this->userWithRole('Super Admin');

        $this->actingAs($admin)
            ->post(route('user-management.roles.store'), [
                'name'        => 'Invented Role',
                'permissions' => ['prodcut.view'], // typo — no such permission
            ])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'Invented Role']);
    }

    public function test_every_declared_permission_exists_in_the_database(): void
    {
        $registry = app(\App\Support\PermissionRegistry::class);
        $existing = \Spatie\Permission\Models\Permission::pluck('name')->all();

        $this->assertSame([], $registry->diff($existing)['missing']);
    }

    public function test_user_creation_assigns_roles_and_records_the_creator(): void
    {
        $admin = $this->userWithRole('Super Admin');

        $this->actingAs($admin)
            ->post(route('user-management.users.store'), [
                'name'                  => 'Ravi Kumar',
                'email'                 => 'ravi@example.com',
                'phone'                 => '9876543210',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'roles'                 => ['Packing'],
                'status'                => 1,
            ])
            ->assertRedirect(route('user-management.users.index'));

        $created = User::firstWhere('email', 'ravi@example.com');

        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('Packing'));
        $this->assertSame($admin->id, $created->created_by);
        $this->assertTrue($created->status);
    }
}
