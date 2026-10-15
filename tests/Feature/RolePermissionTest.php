<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_are_seeded_with_rich_metadata(): void
    {
        $this->seed(StandardAcademicSeeder::class);

        // Verify roles count and existence
        $this->assertGreaterThanOrEqual(6, Role::count());
        $this->assertTrue(Role::where('name', UserRole::SUPERADMIN->value)->exists());
        $this->assertTrue(Role::where('name', UserRole::ADMIN->value)->exists());
        $this->assertTrue(Role::where('name', UserRole::EXAM_OFFICER->value)->exists());
        $this->assertTrue(Role::where('name', UserRole::TEACHER->value)->exists());
        $this->assertTrue(Role::where('name', UserRole::STUDENT->value)->exists());
        $this->assertTrue(Role::where('name', UserRole::GUARDIAN->value)->exists());

        // Verify permissions count and model-based CRUD conventions
        $this->assertGreaterThanOrEqual(80, Permission::count());

        // Test sample model CRUD permissions
        $modelCrudPairs = [
            'classes' => ['view', 'create', 'update', 'delete'],
            'sections' => ['view', 'create', 'update', 'delete'],
            'subjects' => ['view', 'create', 'update', 'delete'],
            'teachers' => ['view', 'create', 'update', 'delete'],
            'students' => ['view', 'create', 'update', 'delete'],
            'exams' => ['view', 'create', 'update', 'delete'],
            'student_marks' => ['view', 'enter', 'update', 'delete'],
        ];

        foreach ($modelCrudPairs as $model => $actions) {
            foreach ($actions as $action) {
                $permissionName = "{$model}.{$action}";
                $permission = Permission::where('name', $permissionName)->first();

                $this->assertNotNull($permission, "Permission {$permissionName} should exist.");
                $this->assertNotEmpty($permission->display_name, "Permission {$permissionName} must have a display name.");
                $this->assertNotEmpty($permission->group, "Permission {$permissionName} must belong to a group.");
                $this->assertNotEmpty($permission->description, "Permission {$permissionName} must have a detailed description.");
            }
        }
    }

    public function test_superadmin_user_has_all_permissions_via_gate_before(): void
    {
        $this->seed(StandardAcademicSeeder::class);

        $superadmin = User::where('username', 'superadmin')->firstOrFail();

        $this->assertTrue($superadmin->isSuperAdmin());
        $this->assertTrue($superadmin->hasRole(UserRole::SUPERADMIN->value));
        $this->assertTrue($superadmin->can('classes.view'));
        $this->assertTrue($superadmin->can('classes.delete'));
        $this->assertTrue($superadmin->can('exams.publish'));
        $this->assertTrue($superadmin->can('any_arbitrary_ability'));
    }

    public function test_teacher_role_has_scoped_permissions(): void
    {
        $this->seed(StandardAcademicSeeder::class);

        $teacherUser = User::factory()->create([
            'role' => UserRole::TEACHER,
        ]);
        $teacherUser->assignRole(UserRole::TEACHER->value);

        $this->assertTrue($teacherUser->isTeacher());
        $this->assertTrue($teacherUser->can('student_marks.enter'));
        $this->assertTrue($teacherUser->can('daily_attendances.take'));
        $this->assertFalse($teacherUser->can('school_settings.update'));
        $this->assertFalse($teacherUser->can('roles.delete'));
    }

    public function test_custom_roles_can_be_dynamically_created_and_granted_permissions(): void
    {
        $this->seed(StandardAcademicSeeder::class);

        $role = Role::create([
            'name' => 'vice_principal',
            'display_name' => 'Vice Principal (Academics)',
            'description' => 'Oversees academic affairs and timetable routines.',
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $role->givePermissionTo([
            'classes.view',
            'teachers.view',
            'student_marks.view',
            'reports.broadsheet.view',
        ]);

        $vpUser = User::factory()->create();
        $vpUser->assignRole('vice_principal');

        $this->assertTrue($vpUser->hasRole('vice_principal'));
        $this->assertTrue($vpUser->can('classes.view'));
        $this->assertTrue($vpUser->can('reports.broadsheet.view'));
        $this->assertFalse($vpUser->can('school_settings.update'));
    }
}
