<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_daily_attendance(): void
    {
        $this->get(route('academic.attendance.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_access_daily_attendance(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('academic.attendance.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_daily_attendance_index(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.attendance.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/attendance/Index')
            ->has('sessions')
            ->has('classes')
            ->has('selectedSessionId')
            ->has('selectedClassId')
            ->has('selectedSectionId')
            ->has('selectedDate')
            ->has('roster')
            ->has('metrics')
            ->has('attendanceStatuses')
        );
    }

    public function test_can_batch_record_daily_attendance_successfully(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        // Create 2 test students & enrollments
        $user1 = User::factory()->create(['role' => UserRole::STUDENT]);
        $student1 = Student::create([
            'user_id' => $user1->id,
            'admission_number' => 'SMS/ATT/001',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollment1 = Enrollment::create([
            'student_id' => $student1->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '1',
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $user2 = User::factory()->create(['role' => UserRole::STUDENT]);
        $student2 = Student::create([
            'user_id' => $user2->id,
            'admission_number' => 'SMS/ATT/002',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollment2 = Enrollment::create([
            'student_id' => $student2->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '2',
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $attendanceDate = now()->toDateString();

        $payload = [
            'attendance_date' => $attendanceDate,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'records' => [
                [
                    'enrollment_id' => $enrollment1->id,
                    'status' => AttendanceStatus::PRESENT->value,
                    'remark' => 'Early arrival',
                ],
                [
                    'enrollment_id' => $enrollment2->id,
                    'status' => AttendanceStatus::LATE->value,
                    'remark' => 'Traffic delay',
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->post(route('academic.attendance.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('daily_attendances', [
            'enrollment_id' => $enrollment1->id,
            'attendance_date' => $attendanceDate,
            'status' => 'present',
            'remark' => 'Early arrival',
            'recorded_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('daily_attendances', [
            'enrollment_id' => $enrollment2->id,
            'attendance_date' => $attendanceDate,
            'status' => 'late',
            'remark' => 'Traffic delay',
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_can_update_existing_daily_attendance_without_duplicates(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $user = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'SMS/ATT/003',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $attendanceDate = now()->toDateString();

        // Initial record: Absent
        DailyAttendance::create([
            'enrollment_id' => $enrollment->id,
            'attendance_date' => $attendanceDate,
            'status' => AttendanceStatus::ABSENT,
            'recorded_by' => $admin->id,
            'remark' => 'Sick',
        ]);

        // Resubmit as Excused
        $payload = [
            'attendance_date' => $attendanceDate,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'records' => [
                [
                    'enrollment_id' => $enrollment->id,
                    'status' => AttendanceStatus::EXCUSED->value,
                    'remark' => 'Medical certificate provided',
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->post(route('academic.attendance.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseCount('daily_attendances', 1);
        $this->assertDatabaseHas('daily_attendances', [
            'enrollment_id' => $enrollment->id,
            'attendance_date' => $attendanceDate,
            'status' => 'excused',
            'remark' => 'Medical certificate provided',
        ]);
    }

    public function test_attendance_validation_fails_on_invalid_data(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'attendance_date' => 'invalid-date',
            'class_id' => 999999,
            'section_id' => 999999,
            'records' => [
                [
                    'enrollment_id' => 999999,
                    'status' => 'unknown_status',
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->post(route('academic.attendance.store'), $payload);

        $response->assertSessionHasErrors(['attendance_date', 'class_id', 'section_id', 'records.0.enrollment_id', 'records.0.status']);
    }
}
