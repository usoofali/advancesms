<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_access_academic_sessions(): void
    {
        $response = $this->get(route('academic.sessions.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_academic_sessions(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $student->assignRole(UserRole::STUDENT->value);

        $response = $this->actingAs($student)->get(route('academic.sessions.index'));
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_academic_sessions_page(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.sessions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/sessions/Index')
            ->has('sessions')
            ->has('stats')
            ->has('currentSession')
            ->has('currentTerm')
        );
    }

    public function test_authorized_user_can_create_academic_session_with_auto_generated_terms(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'is_current' => false,
            'generate_terms' => true,
        ];

        $response = $this->actingAs($admin)->post(route('academic.sessions.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('academic_sessions', [
            'name' => '2026/2027',
            'is_current' => false,
        ]);

        $session = AcademicSession::where('name', '2026/2027')->firstOrFail();
        $this->assertCount(3, $session->terms);

        $this->assertDatabaseHas('academic_terms', [
            'academic_session_id' => $session->id,
            'term_number' => 1,
            'name' => 'First Term',
        ]);
        $this->assertDatabaseHas('academic_terms', [
            'academic_session_id' => $session->id,
            'term_number' => 2,
            'name' => 'Second Term',
        ]);
        $this->assertDatabaseHas('academic_terms', [
            'academic_session_id' => $session->id,
            'term_number' => 3,
            'name' => 'Third Term',
        ]);
    }

    public function test_authorized_user_can_update_academic_session(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $session = AcademicSession::create([
            'name' => '2027/2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-07-31',
            'is_current' => false,
        ]);

        $response = $this->actingAs($admin)->put(route('academic.sessions.update', $session), [
            'name' => '2027/2028 Updated',
            'start_date' => '2027-09-15',
            'end_date' => '2028-08-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('academic_sessions', [
            'id' => $session->id,
            'name' => '2027/2028 Updated',
        ]);
    }

    public function test_cannot_delete_active_academic_session(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $activeSession = AcademicSession::where('is_current', true)->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('academic.sessions.destroy', $activeSession));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('academic_sessions', ['id' => $activeSession->id]);
    }

    public function test_cannot_delete_academic_session_with_enrollments(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $session = AcademicSession::create([
            'name' => '2028/2029',
            'start_date' => '2028-09-01',
            'end_date' => '2029-07-31',
            'is_current' => false,
        ]);

        $user = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSA/2026/001',
            'admission_date' => now(),
        ]);
        $class = SchoolClass::firstOrFail();
        $section = Section::firstOrFail();

        Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'enrollment_date' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.sessions.destroy', $session));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('academic_sessions', ['id' => $session->id]);
    }

    public function test_authorized_user_can_delete_unused_inactive_session(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $session = AcademicSession::create([
            'name' => '2029/2030',
            'start_date' => '2029-09-01',
            'end_date' => '2030-07-31',
            'is_current' => false,
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.sessions.destroy', $session));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('academic_sessions', ['id' => $session->id]);
    }

    public function test_authorized_user_can_set_current_academic_session(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $session = AcademicSession::create([
            'name' => '2030/2031',
            'start_date' => '2030-09-01',
            'end_date' => '2031-07-31',
            'is_current' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('academic.sessions.set-current', $session));

        $response->assertRedirect();
        $this->assertTrue($session->fresh()->is_current);

        // Ensure other sessions are now inactive
        $otherActiveCount = AcademicSession::where('id', '!=', $session->id)->where('is_current', true)->count();
        $this->assertEquals(0, $otherActiveCount);
    }

    public function test_authorized_user_can_create_and_update_academic_term(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $session = AcademicSession::create([
            'name' => '2031/2032',
            'start_date' => '2031-09-01',
            'end_date' => '2032-07-31',
            'is_current' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('academic.terms.store'), [
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'term_number' => 1,
            'start_date' => '2031-09-01',
            'end_date' => '2031-12-15',
            'is_current' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('academic_terms', [
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'term_number' => 1,
        ]);

        $term = AcademicTerm::where('academic_session_id', $session->id)->firstOrFail();

        $responseUpdate = $this->actingAs($admin)->put(route('academic.terms.update', $term), [
            'name' => 'First Term Adjusted',
            'term_number' => 1,
            'start_date' => '2031-09-10',
            'end_date' => '2031-12-20',
        ]);

        $responseUpdate->assertRedirect();
        $this->assertDatabaseHas('academic_terms', [
            'id' => $term->id,
            'name' => 'First Term Adjusted',
        ]);
    }

    public function test_cannot_delete_active_academic_term(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $activeTerm = AcademicTerm::where('is_current', true)->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('academic.terms.destroy', $activeTerm));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('academic_terms', ['id' => $activeTerm->id]);
    }

    public function test_authorized_user_can_set_current_academic_term(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $session = AcademicSession::create([
            'name' => '2032/2033',
            'start_date' => '2032-09-01',
            'end_date' => '2033-07-31',
            'is_current' => false,
        ]);

        $term = AcademicTerm::create([
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'term_number' => 1,
            'start_date' => '2032-09-01',
            'end_date' => '2032-12-15',
            'is_current' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('academic.terms.set-current', $term));

        $response->assertRedirect();
        $this->assertTrue($term->fresh()->is_current);

        // Also parent session should become current
        $this->assertTrue($session->fresh()->is_current);

        // Other terms should be inactive
        $otherActiveTermsCount = AcademicTerm::where('id', '!=', $term->id)->where('is_current', true)->count();
        $this->assertEquals(0, $otherActiveTermsCount);
    }
}
