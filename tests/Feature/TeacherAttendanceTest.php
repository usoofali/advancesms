<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_teacher_attendance(): void
    {
        $this->get(route('people.teachers.attendance.index'))->assertRedirect(route('login'));
        $this->get(route('people.teachers.attendance.report'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_access_teacher_attendance(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('people.teachers.attendance.index'))
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->get(route('people.teachers.attendance.report'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_teacher_attendance_register(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('people.teachers.attendance.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/teachers/attendance/Index')
            ->has('sessions')
            ->has('roster')
            ->has('metrics')
            ->has('selectedDate')
            ->has('isWeekend')
        );
    }

    public function test_weekend_dates_cannot_be_saved(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();

        $teacherUser = User::factory()->create(['role' => UserRole::TEACHER]);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'staff_id' => 'STF-TEST-001',
            'designation' => 'Senior Tutor',
        ]);

        // A Saturday date: 2026-10-24
        $saturday = '2026-10-24';

        $response = $this->actingAs($admin)->post(route('people.teachers.attendance.store'), [
            'attendance_date' => $saturday,
            'academic_session_id' => $session->id,
            'records' => [
                [
                    'teacher_id' => $teacher->id,
                    'status' => 'present',
                    'remark' => 'Weekend work',
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['attendance_date']);
        $this->assertDatabaseMissing('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => $saturday,
        ]);
    }

    public function test_authorized_user_can_batch_save_teacher_attendance(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();

        $teacherUser1 = User::factory()->create(['role' => UserRole::TEACHER, 'name' => 'Adamu Garba']);
        $teacher1 = Teacher::create([
            'user_id' => $teacherUser1->id,
            'staff_id' => 'STF-001',
            'designation' => 'Math Teacher',
        ]);

        $teacherUser2 = User::factory()->create(['role' => UserRole::TEACHER, 'name' => 'Bilkisu Sani']);
        $teacher2 = Teacher::create([
            'user_id' => $teacherUser2->id,
            'staff_id' => 'STF-002',
            'designation' => 'English Teacher',
        ]);

        // Monday: 2026-10-19
        $weekday = '2026-10-19';

        $response = $this->actingAs($admin)->post(route('people.teachers.attendance.store'), [
            'attendance_date' => $weekday,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'records' => [
                [
                    'teacher_id' => $teacher1->id,
                    'status' => 'present',
                    'remark' => 'Early arrival',
                ],
                [
                    'teacher_id' => $teacher2->id,
                    'status' => 'late',
                    'remark' => 'Traffic delay',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher1->id,
            'attendance_date' => $weekday,
            'status' => AttendanceStatus::PRESENT->value,
            'remark' => 'Early arrival',
            'recorded_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher2->id,
            'attendance_date' => $weekday,
            'status' => AttendanceStatus::LATE->value,
            'remark' => 'Traffic delay',
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_authorized_user_can_view_performance_report(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();

        $teacherUser = User::factory()->create(['role' => UserRole::TEACHER]);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'staff_id' => 'STF-REP-01',
            'designation' => 'Physics Teacher',
        ]);

        TeacherAttendance::create([
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'attendance_date' => '2026-10-19',
            'status' => AttendanceStatus::PRESENT,
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('people.teachers.attendance.report', [
            'session_id' => $session->id,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/teachers/attendance/Report')
            ->has('overallMetrics')
            ->has('teacherSummaries')
            ->has('recordedDates')
        );
    }

    public function test_teacher_attendance_report_defaults_to_recorded_attendance_and_aggregates_stats(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $currentTerm = AcademicTerm::where('is_current', true)->firstOrFail();

        // Simulate term start date configured in the future
        $currentTerm->update([
            'start_date' => now()->addMonths(2),
            'end_date' => now()->addMonths(6),
        ]);

        $teacherUser = User::factory()->create(['role' => UserRole::TEACHER]);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'staff_id' => 'STF-AGGR-01',
            'designation' => 'Math Teacher',
        ]);

        TeacherAttendance::create([
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'term_id' => $currentTerm->id,
            'attendance_date' => '2026-10-15',
            'status' => AttendanceStatus::PRESENT,
            'recorded_by' => $admin->id,
        ]);

        TeacherAttendance::create([
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'term_id' => $currentTerm->id,
            'attendance_date' => '2026-10-16',
            'status' => AttendanceStatus::LATE,
            'recorded_by' => $admin->id,
        ]);

        TeacherAttendance::create([
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'term_id' => $currentTerm->id,
            'attendance_date' => '2026-10-19',
            'status' => AttendanceStatus::ABSENT,
            'recorded_by' => $admin->id,
        ]);

        // Default request without date parameters must capture the recorded attendance
        $response = $this->actingAs($admin)->get(route('people.teachers.attendance.report'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/teachers/attendance/Report')
            ->where('dateFrom', '2026-10-15')
            ->where('overallMetrics.total_records', fn ($val) => $val >= 3)
            ->where('overallMetrics.present_count', fn ($val) => $val >= 1)
            ->where('overallMetrics.late_count', fn ($val) => $val >= 1)
            ->where('overallMetrics.absent_count', fn ($val) => $val >= 1)
            ->where('teacherSummaries', function ($summaries) use ($teacher) {
                $item = collect($summaries)->firstWhere('teacher_id', $teacher->id);

                return $item !== null
                    && $item['total_days'] === 3
                    && $item['present_count'] === 1
                    && $item['late_count'] === 1
                    && $item['absent_count'] === 1
                    && $item['attendance_rate'] === 66.7;
            })
        );
    }

    public function test_performance_report_csv_export(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('people.teachers.attendance.report', [
            'export' => 'csv',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Teacher & Staff Attendance Performance Report', $content);
    }

    public function test_authorized_user_can_delete_daily_teacher_attendance(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();

        $teacherUser = User::factory()->create(['role' => UserRole::TEACHER]);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'staff_id' => 'STF-DEL-01',
            'designation' => 'Chemistry Teacher',
        ]);

        $targetDate = '2026-10-19';

        TeacherAttendance::create([
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'attendance_date' => $targetDate,
            'status' => AttendanceStatus::PRESENT,
            'recorded_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => $targetDate,
        ]);

        $response = $this->actingAs($admin)->delete(route('people.teachers.attendance.destroy', [
            'date' => $targetDate,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => $targetDate,
        ]);
    }
}
