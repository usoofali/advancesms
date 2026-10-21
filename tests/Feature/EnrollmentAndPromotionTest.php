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

class EnrollmentAndPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_enrollments(): void
    {
        $this->get(route('academic.enrollments.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_view_enrollments(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('academic.enrollments.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_enrollments_index(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.enrollments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/enrollments/Index')
            ->has('enrollments')
            ->has('sessions')
            ->has('classes')
            ->has('unenrolledStudents')
            ->has('stats')
        );
    }

    public function test_can_enroll_student_into_class_and_section(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'GSSS26-00999',
            'admission_date' => '2026-09-01',
        ]);

        $payload = [
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '10',
        ];

        $response = $this->actingAs($admin)->post(route('academic.enrollments.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '10',
            'status' => EnrollmentStatus::ACTIVE->value,
        ]);
    }

    public function test_cannot_enroll_student_twice_in_same_session(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'GSSS26-00998',
            'admission_date' => '2026-09-01',
        ]);

        Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $duplicatePayload = [
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
        ];

        $response = $this->actingAs($admin)->post(route('academic.enrollments.store'), $duplicatePayload);

        $response->assertSessionHasErrors(['student_id']);
    }

    public function test_can_update_enrollment_section_and_status(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $sections = Section::where('class_id', $class->id)->get();
        $sectionA = $sections[0];
        $sectionB = $sections[1] ?? $sections[0];

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'GSSS26-00997',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $sectionA->id,
            'roll_number' => '01',
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $updatePayload = [
            'class_id' => $class->id,
            'section_id' => $sectionB->id,
            'roll_number' => '25',
            'status' => EnrollmentStatus::WITHDRAWN->value,
        ];

        $response = $this->actingAs($admin)->put(route('academic.enrollments.update', $enrollment), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'section_id' => $sectionB->id,
            'roll_number' => '25',
            'status' => EnrollmentStatus::WITHDRAWN->value,
        ]);
    }

    public function test_cannot_delete_enrollment_with_attendance_or_marks(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'GSSS26-00996',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        DailyAttendance::create([
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'section_id' => $section->id,
            'attendance_date' => '2026-10-02',
            'status' => AttendanceStatus::PRESENT,
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.enrollments.destroy', $enrollment));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
    }

    public function test_can_delete_enrollment_without_records(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'admission_number' => 'GSSS26-00995',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.enrollments.destroy', $enrollment));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
    }

    public function test_management_initiated_batch_promotion_workflow(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $sourceSession = AcademicSession::where('is_current', true)->firstOrFail();
        $targetSession = AcademicSession::create([
            'name' => '2027/2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-07-31',
            'is_current' => false,
        ]);

        $classes = SchoolClass::all();
        $sourceClass = $classes[0]; // e.g. JSS 1
        $targetClass = $classes[1]; // e.g. JSS 2
        $sourceSection = Section::where('class_id', $sourceClass->id)->firstOrFail();
        $targetSection = Section::where('class_id', $targetClass->id)->firstOrFail();

        // Create 2 students in source session
        $student1 = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-P001',
            'admission_date' => '2026-09-01',
        ]);
        $student2 = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-P002',
            'admission_date' => '2026-09-01',
        ]);

        $sourceEnrollment1 = Enrollment::create([
            'academic_session_id' => $sourceSession->id,
            'student_id' => $student1->id,
            'class_id' => $sourceClass->id,
            'section_id' => $sourceSection->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $sourceEnrollment2 = Enrollment::create([
            'academic_session_id' => $sourceSession->id,
            'student_id' => $student2->id,
            'class_id' => $sourceClass->id,
            'section_id' => $sourceSection->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $promotionPayload = [
            'source_session_id' => $sourceSession->id,
            'target_session_id' => $targetSession->id,
            'action' => 'promote',
            'target_class_id' => $targetClass->id,
            'target_section_id' => $targetSection->id,
            'student_ids' => [$student1->id, $student2->id],
        ];

        $response = $this->actingAs($admin)->post(route('academic.enrollments.promote'), $promotionPayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check source enrollments are marked as PROMOTED
        $this->assertSame(EnrollmentStatus::PROMOTED, $sourceEnrollment1->fresh()->status);
        $this->assertSame(EnrollmentStatus::PROMOTED, $sourceEnrollment2->fresh()->status);

        // Check new active enrollments created in target session
        $this->assertDatabaseHas('enrollments', [
            'academic_session_id' => $targetSession->id,
            'student_id' => $student1->id,
            'class_id' => $targetClass->id,
            'section_id' => $targetSection->id,
            'status' => EnrollmentStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('enrollments', [
            'academic_session_id' => $targetSession->id,
            'student_id' => $student2->id,
            'class_id' => $targetClass->id,
            'section_id' => $targetSection->id,
            'status' => EnrollmentStatus::ACTIVE->value,
        ]);
    }

    public function test_management_initiated_batch_repetition_workflow(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $sourceSession = AcademicSession::where('is_current', true)->firstOrFail();
        $targetSession = AcademicSession::create([
            'name' => '2028/2029',
            'start_date' => '2028-09-01',
            'end_date' => '2029-07-31',
            'is_current' => false,
        ]);

        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-R001',
            'admission_date' => '2026-09-01',
        ]);

        $sourceEnrollment = Enrollment::create([
            'academic_session_id' => $sourceSession->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $repeatPayload = [
            'source_session_id' => $sourceSession->id,
            'target_session_id' => $targetSession->id,
            'action' => 'repeat',
            'target_class_id' => $class->id,
            'target_section_id' => $section->id,
            'student_ids' => [$student->id],
        ];

        $response = $this->actingAs($admin)->post(route('academic.enrollments.promote'), $repeatPayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(EnrollmentStatus::REPEATED, $sourceEnrollment->fresh()->status);

        $this->assertDatabaseHas('enrollments', [
            'academic_session_id' => $targetSession->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE->value,
        ]);
    }

    public function test_management_initiated_graduation_workflow(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $sourceSession = AcademicSession::where('is_current', true)->firstOrFail();
        $targetSession = AcademicSession::create([
            'name' => '2029/2030',
            'start_date' => '2029-09-01',
            'end_date' => '2030-07-31',
            'is_current' => false,
        ]);

        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-G001',
            'admission_date' => '2026-09-01',
        ]);

        $sourceEnrollment = Enrollment::create([
            'academic_session_id' => $sourceSession->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $graduatePayload = [
            'source_session_id' => $sourceSession->id,
            'target_session_id' => $targetSession->id,
            'action' => 'graduate',
            'student_ids' => [$student->id],
        ];

        $response = $this->actingAs($admin)->post(route('academic.enrollments.promote'), $graduatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(EnrollmentStatus::GRADUATED, $sourceEnrollment->fresh()->status);
        $this->assertDatabaseMissing('enrollments', [
            'academic_session_id' => $targetSession->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_guests_cannot_view_promotions(): void
    {
        $this->get(route('academic.promotions.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_promotions_index(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.promotions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/promotions/Index')
            ->has('sessions')
            ->has('classes')
            ->has('stats')
        );
    }

    public function test_authorized_user_can_promote_students_via_dedicated_promotions_route(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $sourceSession = AcademicSession::where('is_current', true)->firstOrFail();
        $targetSession = AcademicSession::create([
            'name' => '2030/2031',
            'start_date' => '2030-09-01',
            'end_date' => '2031-07-31',
            'is_current' => false,
        ]);

        $sourceClass = SchoolClass::firstOrFail();
        $sourceSection = Section::where('class_id', $sourceClass->id)->firstOrFail();

        $targetClass = SchoolClass::where('id', '!=', $sourceClass->id)->firstOrFail();
        $targetSection = Section::where('class_id', $targetClass->id)->firstOrFail();

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::STUDENT])->id,
            'admission_number' => 'GSSS26-DEDICATED',
            'admission_date' => '2026-09-01',
        ]);

        $sourceEnrollment = Enrollment::create([
            'academic_session_id' => $sourceSession->id,
            'student_id' => $student->id,
            'class_id' => $sourceClass->id,
            'section_id' => $sourceSection->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $payload = [
            'source_session_id' => $sourceSession->id,
            'target_session_id' => $targetSession->id,
            'action' => 'promote',
            'target_class_id' => $targetClass->id,
            'target_section_id' => $targetSection->id,
            'student_ids' => [$student->id],
        ];

        $response = $this->actingAs($admin)->post(route('academic.promotions.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(EnrollmentStatus::PROMOTED, $sourceEnrollment->fresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'academic_session_id' => $targetSession->id,
            'student_id' => $student->id,
            'class_id' => $targetClass->id,
            'section_id' => $targetSection->id,
            'status' => EnrollmentStatus::ACTIVE->value,
        ]);
    }
}
