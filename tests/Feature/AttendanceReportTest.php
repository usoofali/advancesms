<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_attendance_report(): void
    {
        $this->get(route('academic.reports.attendance'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_access_attendance_report(): void
    {
        $unauthorized = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($unauthorized)
            ->get(route('academic.reports.attendance'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_school_wide_attendance_report(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.reports.attendance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/reports/AttendanceReport')
            ->has('sessions')
            ->has('classes')
            ->where('viewMode', 'school')
            ->where('selectedClassId', 'all')
            ->has('overallMetrics')
            ->has('classSummaries')
        );
    }

    public function test_authorized_user_can_view_class_specific_roster(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.reports.attendance', [
            'session_id' => $session->id,
            'class_id' => $class->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/reports/AttendanceReport')
            ->where('viewMode', 'class')
            ->where('selectedClassId', $class->id)
            ->has('overallMetrics')
            ->has('studentRoster')
        );
    }

    public function test_attendance_report_calculates_metrics_correctly(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'name' => 'Fatima Ibrahim',
            'gender' => 'female',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'GSSS/TEST/001',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => 'TEST-01',
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $testDate1 = now()->subDays(2)->toDateString();
        $testDate2 = now()->subDays(1)->toDateString();

        DailyAttendance::create([
            'enrollment_id' => $enrollment->id,
            'attendance_date' => $testDate1,
            'status' => AttendanceStatus::PRESENT,
            'recorded_by' => $admin->id,
        ]);

        DailyAttendance::create([
            'enrollment_id' => $enrollment->id,
            'attendance_date' => $testDate2,
            'status' => AttendanceStatus::LATE,
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('academic.reports.attendance', [
            'session_id' => $session->id,
            'term_id' => $term->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_from' => now()->subDays(5)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('viewMode', 'class')
            ->has('studentRoster', 1)
            ->where('studentRoster.0.present_count', 1)
            ->where('studentRoster.0.late_count', 1)
            ->where('studentRoster.0.absent_count', 0)
            ->where('studentRoster.0.attendance_rate', 100)
        );
    }

    public function test_attendance_report_csv_export_school_summary(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.reports.attendance', [
            'class_id' => 'all',
            'export' => 'csv',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('School Attendance Summary Report', $content);
    }

    public function test_attendance_report_csv_export_class_roster(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.reports.attendance', [
            'class_id' => $class->id,
            'export' => 'csv',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Student Attendance Roster', $content);
    }
}
