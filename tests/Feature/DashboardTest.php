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
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentMark;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_executive_dashboard(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role_view', 'admin')
            ->has('stats.total_students')
            ->has('stats.total_teachers')
            ->has('stats.total_classes')
            ->has('stats.total_sections')
            ->has('recent_enrollments')
        );
    }

    public function test_student_can_view_personal_portal_dashboard(): void
    {
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $subject = Subject::firstOrFail();

        $user = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(UserRole::STUDENT->value);

        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'SMS/DASH/001',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $exam = Exam::firstOrCreate(
            ['academic_session_id' => $session->id, 'academic_term_id' => $term->id],
            ['name' => 'Dashboard Exam', 'max_ca1' => 10, 'max_ca2' => 10, 'max_ca3' => 10, 'max_exam' => 70]
        );

        StudentMark::create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 10,
            'ca2' => 10,
            'ca3' => 10,
            'exam_score' => 60,
        ]);

        DailyAttendance::create([
            'enrollment_id' => $enrollment->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::PRESENT,
            'recorded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role_view', 'student')
            ->where('student_profile.admission_number', 'SMS/DASH/001')
            ->where('total_score', 90)
            ->where('terminal_average', 90)
            ->where('attendance.days_present', 1)
            ->where('attendance.attendance_rate', 100)
            ->has('marks', 1)
        );
    }

    public function test_guardian_can_view_ward_academic_portal(): void
    {
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $guardianUser = User::factory()->create([
            'role' => UserRole::GUARDIAN,
            'email_verified_at' => now(),
        ]);
        $guardianUser->assignRole(UserRole::GUARDIAN->value);

        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'name' => 'Alhaji Umar Faruk',
            'relationship' => 'Father',
            'phone' => '08033334444',
        ]);

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $ward = Student::create([
            'user_id' => $studentUser->id,
            'guardian_id' => $guardian->id,
            'admission_number' => 'SMS/WARD/001',
            'admission_date' => now()->toDateString(),
        ]);
        Enrollment::create([
            'student_id' => $ward->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($guardianUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role_view', 'guardian')
            ->has('wards', 1)
            ->where('active_ward.name', $studentUser->name)
            ->where('active_ward.admission_number', 'SMS/WARD/001')
        );
    }
}
