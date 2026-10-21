<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentMark;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadsheetReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_broadsheet(): void
    {
        $this->get(route('academic.broadsheet.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_access_broadsheet(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('academic.broadsheet.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_broadsheet_index(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.broadsheet.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/reports/Broadsheet')
            ->has('sessions')
            ->has('classes')
            ->has('selectedSessionId')
            ->has('selectedTermId')
            ->has('selectedClassId')
            ->has('selectedSectionId')
            ->has('subjects')
            ->has('matrixRows')
            ->has('subjectStats')
            ->has('classMetrics')
            ->has('school')
        );
    }

    public function test_broadsheet_accurately_compiles_matrix_scores_and_rankings(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();

        // Fetch or create exam milestone
        $exam = Exam::firstOrCreate(
            [
                'academic_session_id' => $session->id,
                'academic_term_id' => $term->id,
            ],
            [
                'name' => 'First Term Exam Test',
                'max_ca1' => 10,
                'max_ca2' => 10,
                'max_ca3' => 10,
                'max_exam' => 70,
            ]
        );

        // Student A - Score 90 (1st rank)
        $userA = User::factory()->create(['role' => UserRole::STUDENT]);
        $studentA = Student::create([
            'user_id' => $userA->id,
            'admission_number' => 'SMS/BS/001',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollmentA = Enrollment::create([
            'student_id' => $studentA->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);
        StudentMark::create([
            'enrollment_id' => $enrollmentA->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 10,
            'ca2' => 10,
            'ca3' => 10,
            'exam_score' => 60,
        ]);

        // Student B - Score 60 (2nd rank)
        $userB = User::factory()->create(['role' => UserRole::STUDENT]);
        $studentB = Student::create([
            'user_id' => $userB->id,
            'admission_number' => 'SMS/BS/002',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollmentB = Enrollment::create([
            'student_id' => $studentB->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);
        StudentMark::create([
            'enrollment_id' => $enrollmentB->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 5,
            'ca2' => 5,
            'ca3' => 5,
            'exam_score' => 45,
        ]);

        $response = $this->actingAs($admin)->get(route('academic.broadsheet.index', [
            'session_id' => $session->id,
            'term_id' => $term->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/reports/Broadsheet')
            ->where('classMetrics.total_students', 2)
            ->where('classMetrics.highest_total', 90)
            ->where('classMetrics.lowest_total', 60)
            ->has('matrixRows', 2)
            ->where('matrixRows.0.student_name', $userA->name)
            ->where('matrixRows.0.class_rank', 1)
            ->where('matrixRows.1.student_name', $userB->name)
            ->where('matrixRows.1.class_rank', 2)
        );
    }

    public function test_broadsheet_csv_export_returns_streamed_file(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.broadsheet.index', [
            'session_id' => $session->id,
            'term_id' => $term->id,
            'class_id' => $class->id,
            'export' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Class Rank', $content);
        $this->assertStringContainsString('Admission No', $content);
    }
}
