<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guest_cannot_access_roles_page(): void
    {
        $response = $this->get(route('admin.roles.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_roles_view_permission_is_forbidden(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
        ]);
        $student->assignRole(UserRole::STUDENT->value);

        $response = $this->actingAs($student)->get(route('admin.roles.index'));
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_roles_page_and_permissions(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.roles.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('roles/Index')
            ->has('roles')
            ->has('groupedPermissions')
            ->has('stats')
        );
    }

    public function test_authorized_user_can_create_custom_role_with_permissions(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'discipline_master',
            'display_name' => 'Discipline Master',
            'description' => 'Enforces school discipline and reviews daily attendance logs.',
            'permissions' => [
                'daily_attendances.view',
                'daily_attendances.take',
                'students.view',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $role = Role::where('name', 'discipline_master')->first();
        $this->assertNotNull($role);
        $this->assertSame('Discipline Master', $role->display_name);
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->hasPermissionTo('daily_attendances.view'));
        $this->assertTrue($role->hasPermissionTo('students.view'));
        $this->assertFalse($role->hasPermissionTo('classes.delete'));
    }

    public function test_authorized_user_can_update_role_and_sync_permissions(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $role = Role::create([
            'name' => 'head_of_sports',
            'display_name' => 'Sports Master',
            'description' => 'Coordinates athletic activities.',
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'display_name' => 'Director of Sports & Health',
            'description' => 'Coordinates athletic competitions and health records.',
            'permissions' => [
                'students.view',
                'daily_attendances.view',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $role->refresh();

        $this->assertSame('Director of Sports & Health', $role->display_name);
        $this->assertTrue($role->hasPermissionTo('students.view'));
        $this->assertTrue($role->hasPermissionTo('daily_attendances.view'));
    }

    public function test_system_default_roles_cannot_be_deleted(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $systemRole = Role::where('name', UserRole::TEACHER->value)->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $systemRole));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => UserRole::TEACHER->value]);
    }

    public function test_custom_role_can_be_deleted_when_no_active_users(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $role = Role::create([
            'name' => 'temporary_staff',
            'display_name' => 'Temporary Staff',
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $role));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['name' => 'temporary_staff']);
    }

    public function test_role_creation_requires_unique_name(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => UserRole::ADMIN->value, // already exists
            'display_name' => 'Duplicate Admin',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
