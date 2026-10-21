<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlumniManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_alumni_directory(): void
    {
        $this->get(route('people.alumni.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_view_alumni(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('people.alumni.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_alumni_directory(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('people.alumni.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/alumni/Index')
            ->has('alumni')
            ->has('sessions')
            ->has('classes')
            ->has('stats')
        );
    }

    public function test_alumni_directory_only_lists_graduated_students(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $schoolClass = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $schoolClass->id)->firstOrFail();

        // 1. Create an active student
        $activeUser = User::factory()->create([
            'name' => 'Active Student',
            'role' => UserRole::STUDENT,
            'gender' => Gender::MALE,
        ]);
        $activeStudent = Student::create([
            'user_id' => $activeUser->id,
            'admission_number' => 'ACTIVE-001',
            'admission_date' => now()->subYear()->toDateString(),
        ]);
        Enrollment::create([
            'student_id' => $activeStudent->id,
            'academic_session_id' => $session->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'roll_number' => '001',
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        // 2. Create a graduated alumnus
        $alumnusUser = User::factory()->create([
            'name' => 'Graduated Alumnus',
            'role' => UserRole::STUDENT,
            'gender' => Gender::FEMALE,
        ]);
        $alumnusStudent = Student::create([
            'user_id' => $alumnusUser->id,
            'admission_number' => 'ALUMNI-001',
            'admission_date' => now()->subYears(3)->toDateString(),
        ]);
        Enrollment::create([
            'student_id' => $alumnusStudent->id,
            'academic_session_id' => $session->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'roll_number' => '002',
            'status' => EnrollmentStatus::GRADUATED,
        ]);

        $response = $this->actingAs($admin)->get(route('people.alumni.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/alumni/Index')
            ->has('alumni', 1)
            ->where('alumni.0.admission_number', 'ALUMNI-001')
            ->where('stats.total_alumni', 1)
            ->where('stats.female_alumni', 1)
            ->where('stats.male_alumni', 0)
        );
    }

    public function test_alumni_directory_can_be_filtered_by_session_and_class(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session1 = AcademicSession::where('is_current', true)->firstOrFail();
        $session2 = AcademicSession::create([
            'name' => '2024/2025',
            'start_date' => '2024-09-01',
            'end_date' => '2025-07-31',
            'is_current' => false,
        ]);

        $classes = SchoolClass::orderBy('level_order')->get();
        $class1 = $classes->first();
        $class2 = $classes->last();

        $section1 = Section::where('class_id', $class1->id)->firstOrFail();
        $section2 = Section::where('class_id', $class2->id)->firstOrFail();

        // Alumnus 1
        $user1 = User::factory()->create(['name' => 'Alumnus Session One', 'gender' => Gender::MALE]);
        $student1 = Student::create([
            'user_id' => $user1->id,
            'admission_number' => 'ALUMNI-SESSION-1',
            'admission_date' => now()->toDateString(),
        ]);
        Enrollment::create([
            'student_id' => $student1->id,
            'academic_session_id' => $session1->id,
            'class_id' => $class1->id,
            'section_id' => $section1->id,
            'status' => EnrollmentStatus::GRADUATED,
        ]);

        // Alumnus 2
        $user2 = User::factory()->create(['name' => 'Alumnus Session Two', 'gender' => Gender::FEMALE]);
        $student2 = Student::create([
            'user_id' => $user2->id,
            'admission_number' => 'ALUMNI-SESSION-2',
            'admission_date' => now()->toDateString(),
        ]);
        Enrollment::create([
            'student_id' => $student2->id,
            'academic_session_id' => $session2->id,
            'class_id' => $class2->id,
            'section_id' => $section2->id,
            'status' => EnrollmentStatus::GRADUATED,
        ]);

        // Filter by Session 1
        $response = $this->actingAs($admin)->get(route('people.alumni.index', ['session_id' => $session1->id]));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('alumni', 1)
            ->where('alumni.0.admission_number', 'ALUMNI-SESSION-1')
        );

        // Filter by Class 2
        $responseClass = $this->actingAs($admin)->get(route('people.alumni.index', ['class_id' => $class2->id]));
        $responseClass->assertOk();
        $responseClass->assertInertia(fn ($page) => $page
            ->has('alumni', 1)
            ->where('alumni.0.admission_number', 'ALUMNI-SESSION-2')
        );
    }

    public function test_alumni_directory_can_export_csv(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $schoolClass = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $schoolClass->id)->firstOrFail();

        $user = User::factory()->create([
            'name' => 'Graduated Alumnus CSV',
            'role' => UserRole::STUDENT,
            'gender' => Gender::MALE,
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'ALUMNI-CSV-001',
            'admission_date' => now()->subYears(2)->toDateString(),
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::GRADUATED,
        ]);

        $response = $this->actingAs($admin)->get(route('people.alumni.index', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('alumni_directory_', (string) $response->headers->get('content-disposition'));

        // Capture streamed content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('ALUMNI-CSV-001', (string) $content);
        $this->assertStringContainsString('Graduated Alumnus CSV', (string) $content);
        $this->assertStringContainsString($schoolClass->name, (string) $content);
    }
}
