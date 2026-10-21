<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentMark;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentMarkEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_marks_entry(): void
    {
        $this->get(route('academic.marks.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_access_marks(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('academic.marks.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_marks_index_and_auto_provisions_exam(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.marks.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/marks/Index')
            ->has('sessions')
            ->has('classes')
            ->has('subjects')
            ->has('exam')
            ->has('gridRows')
            ->has('grades')
        );

        $this->assertDatabaseHas('exams', [
            'max_ca1' => 10.00,
            'max_ca2' => 10.00,
            'max_ca3' => 10.00,
            'max_exam' => 70.00,
        ]);
    }

    public function test_marks_index_scopes_subjects_strictly_to_selected_class_allocation(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $jss2 = SchoolClass::where('name', 'JSS 2')->firstOrFail();
        $allocatedSubjectIds = $jss2->subjects()->pluck('subjects.id')->all();

        $this->assertCount(10, $allocatedSubjectIds);
        $this->assertGreaterThan(10, Subject::count());

        $response = $this->actingAs($admin)->get(route('academic.marks.index', [
            'class_id' => $jss2->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/marks/Index')
            ->where('selectedClassId', $jss2->id)
            ->has('subjects', 10)
            ->where('subjects', function ($subjects) use ($allocatedSubjectIds) {
                $returnedIds = collect($subjects)->pluck('id')->all();
                sort($returnedIds);
                sort($allocatedSubjectIds);

                return $returnedIds === $allocatedSubjectIds;
            })
        );
    }

    public function test_can_batch_save_student_marks_with_auto_grade_resolution(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();

        $exam = Exam::create([
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'name' => 'First Term Exam Test',
            'max_ca1' => 10.00,
            'max_ca2' => 10.00,
            'max_ca3' => 10.00,
            'max_exam' => 70.00,
        ]);

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-M001',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $payload = [
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'marks' => [
                [
                    'enrollment_id' => $enrollment->id,
                    'ca1' => 9.5,
                    'ca2' => 8.0,
                    'ca3' => 9.0,
                    'exam_score' => 60.0,
                    'teacher_comment' => 'Outstanding academic performance criteria.',
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('academic.marks.batch-save'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('student_marks', [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 9.5,
            'ca2' => 8.0,
            'ca3' => 9.0,
            'exam_score' => 60.0,
            'total_score' => 86.5,
            'teacher_comment' => 'Outstanding academic performance criteria.',
        ]);

        $savedMark = StudentMark::where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertNotNull($savedMark->grade_id);

        $grade = Grade::find($savedMark->grade_id);
        $this->assertNotNull($grade);
        $this->assertTrue((float) $grade->mark_from <= 86.5 && (float) $grade->mark_to >= 86.5);
    }

    public function test_cannot_save_marks_exceeding_score_limits(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();

        $exam = Exam::create([
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'name' => 'First Term Exam Limit Test',
            'max_ca1' => 10.00,
            'max_ca2' => 10.00,
            'max_ca3' => 10.00,
            'max_exam' => 70.00,
        ]);

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-M002',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $invalidPayload = [
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'marks' => [
                [
                    'enrollment_id' => $enrollment->id,
                    'ca1' => 25.0, // Exceeds max_ca1 of 10
                    'ca2' => 8.0,
                    'ca3' => 9.0,
                    'exam_score' => 60.0,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('academic.marks.batch-save'), $invalidPayload);

        $response->assertSessionHasErrors(['marks.0.ca1']);
    }

    public function test_batch_save_works_with_2_cas_and_ca3_is_null_and_total_score_is_accurate(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();

        $exam = Exam::create([
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'name' => 'Dual CA Term Exam',
            'ca_count' => 2,
            'max_ca1' => 15.00,
            'max_ca2' => 15.00,
            'max_ca3' => 0.00,
            'max_exam' => 70.00,
            'ca1_label' => '1st Continuous Assessment',
            'ca2_label' => '2nd Continuous Assessment',
        ]);

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-M003',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $payload = [
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'marks' => [
                [
                    'enrollment_id' => $enrollment->id,
                    'ca1' => 14.0,
                    'ca2' => 13.0,
                    'exam_score' => 60.0,
                    'teacher_comment' => 'Great performance across both CAs.',
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('academic.marks.batch-save'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('student_marks', [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 14.0,
            'ca2' => 13.0,
            'ca3' => null,
            'exam_score' => 60.0,
            'total_score' => 87.0,
        ]);
    }

    public function test_batch_save_works_with_1_ca_and_ca2_ca3_are_null_and_total_score_is_accurate(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();

        $exam = Exam::create([
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'name' => 'Single CA Term Exam',
            'ca_count' => 1,
            'max_ca1' => 30.00,
            'max_ca2' => 0.00,
            'max_ca3' => 0.00,
            'max_exam' => 70.00,
            'ca1_label' => 'Midterm Assessment',
        ]);

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-M004',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $payload = [
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'marks' => [
                [
                    'enrollment_id' => $enrollment->id,
                    'ca1' => 28.0,
                    'exam_score' => 62.0,
                    'teacher_comment' => 'Excellent midterm assessment & exam score.',
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('academic.marks.batch-save'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('student_marks', [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 28.0,
            'ca2' => null,
            'ca3' => null,
            'exam_score' => 62.0,
            'total_score' => 90.0,
        ]);
    }

    public function test_marks_index_auto_provisions_exam_with_school_settings_ca_configuration(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        // Update school setting to 2 CAs policy
        $setting = SchoolSetting::current();
        $setting->update([
            'ca_count' => 2,
            'default_max_ca1' => 20.00,
            'default_max_ca2' => 20.00,
            'default_max_ca3' => 0.00,
            'default_max_exam' => 60.00,
            'ca1_label' => 'Test 1',
            'ca2_label' => 'Test 2',
        ]);

        $newSession = AcademicSession::create([
            'name' => '2027/2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-07-20',
            'is_current' => false,
        ]);

        $newTerm = AcademicTerm::create([
            'academic_session_id' => $newSession->id,
            'name' => 'First Term',
            'term_number' => 1,
            'start_date' => '2027-09-01',
            'end_date' => '2027-12-15',
            'is_current' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('academic.marks.index', [
            'session_id' => $newSession->id,
            'term_id' => $newTerm->id,
        ]));

        $response->assertOk();

        $this->assertDatabaseHas('exams', [
            'academic_session_id' => $newSession->id,
            'academic_term_id' => $newTerm->id,
            'ca_count' => 2,
            'max_ca1' => 20.00,
            'max_ca2' => 20.00,
            'max_ca3' => 0.00,
            'max_exam' => 60.00,
            'ca1_label' => 'Test 1',
            'ca2_label' => 'Test 2',
        ]);
    }

    public function test_teacher_only_sees_allocated_classes_sections_and_subjects_in_marks_index(): void
    {
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();

        $teacherUser = User::factory()->create([
            'role' => UserRole::TEACHER,
            'email_verified_at' => now(),
        ]);
        $teacherUser->assignRole(UserRole::TEACHER->value);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'staff_id' => 'TCH-TEST-01',
            'designation' => 'Mathematics Teacher',
        ]);

        $jss1 = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $sectionA = Section::where('class_id', $jss1->id)->firstOrFail();
        $mathSubject = Subject::where('name', 'Mathematics')->firstOrFail();

        // Allocate Teacher to JSS 1, Section A, Mathematics ONLY
        TeacherSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'class_id' => $jss1->id,
            'section_id' => $sectionA->id,
            'subject_id' => $mathSubject->id,
        ]);

        $response = $this->actingAs($teacherUser)->get(route('academic.marks.index', [
            'session_id' => $session->id,
            'term_id' => $term->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/marks/Index')
            ->has('classes', 1)
            ->where('classes.0.id', $jss1->id)
            ->has('sections', 1)
            ->where('sections.0.id', $sectionA->id)
            ->has('subjects', 1)
            ->where('subjects.0.id', $mathSubject->id)
            ->where('selectedSubjectId', $mathSubject->id)
        );
    }

    public function test_teacher_cannot_batch_save_marks_for_unallocated_subject(): void
    {
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();

        $teacherUser = User::factory()->create([
            'role' => UserRole::TEACHER,
            'email_verified_at' => now(),
        ]);
        $teacherUser->assignRole(UserRole::TEACHER->value);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'staff_id' => 'TCH-TEST-02',
            'designation' => 'Mathematics Teacher',
        ]);

        $jss1 = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $sectionA = Section::where('class_id', $jss1->id)->firstOrFail();
        $mathSubject = Subject::where('name', 'Mathematics')->firstOrFail();
        $englishSubject = Subject::where('name', 'English Language')->firstOrFail();

        // Allocate Teacher to JSS 1, Section A, Mathematics ONLY
        TeacherSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'class_id' => $jss1->id,
            'section_id' => $sectionA->id,
            'subject_id' => $mathSubject->id,
        ]);

        $exam = Exam::firstOrCreate([
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
        ], [
            'name' => 'First Term Exam Test',
            'max_ca1' => 10.00,
            'max_ca2' => 10.00,
            'max_ca3' => 10.00,
            'max_exam' => 70.00,
            'is_published' => false,
        ]);

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'ADM-TCH-001',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $jss1->id,
            'section_id' => $sectionA->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        // 1. Attempt to save marks for English Language (UNALLOCATED SUBJECT)
        $unauthorizedPayload = [
            'exam_id' => $exam->id,
            'subject_id' => $englishSubject->id,
            'marks' => [
                [
                    'enrollment_id' => $enrollment->id,
                    'ca1' => 8.0,
                    'exam_score' => 50.0,
                ],
            ],
        ];

        $unauthResponse = $this->actingAs($teacherUser)->post(
            route('academic.marks.batch-save'),
            $unauthorizedPayload
        );

        $unauthResponse->assertForbidden();
        $this->assertDatabaseMissing('student_marks', [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $englishSubject->id,
        ]);

        // 2. Save marks for Mathematics (ALLOCATED SUBJECT)
        $authorizedPayload = [
            'exam_id' => $exam->id,
            'subject_id' => $mathSubject->id,
            'marks' => [
                [
                    'enrollment_id' => $enrollment->id,
                    'ca1' => 9.5,
                    'exam_score' => 65.0,
                    'teacher_comment' => 'Great performance in mathematics.',
                ],
            ],
        ];

        $authResponse = $this->actingAs($teacherUser)->post(
            route('academic.marks.batch-save'),
            $authorizedPayload
        );

        $authResponse->assertRedirect();
        $authResponse->assertSessionHas('success');

        $this->assertDatabaseHas('student_marks', [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $mathSubject->id,
            'ca1' => 9.5,
            'exam_score' => 65.0,
        ]);
    }
}
