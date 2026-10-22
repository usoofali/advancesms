<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_teachers_directory(): void
    {
        $this->get(route('people.teachers.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_view_teachers(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $student->assignRole(UserRole::STUDENT->value);

        $this->actingAs($student)
            ->get(route('people.teachers.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_teachers_directory(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('people.teachers.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/teachers/Index')
            ->has('teachers')
            ->has('departments')
            ->has('roles')
            ->has('stats')
        );
    }

    public function test_can_register_new_teacher_with_auto_provisioned_user_account(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::firstOrFail();

        $payload = [
            'first_name' => 'Fatima',
            'last_name' => 'Ibrahim',
            'email' => 'fatima.ibrahim@school.ng',
            'phone' => '08031234567',
            'gender' => Gender::FEMALE->value,
            'staff_id' => 'STAFF/2026/088',
            'department_id' => $dept->id,
            'designation' => 'Senior Subject Master',
            'qualification' => 'B.Sc. Ed Physics, M.Ed.',
            'joining_date' => '2026-09-01',
            'password' => 'StaffSecret123#',
        ];

        $response = $this->actingAs($admin)->post(route('people.teachers.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'staff_id' => 'STAFF/2026/088',
            'department_id' => $dept->id,
            'designation' => 'Senior Subject Master',
            'qualification' => 'B.Sc. Ed Physics, M.Ed.',
        ]);

        $teacher = Teacher::where('staff_id', 'STAFF/2026/088')->firstOrFail();
        $user = $teacher->user;

        $this->assertNotNull($user);
        $this->assertSame('FATIMA IBRAHIM', $user->name);
        $this->assertSame('fatima.ibrahim@school.ng', $user->email);
        $this->assertSame('08031234567', $user->phone);
        $this->assertSame(Gender::FEMALE, $user->gender);
        $this->assertSame(UserRole::TEACHER, $user->role);
        $this->assertTrue(Hash::check('StaffSecret123#', $user->password));
        $this->assertTrue($user->hasRole('teacher'));
    }

    public function test_cannot_create_teacher_with_duplicate_staff_id(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::firstOrFail();

        $payload = [
            'first_name' => 'Aminu',
            'last_name' => 'Bello',
            'email' => 'aminu1@school.ng',
            'gender' => Gender::MALE->value,
            'staff_id' => 'STAFF/2026/001',
            'department_id' => $dept->id,
        ];

        $this->actingAs($admin)->post(route('people.teachers.store'), $payload)->assertRedirect();

        $duplicatePayload = [
            'first_name' => 'Bello',
            'last_name' => 'Aminu',
            'email' => 'aminu2@school.ng',
            'gender' => Gender::MALE->value,
            'staff_id' => 'STAFF/2026/001', // Duplicate staff ID
            'department_id' => $dept->id,
        ];

        $response = $this->actingAs($admin)->post(route('people.teachers.store'), $duplicatePayload);

        $response->assertSessionHasErrors(['staff_id']);
    }

    public function test_can_update_teacher_and_linked_user_profile(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::firstOrFail();

        $user = User::factory()->create([
            'name' => 'Kabiru Bello',
            'first_name' => 'Kabiru',
            'last_name' => 'Bello',
            'role' => UserRole::TEACHER,
            'gender' => Gender::MALE,
            'email' => 'kabiru@school.ng',
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::TEACHER->value);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'staff_id' => 'STAFF/2026/050',
            'department_id' => $dept->id,
            'designation' => 'Assistant Teacher',
            'qualification' => 'NCE Mathematics',
            'joining_date' => '2026-01-10',
        ]);

        $updatePayload = [
            'first_name' => 'Kabiru',
            'last_name' => 'Bello-Danbatta',
            'email' => 'k.danbatta@school.ng',
            'phone' => '08099887766',
            'gender' => Gender::MALE->value,
            'staff_id' => 'STAFF/2026/050-EDITED',
            'department_id' => $dept->id,
            'designation' => 'Senior Mathematics Teacher',
            'qualification' => 'B.Sc. Ed, NCE',
            'is_active' => false,
        ];

        $response = $this->actingAs($admin)->put(route('people.teachers.update', $teacher), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'staff_id' => 'STAFF/2026/050-EDITED',
            'designation' => 'Senior Mathematics Teacher',
        ]);

        $user->refresh();
        $this->assertSame('KABIRU BELLO-DANBATTA', $user->name);
        $this->assertSame('k.danbatta@school.ng', $user->email);
        $this->assertFalse((bool) $user->is_active);
    }

    public function test_cannot_delete_teacher_with_active_subject_assignments(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();

        $user = User::factory()->create([
            'name' => 'Zainab Garba',
            'role' => UserRole::TEACHER,
            'gender' => Gender::FEMALE,
        ]);
        $user->assignRole(UserRole::TEACHER->value);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'staff_id' => 'STAFF/2026/077',
            'department_id' => $dept->id,
        ]);

        $session = AcademicSession::firstOrFail();
        $term = AcademicTerm::firstOrFail();

        TeacherSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'can_input_marks' => true,
            'can_take_attendance' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('people.teachers.destroy', $teacher));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_can_delete_unassigned_teacher_and_linked_user(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::firstOrFail();

        $user = User::factory()->create([
            'name' => 'Temporary Teacher',
            'role' => UserRole::TEACHER,
            'gender' => Gender::MALE,
        ]);
        $user->assignRole(UserRole::TEACHER->value);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'staff_id' => 'STAFF/TEMP/999',
            'department_id' => $dept->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('people.teachers.destroy', $teacher));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_can_register_new_staff_member_with_custom_role_and_stf_prefix(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::firstOrFail();

        $payload = [
            'role' => 'exam_officer',
            'first_name' => 'Usman',
            'last_name' => 'Danladi',
            'email' => 'usman.danladi@school.ng',
            'phone' => '08022334455',
            'gender' => Gender::MALE->value,
            'staff_id' => 'STF/2026/001',
            'department_id' => $dept->id,
            'designation' => 'Examination Officer',
            'qualification' => 'B.Sc. Computer Science',
            'joining_date' => '2026-09-01',
            'password' => 'ExamPass123#',
        ];

        $response = $this->actingAs($admin)->post(route('people.teachers.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'staff_id' => 'STF/2026/001',
            'department_id' => $dept->id,
            'designation' => 'Examination Officer',
        ]);

        $teacher = Teacher::where('staff_id', 'STF/2026/001')->firstOrFail();
        $user = $teacher->user;

        $this->assertNotNull($user);
        $this->assertSame('USMAN DANLADI', $user->name);
        $this->assertSame(UserRole::EXAM_OFFICER, $user->role);
        $this->assertTrue($user->hasRole('exam_officer'));
        $this->assertFalse($user->hasRole('teacher'));
    }

    public function test_can_update_staff_member_role(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::firstOrFail();

        $user = User::factory()->create([
            'name' => 'Halima Musa',
            'first_name' => 'Halima',
            'last_name' => 'Musa',
            'role' => UserRole::TEACHER,
            'gender' => Gender::FEMALE,
            'email' => 'halima@school.ng',
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::TEACHER->value);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'staff_id' => 'TCH/2026/015',
            'department_id' => $dept->id,
            'designation' => 'Subject Teacher',
        ]);

        $updatePayload = [
            'role' => 'admin',
            'first_name' => 'Halima',
            'last_name' => 'Musa',
            'email' => 'halima@school.ng',
            'gender' => Gender::FEMALE->value,
            'staff_id' => 'STF/2026/015',
            'department_id' => $dept->id,
            'designation' => 'Assistant School Administrator',
        ];

        $response = $this->actingAs($admin)->put(route('people.teachers.update', $teacher), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertSame(UserRole::ADMIN, $user->role);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('teacher'));
    }

    public function test_can_register_staff_with_custom_dynamic_spatie_role(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::firstOrFail();

        Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);

        $payload = [
            'role' => 'accountant',
            'first_name' => 'Ibrahim',
            'last_name' => 'Suleiman',
            'email' => 'ibrahim.bursar@school.ng',
            'phone' => '08099887711',
            'gender' => Gender::MALE->value,
            'staff_id' => 'STF/2026/099',
            'department_id' => $dept->id,
            'designation' => 'School Bursar / Accountant',
            'password' => 'BursarSecret123#',
        ];

        $response = $this->actingAs($admin)->post(route('people.teachers.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $teacher = Teacher::where('staff_id', 'STF/2026/099')->firstOrFail();
        $user = $teacher->user;

        $this->assertNotNull($user);
        $this->assertSame('accountant', $user->role);
        $this->assertTrue($user->hasRole('accountant'));
    }

    public function test_cannot_register_staff_with_forbidden_or_invalid_role(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $forbiddenStudentPayload = [
            'role' => 'student',
            'first_name' => 'Illegal',
            'last_name' => 'StudentStaff',
            'email' => 'illegal.student@school.ng',
            'gender' => Gender::MALE->value,
            'staff_id' => 'STF/2026/991',
        ];

        $response = $this->actingAs($admin)->post(route('people.teachers.store'), $forbiddenStudentPayload);
        $response->assertSessionHasErrors(['role']);

        $forbiddenGuardianPayload = [
            'role' => 'guardian',
            'first_name' => 'Illegal',
            'last_name' => 'GuardianStaff',
            'email' => 'illegal.guardian@school.ng',
            'gender' => Gender::MALE->value,
            'staff_id' => 'STF/2026/992',
        ];

        $response2 = $this->actingAs($admin)->post(route('people.teachers.store'), $forbiddenGuardianPayload);
        $response2->assertSessionHasErrors(['role']);
    }

    public function test_admin_can_directly_reset_teacher_password(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $user = User::factory()->create([
            'role' => UserRole::TEACHER,
            'password' => Hash::make('OldPassword123#'),
        ]);
        $user->assignRole(UserRole::TEACHER->value);
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'staff_id' => 'STAFF/2026/999',
            'designation' => 'Teacher',
        ]);

        $response = $this->actingAs($admin)->put(route('people.teachers.reset-password', $teacher), [
            'password' => 'BrandNewPassword123#',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('BrandNewPassword123#', $teacher->user->fresh()->password));
    }

    public function test_reset_password_requires_minimum_length(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $user = User::factory()->create(['role' => UserRole::TEACHER]);
        $user->assignRole(UserRole::TEACHER->value);
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'staff_id' => 'STAFF/2026/998',
            'designation' => 'Teacher',
        ]);

        $response = $this->actingAs($admin)->put(route('people.teachers.reset-password', $teacher), [
            'password' => '12345',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_user_model_accessors_extract_first_and_last_name_when_only_full_name_is_present(): void
    {
        $user = User::factory()->create([
            'name' => 'BELLO AHMADU GUSAU',
            'first_name' => null,
            'last_name' => null,
        ]);

        $this->assertSame('BELLO', $user->first_name);
        $this->assertSame('AHMADU GUSAU', $user->last_name);
    }
}
