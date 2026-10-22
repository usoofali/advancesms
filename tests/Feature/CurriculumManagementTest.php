<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentMark;
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
        $session = AcademicSession::where('is_current', true)->first() ?? AcademicSession::first();
        $term = AcademicTerm::where('is_current', true)->first() ?? AcademicTerm::first();

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

    public function test_cannot_remove_class_subject_when_marks_exist_in_current_active_session(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $subject = Subject::where('code', 'ENG')->firstOrFail();
        $section = $class->sections()->firstOrFail();

        $activeSession = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $activeSession->id)->where('is_current', true)->firstOrFail();

        $classSubject = ClassSubject::where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        $exam = Exam::firstOrCreate(
            ['academic_session_id' => $activeSession->id, 'academic_term_id' => $term->id],
            ['name' => '1st Term Exam', 'max_exam' => 70, 'max_ca1' => 10, 'max_ca2' => 10, 'max_ca3' => 10]
        );

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'CURR/TEST/001',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $activeSession->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        StudentMark::create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 10,
            'ca2' => 10,
            'ca3' => 10,
            'exam_score' => 50,
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.class-subjects.destroy', $classSubject));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('class_subjects', ['id' => $classSubject->id]);
    }

    public function test_can_remove_class_subject_when_marks_only_exist_in_previous_session(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $subject = Subject::where('code', 'BSC')->firstOrFail();
        $section = $class->sections()->firstOrFail();

        // Create a past academic session (not current)
        $pastSession = AcademicSession::create([
            'name' => '2023/2024 Session',
            'start_date' => '2023-09-01',
            'end_date' => '2024-07-31',
            'is_current' => false,
        ]);
        $pastTerm = AcademicTerm::create([
            'academic_session_id' => $pastSession->id,
            'name' => 'Third Term',
            'term_number' => 3,
            'start_date' => '2024-05-01',
            'end_date' => '2024-07-25',
            'is_current' => false,
        ]);
        $pastExam = Exam::create([
            'academic_session_id' => $pastSession->id,
            'academic_term_id' => $pastTerm->id,
            'name' => '3rd Term Exam',
            'max_exam' => 70,
            'max_ca1' => 10,
            'max_ca2' => 10,
            'max_ca3' => 10,
        ]);

        // Student with marks in the past session
        $pastStudentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $pastStudent = Student::create([
            'user_id' => $pastStudentUser->id,
            'admission_number' => 'PAST/TEST/001',
            'admission_date' => '2023-09-01',
        ]);
        $pastEnrollment = Enrollment::create([
            'student_id' => $pastStudent->id,
            'academic_session_id' => $pastSession->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::GRADUATED,
        ]);

        $pastMark = StudentMark::create([
            'enrollment_id' => $pastEnrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $pastExam->id,
            'ca1' => 10,
            'ca2' => 10,
            'ca3' => 10,
            'exam_score' => 60,
        ]);

        $classSubject = ClassSubject::where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        // Deallocating the subject from the class in the new session should succeed
        $response = $this->actingAs($admin)->delete(route('academic.class-subjects.destroy', $classSubject));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('class_subjects', ['id' => $classSubject->id]);

        // Historical mark from past session is still preserved in the database
        $this->assertDatabaseHas('student_marks', ['id' => $pastMark->id]);
    }

    public function test_can_remove_class_subject_when_marks_only_exist_in_previous_term_of_same_session(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $subject = Subject::where('code', 'ENG')->firstOrFail();
        $section = $class->sections()->firstOrFail();

        $session = AcademicSession::where('is_current', true)->firstOrFail();

        // Ensure Term 1 exists (not current) and Term 2 exists (current)
        $term1 = AcademicTerm::where('academic_session_id', $session->id)->where('term_number', 1)->first()
            ?? AcademicTerm::create([
                'academic_session_id' => $session->id,
                'name' => 'First Term',
                'term_number' => 1,
                'start_date' => '2025-09-01',
                'end_date' => '2025-12-15',
                'is_current' => false,
            ]);
        $term1->update(['is_current' => false]);

        $term2 = AcademicTerm::where('academic_session_id', $session->id)->where('term_number', 2)->first()
            ?? AcademicTerm::create([
                'academic_session_id' => $session->id,
                'name' => 'Second Term',
                'term_number' => 2,
                'start_date' => '2026-01-10',
                'end_date' => '2026-04-10',
                'is_current' => true,
            ]);
        $term2->update(['is_current' => true]);

        // Term 1 exam with student marks
        $exam1 = Exam::firstOrCreate(
            [
                'academic_session_id' => $session->id,
                'academic_term_id' => $term1->id,
            ],
            [
                'name' => 'First Term Exam',
                'max_exam' => 60,
                'max_ca1' => 20,
                'max_ca2' => 20,
            ]
        );

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'CURR/TEST/001',
            'admission_date' => '2025-09-01',
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $term1Mark = StudentMark::create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam1->id,
            'ca1' => 18,
            'ca2' => 17,
            'exam_score' => 50,
        ]);

        $classSubject = ClassSubject::where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        // Deallocate the subject from the class in Term 2 (marks only exist in Term 1)
        $response = $this->actingAs($admin)->delete(route('academic.class-subjects.destroy', $classSubject));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('class_subjects', ['id' => $classSubject->id]);

        // Term 1 marks are strictly preserved
        $this->assertDatabaseHas('student_marks', ['id' => $term1Mark->id]);

        // Term 1 broadsheet still shows the subject
        $broadsheetTerm1 = $this->actingAs($admin)->get(route('academic.broadsheet.index', [
            'session_id' => $session->id,
            'term_id' => $term1->id,
            'class_id' => $class->id,
        ]));
        $broadsheetTerm1->assertOk();
        $broadsheetTerm1->assertInertia(function ($page) use ($subject) {
            $subjects = collect($page->toArray()['props']['subjects']);
            $this->assertTrue($subjects->contains('id', $subject->id));
        });

        // Term 2 broadsheet does NOT show the dropped subject (no ghost column)
        $exam2 = Exam::firstOrCreate(
            [
                'academic_session_id' => $session->id,
                'academic_term_id' => $term2->id,
            ],
            [
                'name' => 'Second Term Exam',
                'max_exam' => 60,
                'max_ca1' => 20,
                'max_ca2' => 20,
            ]
        );
        $broadsheetTerm2 = $this->actingAs($admin)->get(route('academic.broadsheet.index', [
            'session_id' => $session->id,
            'term_id' => $term2->id,
            'class_id' => $class->id,
        ]));
        $broadsheetTerm2->assertOk();
        $broadsheetTerm2->assertInertia(function ($page) use ($subject) {
            $subjects = collect($page->toArray()['props']['subjects']);
            $this->assertFalse($subjects->contains('id', $subject->id));
        });
    }
}
