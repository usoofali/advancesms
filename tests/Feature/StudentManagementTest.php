<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_students_directory(): void
    {
        $this->get(route('people.students.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_view_students(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('people.students.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_students_directory(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('people.students.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/students/Index')
            ->has('students')
            ->has('classes')
            ->has('guardians')
            ->has('activeSession')
            ->has('sessions')
            ->has('nextAdmissionNumber')
            ->has('stats')
        );
    }

    public function test_can_register_student_with_auto_generated_admission_number(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $payload = [
            'first_name' => 'Amina',
            'last_name' => 'Suleiman',
            'gender' => Gender::FEMALE->value,
            'admission_date' => '2026-09-10',
            'class_id' => $class->id,
            'section_id' => $section->id,
        ];

        $response = $this->actingAs($admin)->post(route('people.students.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $student = Student::where('admission_number', 'YUM-SA/00001')->first();
        $this->assertNotNull($student);
        $this->assertSame('AMINA SULEIMAN', $student->user->name);
        $this->assertSame('yum-sa00001@sms.com', $student->user->email);
        $this->assertSame('yum-sa00001', $student->user->username);
        $this->assertTrue(Hash::check('12345678', $student->user->password));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => 'YUM-SA/00001',
            'status' => EnrollmentStatus::ACTIVE->value,
        ]);
    }

    public function test_can_register_student_with_auto_provisioned_user_account(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $guardian = Guardian::create([
            'name' => 'Malam Aliyu Shehu',
            'relationship' => 'Father',
            'phone' => '08031112233',
        ]);

        $payload = [
            'first_name' => 'Maryam',
            'last_name' => 'Aliyu',
            'gender' => Gender::FEMALE->value,
            'admission_number' => 'GSSS26-00001',
            'admission_date' => '2026-09-10',
            'date_of_birth' => '2012-05-15',
            'state_of_origin' => 'Zamfara',
            'lga' => 'Gusau',
            'guardian_id' => $guardian->id,
            'email' => 'maryam.aliyu@student.sms.ng',
            'password' => 'StudentPass123#',
        ];

        $response = $this->actingAs($admin)->post(route('people.students.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'admission_number' => 'GSSS26-00001',
            'guardian_id' => $guardian->id,
            'state_of_origin' => 'Zamfara',
            'lga' => 'Gusau',
        ]);

        $student = Student::where('admission_number', 'GSSS26-00001')->firstOrFail();
        $user = $student->user;

        $this->assertNotNull($user);
        $this->assertSame('MARYAM ALIYU', $user->name);
        $this->assertSame(Gender::FEMALE, $user->gender);
        $this->assertSame(UserRole::STUDENT, $user->role);
        $this->assertTrue(Hash::check('StudentPass123#', $user->password));
        $this->assertTrue($user->hasRole('student'));
    }

    public function test_can_register_student_with_initial_class_and_section_enrollment(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();

        $payload = [
            'first_name' => 'Usman',
            'last_name' => 'Balarabe',
            'gender' => Gender::MALE->value,
            'admission_number' => 'GSSS26-00002',
            'admission_date' => '2026-09-10',
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '05',
        ];

        $response = $this->actingAs($admin)->post(route('people.students.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $student = Student::where('admission_number', 'GSSS26-00002')->firstOrFail();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '05',
            'status' => EnrollmentStatus::ACTIVE->value,
        ]);
    }

    public function test_cannot_create_student_with_duplicate_admission_number(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload1 = [
            'first_name' => 'Bilal',
            'last_name' => 'Umar',
            'gender' => Gender::MALE->value,
            'admission_number' => 'GSSS26-00003',
            'admission_date' => '2026-09-10',
        ];

        $this->actingAs($admin)->post(route('people.students.store'), $payload1)->assertRedirect();

        $duplicatePayload = [
            'first_name' => 'Another',
            'last_name' => 'Student',
            'gender' => Gender::FEMALE->value,
            'admission_number' => 'GSSS26-00003', // Duplicate
            'admission_date' => '2026-09-10',
        ];

        $response = $this->actingAs($admin)->post(route('people.students.store'), $duplicatePayload);

        $response->assertSessionHasErrors(['admission_number']);
    }

    public function test_can_update_student_profile_and_linked_user(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $user = User::factory()->create([
            'name' => 'Initial Name',
            'first_name' => 'Initial',
            'last_name' => 'Name',
            'gender' => Gender::MALE,
            'role' => UserRole::STUDENT,
        ]);
        $user->assignRole(UserRole::STUDENT->value);

        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSSS26-00004',
            'admission_date' => '2026-09-01',
        ]);

        $updatePayload = [
            'first_name' => 'Suleiman',
            'last_name' => 'Mustapha',
            'gender' => Gender::MALE->value,
            'admission_number' => 'GSSS26-00004-EDITED',
            'admission_date' => '2026-09-05',
            'date_of_birth' => '2011-03-20',
            'state_of_origin' => 'Kano',
            'lga' => 'Nassarawa',
            'is_active' => false,
        ];

        $response = $this->actingAs($admin)->put(route('people.students.update', $student), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'admission_number' => 'GSSS26-00004-EDITED',
            'state_of_origin' => 'Kano',
            'lga' => 'Nassarawa',
        ]);

        $user->refresh();
        $this->assertSame('SULEIMAN MUSTAPHA', $user->name);
        $this->assertFalse((bool) $user->is_active);
    }

    public function test_cannot_delete_student_with_academic_records(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();

        $user = User::factory()->create([
            'role' => UserRole::STUDENT,
            'gender' => Gender::MALE,
        ]);
        $user->assignRole(UserRole::STUDENT->value);

        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSSS26-00005',
            'admission_date' => '2026-09-01',
        ]);

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        DailyAttendance::create([
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $session->id,
            'academic_term_id' => AcademicTerm::firstOrFail()->id,
            'section_id' => $section->id,
            'attendance_date' => '2026-10-01',
            'status' => AttendanceStatus::PRESENT,
        ]);

        $response = $this->actingAs($admin)->delete(route('people.students.destroy', $student));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_can_delete_student_without_academic_records(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $user = User::factory()->create([
            'role' => UserRole::STUDENT,
            'gender' => Gender::FEMALE,
        ]);
        $user->assignRole(UserRole::STUDENT->value);

        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSSS26-00006',
            'admission_date' => '2026-09-01',
        ]);

        $response = $this->actingAs($admin)->delete(route('people.students.destroy', $student));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_can_update_student_class_and_section_enrollment(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $class1 = SchoolClass::firstOrFail();
        $section1 = Section::where('class_id', $class1->id)->firstOrFail();

        $class2 = SchoolClass::where('id', '!=', $class1->id)->firstOrFail();
        $section2 = Section::where('class_id', $class2->id)->firstOrFail();

        $user = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSSS26-TRANSFER',
            'admission_date' => '2026-09-01',
        ]);

        $session = AcademicSession::where('is_current', true)->firstOrFail();
        Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class1->id,
            'section_id' => $section1->id,
            'roll_number' => 'GSSS26-TRANSFER',
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $updatePayload = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'gender' => Gender::MALE->value,
            'admission_number' => 'GSSS26-TRANSFER',
            'admission_date' => '2026-09-01',
            'class_id' => $class2->id,
            'section_id' => $section2->id,
        ];

        $response = $this->actingAs($admin)->put(route('people.students.update', $student), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class2->id,
            'section_id' => $section2->id,
        ]);
    }

    public function test_admission_number_sanitization_and_credential_generation(): void
    {
        $this->assertSame('yum-sa001', Student::sanitizeAdmissionNumber('YUM-SA/001'));
        $this->assertSame('yum-sa001@sms.com', Student::generateStudentEmail('YUM-SA/001'));
        $this->assertSame('yum-sa001', Student::generateStudentUsername('YUM-SA/001'));

        $this->assertSame('gsss0001', Student::sanitizeAdmissionNumber('GSSS/0001'));
        $this->assertSame('gsss0001@sms.com', Student::generateStudentEmail('GSSS/0001'));
        $this->assertSame('gsss0001', Student::generateStudentUsername('GSSS/0001'));
    }

    public function test_admin_can_reset_student_password_to_default(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $user = User::factory()->create([
            'role' => UserRole::STUDENT,
            'password' => Hash::make('OldCustomPassword123'),
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSSS26-RESET01',
            'admission_date' => '2026-09-01',
        ]);

        $updatePayload = [
            'first_name' => 'Reset',
            'last_name' => 'Student',
            'gender' => Gender::MALE->value,
            'admission_number' => 'GSSS26-RESET01',
            'admission_date' => '2026-09-01',
            'password' => '12345678',
        ];

        $response = $this->actingAs($admin)->put(route('people.students.update', $student), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('12345678', $student->user->fresh()->password));
    }

    public function test_authorized_user_can_view_student_show_profile(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $user = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSSS26-VIEW01',
            'admission_date' => '2026-09-01',
        ]);

        $response = $this->actingAs($admin)->get(route('people.students.show', $student));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/students/Show')
            ->has('student')
            ->has('marks')
            ->has('attendances')
            ->has('attendanceStats')
            ->where('student.id', $student->id)
            ->where('student.admission_number', 'GSSS26-VIEW01')
        );
    }

    public function test_generate_next_admission_number_uses_five_digit_zero_padded_sequence(): void
    {
        $next = Student::generateNextAdmissionNumber('TEST-SCH');
        $this->assertMatchesRegularExpression('/^TEST-SCH\/\d{5}$/', $next);
        $this->assertSame('TEST-SCH/00001', $next);
    }
}
