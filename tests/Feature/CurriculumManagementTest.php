<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_curriculum_or_subjects(): void
    {
        $this->get(route('academic.subjects.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_view_subjects(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $student->assignRole(UserRole::STUDENT->value);

        $this->actingAs($student)
            ->get(route('academic.subjects.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_subjects_index(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.subjects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/subjects/Index')
            ->has('subjects')
            ->has('departments')
            ->has('classes')
            ->has('stats')
        );
    }

    public function test_authorized_user_can_create_subject(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $dept = Department::first();

        $response = $this->actingAs($admin)->post(route('academic.subjects.store'), [
            'code' => 'frn',
            'name' => 'French Language',
            'department_id' => $dept?->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subjects', [
            'code' => 'FRN',
            'name' => 'French Language',
            'department_id' => $dept?->id,
        ]);
    }

    public function test_subject_code_and_name_must_be_unique(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('academic.subjects.store'), [
            'code' => 'MTH', // already seeded
            'name' => 'Mathematics', // already seeded
        ]);

        $response->assertSessionHasErrors(['code', 'name']);
    }

    public function test_authorized_user_can_update_subject(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $subject = Subject::where('code', 'MTH')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('academic.subjects.update', $subject), [
            'code' => 'MTH',
            'name' => 'General Mathematics',
            'department_id' => $subject->department_id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'General Mathematics',
        ]);
    }

    public function test_cannot_delete_subject_allocated_to_class_curriculum(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $subject = Subject::where('code', 'MTH')->firstOrFail();

        $this->assertGreaterThan(0, $subject->classes()->count());

        $response = $this->actingAs($admin)->delete(route('academic.subjects.destroy', $subject));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_can_delete_unallocated_subject(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $subject = Subject::create([
            'code' => 'MUS',
            'name' => 'Music Studies',
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.subjects.destroy', $subject));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    public function test_authorized_user_can_allocate_subject_to_class(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $newSubject = Subject::create([
            'code' => 'ART',
            'name' => 'Fine Art',
        ]);

        $response = $this->actingAs($admin)->post(route('academic.class-subjects.store'), [
            'class_id' => $class->id,
            'subject_id' => $newSubject->id,
            'is_compulsory' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $class->id,
            'subject_id' => $newSubject->id,
            'is_compulsory' => false,
        ]);
    }

    public function test_duplicate_class_subject_allocation_fails_validation(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $subject = Subject::where('code', 'MTH')->firstOrFail();

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($admin)->post(route('academic.class-subjects.store'), [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'is_compulsory' => true,
        ]);

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_authorized_user_can_update_compulsory_status(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $subject = Subject::where('code', 'MTH')->firstOrFail();

        $classSubject = ClassSubject::where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        $response = $this->actingAs($admin)->put(route('academic.class-subjects.update', $classSubject), [
            'is_compulsory' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_subjects', [
            'id' => $classSubject->id,
            'is_compulsory' => false,
        ]);
    }

    public function test_authorized_user_can_bulk_assign_subjects_to_class(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'SS 3')->firstOrFail();

        $sub1 = Subject::create(['code' => 'ARB', 'name' => 'Arabic Language']);
        $sub2 = Subject::create(['code' => 'TDR', 'name' => 'Technical Drawing']);

        $response = $this->actingAs($admin)->post(route('academic.class-subjects.bulk-assign'), [
            'class_id' => $class->id,
            'subject_ids' => [$sub1->id, $sub2->id],
            'is_compulsory' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $class->id,
            'subject_id' => $sub1->id,
            'is_compulsory' => true,
        ]);

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $class->id,
            'subject_id' => $sub2->id,
            'is_compulsory' => true,
        ]);
    }

    public function test_cannot_remove_class_subject_when_teachers_assigned(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $subject = Subject::where('code', 'MTH')->firstOrFail();

        $classSubject = ClassSubject::where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        // Create a teacher and an assignment
        $teacherUser = User::factory()->create([
            'role' => UserRole::TEACHER,
            'email_verified_at' => now(),
        ]);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'staff_id' => 'TCH/TEST/001',
            'qualification' => 'B.Sc Ed',
        ]);

        $section = $class->sections()->first();
        $session = AcademicSession::first();
        $term = AcademicTerm::first();

        TeacherSubjectAssignment::create([
            'academic_session_id' => $session?->id,
            'academic_term_id' => $term?->id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'section_id' => $section?->id,
            'subject_id' => $subject->id,
            'can_input_marks' => true,
            'can_take_attendance' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.class-subjects.destroy', $classSubject));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('class_subjects', ['id' => $classSubject->id]);
    }

    public function test_can_remove_class_subject_when_unencumbered(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $subject = Subject::where('code', 'CIV')->firstOrFail();

        $classSubject = ClassSubject::where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('academic.class-subjects.destroy', $classSubject));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('class_subjects', ['id' => $classSubject->id]);
    }
}
