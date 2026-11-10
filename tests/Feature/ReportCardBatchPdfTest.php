<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Exam;
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

class ReportCardBatchPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_access_batch_data(): void
    {
        $this->getJson(route('academic.report-cards.batch-data'))->assertUnauthorized();
    }

    public function test_unauthorized_users_cannot_access_batch_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('academic.report-cards.batch-data'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_fetch_class_batch_data_with_letterhead_and_margins(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        // Update school settings with Base64 letterhead and custom margins
        $school = SchoolSetting::current();
        $fakeBase64 = 'data:image/jpeg;base64,'.base64_encode('fake-letterhead-content');
        $school->update([
            'letterhead_base64' => $fakeBase64,
            'letterhead_top_margin' => 140,
            'letterhead_bottom_margin' => 60,
            'letterhead_left_margin' => 45,
            'letterhead_right_margin' => 45,
        ]);

        $response = $this->actingAs($admin)->getJson(route('academic.report-cards.batch-data', [
            'session_id' => $session->id,
            'term_id' => $term->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'session_name',
                'term_name',
                'class_name',
                'section_name',
                'section_total_students',
                'class_total_students',
                'school' => [
                    'name',
                    'letterhead_base64',
                    'stamp_base64',
                    'letterhead_top_margin',
                    'letterhead_bottom_margin',
                    'letterhead_left_margin',
                    'letterhead_right_margin',
                ],
                'exam',
                'students',
                'grades',
            ]);

        $this->assertEquals($fakeBase64, $response->json('school.letterhead_base64'));
        $this->assertEquals(140, $response->json('school.letterhead_top_margin'));
        $this->assertEquals(60, $response->json('school.letterhead_bottom_margin'));
        $this->assertEquals(45, $response->json('school.letterhead_left_margin'));
        $this->assertEquals(45, $response->json('school.letterhead_right_margin'));
    }

    public function test_batch_data_marks_contain_absent_flag(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();
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
            'admission_number' => 'SMS/BATCH/ABS01',
            'admission_date' => now()->toDateString(),
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        StudentMark::create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'ca1' => 0,
            'ca2' => 0,
            'ca3' => 0,
            'exam_score' => 0,
            'total_score' => 0,
            'teacher_comment' => 'Excellent work!',
        ]);

        $response = $this->actingAs($admin)->getJson(route('academic.report-cards.batch-data', [
            'session_id' => $session->id,
            'term_id' => $term->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]));

        $response->assertOk();
        $students = $response->json('students');
        $matched = collect($students)->firstWhere('id', $enrollment->id);
        $this->assertNotNull($matched);
        $this->assertTrue($matched['marks'][0]['is_absent']);
        $this->assertEquals('ABS', $matched['marks'][0]['grade']);
        $this->assertEquals('Absent', $matched['marks'][0]['remark']);
        $this->assertEquals('Absent', $matched['marks'][0]['teacher_comment']);
    }
}
