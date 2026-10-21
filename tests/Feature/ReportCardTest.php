<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\DailyAttendance;
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

class ReportCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_report_cards_directory(): void
    {
        $this->get(route('academic.report-cards.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_access_report_cards(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        // Student role only has report_card.view if tailored, but let's check
        $guestLikeUser = User::factory()->create();

        $this->actingAs($guestLikeUser)
            ->get(route('academic.report-cards.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_report_cards_directory_with_pagination(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.report-cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/reports/ReportCardIndex')
            ->has('sessions')
            ->has('classes')
            ->has('selectedSessionId')
            ->has('selectedTermId')
            ->has('selectedClassId')
            ->has('selectedSectionId')
            ->has('students.data')
            ->has('students.per_page')
            ->has('students.total')
        );
    }

    public function test_authorized_user_can_view_individual_printable_report_card(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();

        $exam = Exam::firstOrCreate(
            [
                'academic_session_id' => $session->id,
                'academic_term_id' => $term->id,
            ],
            [
                'name' => 'Report Card Examination',
                'max_ca1' => 10,
                'max_ca2' => 10,
                'max_ca3' => 10,
                'max_exam' => 70,
            ]
        );

        $user = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'SMS/RC/001',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        // Create mark
        StudentMark::create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 8,
            'ca2' => 9,
            'ca3' => 8,
            'exam_score' => 55,
            'teacher_comment' => 'Great performance',
        ]);

        // Create attendance
        DailyAttendance::create([
            'enrollment_id' => $enrollment->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::PRESENT,
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('academic.report-cards.show', [
            'enrollment' => $enrollment->id,
            'term_id' => $term->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/reports/ReportCard')
            ->where('enrollment.id', $enrollment->id)
            ->where('enrollment.student.name', $user->name)
            ->where('summary.total_score_obtained', 80)
            ->where('summary.terminal_average', 80)
            ->where('attendance.days_present', 1)
            ->where('attendance.total_school_days', 1)
            ->where('attendance.attendance_rate', 100)
            ->has('school.name')
            ->has('grades')
            ->has('remarks.teacher')
            ->has('remarks.principal')
        );
    }
}
