<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
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
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_academic_seeder_populates_complete_academic_hierarchy(): void
    {
        $this->seed(StandardAcademicSeeder::class);

        // Verify School Settings
        $setting = SchoolSetting::current();
        $this->assertNotNull($setting);
        $this->assertSame('YUM Academy', $setting->school_name);

        // Verify Superadmin
        $admin = User::where('username', 'superadmin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isSuperAdmin());

        // Verify Sessions & Terms
        $currentSession = AcademicSession::current();
        $this->assertNotNull($currentSession);
        $this->assertSame('2025/2026', $currentSession->name);
        $this->assertCount(3, $currentSession->terms);

        $currentTerm = AcademicTerm::current();
        $this->assertNotNull($currentTerm);
        $this->assertSame('Second Term', $currentTerm->name);

        // Verify Classes & Sections (6 classes * 4 sections = 24 sections)
        $this->assertSame(6, SchoolClass::count());
        $this->assertSame(24, Section::count());

        $jss1 = SchoolClass::where('name', 'JSS 1')->first();
        $this->assertNotNull($jss1);
        $this->assertSame('junior_secondary', $jss1->stage);
        $this->assertCount(4, $jss1->sections);

        // Verify Subjects Catalog & Allocations
        $this->assertSame(17, Subject::count());
        $this->assertGreaterThan(0, $jss1->subjects()->count());

        // Verify WAEC/NECO Grades
        $this->assertSame(6, Grade::count());
        $this->assertSame('A', Grade::forScore(85.0)->name);
        $this->assertSame('B', Grade::forScore(64.5)->name);
        $this->assertSame('C', Grade::forScore(52.0)->name);
        $this->assertSame('D', Grade::forScore(45.0)->name);
        $this->assertSame('E', Grade::forScore(38.0)->name);
        $this->assertSame('F', Grade::forScore(25.0)->name);
    }

    public function test_student_enrollment_and_mark_grading_lifecycle(): void
    {
        $this->seed(StandardAcademicSeeder::class);

        $session = AcademicSession::current();
        $term = AcademicTerm::current();
        $class = SchoolClass::where('name', 'SS 1')->first();
        $section = $class->sections()->first();
        $subject = Subject::where('code', 'MTH')->first();
        $exam = Exam::where('academic_session_id', $session->id)->first();

        // Create Student User & Profile
        $user = User::create([
            'username' => 'GSSS25-00001',
            'name' => 'Aliyu Mohammed',
            'first_name' => 'Aliyu',
            'last_name' => 'Mohammed',
            'role' => UserRole::STUDENT,
            'gender' => Gender::MALE,
            'password' => 'secret123',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSSS25-00001',
            'admission_date' => now()->toDateString(),
        ]);

        // Enroll Student
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        // Input Assessment Scores (ca1: 8, ca2: 9, ca3: 7, exam: 58 => total: 82)
        $mark = StudentMark::create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 8.00,
            'ca2' => 9.00,
            'ca3' => 7.00,
            'exam_score' => 58.00,
        ]);

        // Reload to test stored total and auto-assigned grade
        $mark->refresh();
        $this->assertEquals(82.00, (float) $mark->total_score);
        $this->assertNotNull($mark->grade);
        $this->assertSame('A', $mark->grade->name);
        $this->assertSame('Excellent', $mark->grade->remark);
    }

    public function test_turnkey_app_install_command(): void
    {
        $this->artisan('app:install', [
            '--no-interaction' => true,
            '--school-name' => 'Danfodiyo Model School',
            '--admin-email' => 'principal@danfodiyo.test',
            '--academic-session' => '2026/2027',
        ])->assertSuccessful();

        $setting = SchoolSetting::first();
        $this->assertSame('Danfodiyo Model School', $setting->school_name);

        $admin = User::where('email', 'principal@danfodiyo.test')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isSuperAdmin());

        $session = AcademicSession::where('name', '2026/2027')->first();
        $this->assertNotNull($session);
    }
}
