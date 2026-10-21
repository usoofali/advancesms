<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSubjectAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_allocations(): void
    {
        $this->get(route('academic.allocations.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_view_allocations(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('academic.allocations.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_allocations_index(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.allocations.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/allocations/Index')
            ->has('assignments')
            ->has('sessions')
            ->has('classes')
            ->has('teachers')
            ->has('subjects')
            ->has('classSubjects')
            ->has('stats')
        );
    }

    private function createTeacher(string $staffId = 'STAFF/TEST/001'): Teacher
    {
        $user = User::factory()->create([
            'role' => UserRole::TEACHER,
            'name' => 'Malam Aminu Teacher',
        ]);
        $user->assignRole(UserRole::TEACHER->value);

        return Teacher::create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
        ]);
    }

    public function test_can_create_teacher_subject_assignment(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();
        $teacher = $this->createTeacher('STAFF/TEST/001');

        $payload = [
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'can_input_marks' => true,
            'can_take_attendance' => true,
        ];

        $response = $this->actingAs($admin)->post(route('academic.allocations.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teacher_subject_assignments', [
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'can_input_marks' => true,
            'can_take_attendance' => true,
        ]);
    }

    public function test_cannot_assign_duplicate_subject_to_same_section_in_same_term(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();
        $teacher1 = $this->createTeacher('STAFF/TEST/002');
        $teacher2 = $this->createTeacher('STAFF/TEST/003');

        TeacherSubjectAssignment::create([
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'teacher_id' => $teacher1->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'can_input_marks' => true,
        ]);

        $duplicatePayload = [
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'teacher_id' => $teacher2->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'can_input_marks' => true,
        ];

        $response = $this->actingAs($admin)->post(route('academic.allocations.store'), $duplicatePayload);

        $response->assertSessionHasErrors(['subject_id']);
    }

    public function test_can_update_teacher_subject_assignment(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();
        $teacher1 = $this->createTeacher('STAFF/TEST/004');
        $teacher2 = $this->createTeacher('STAFF/TEST/005');

        $assignment = TeacherSubjectAssignment::create([
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'teacher_id' => $teacher1->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'can_input_marks' => true,
            'can_take_attendance' => false,
        ]);

        $updatePayload = [
            'teacher_id' => $teacher2->id,
            'can_input_marks' => false,
            'can_take_attendance' => true,
        ];

        $response = $this->actingAs($admin)->put(route('academic.allocations.update', $assignment), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teacher_subject_assignments', [
            'id' => $assignment->id,
            'teacher_id' => $teacher2->id,
            'can_input_marks' => false,
            'can_take_attendance' => true,
        ]);
    }

    public function test_can_delete_teacher_subject_assignment(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();
        $teacher = $this->createTeacher('STAFF/TEST/006');

        $assignment = TeacherSubjectAssignment::create([
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.allocations.destroy', $assignment));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('teacher_subject_assignments', ['id' => $assignment->id]);
    }
}
