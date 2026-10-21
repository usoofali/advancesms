<?php

namespace Tests\Feature;

use App\Enums\EducationalStage;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_classes_or_departments(): void
    {
        $this->get(route('academic.classes.index'))->assertRedirect(route('login'));
        $this->get(route('academic.departments.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_classes(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $student->assignRole(UserRole::STUDENT->value);

        $this->actingAs($student)->get(route('academic.classes.index'))->assertForbidden();
        $this->actingAs($student)->get(route('academic.departments.index'))->assertForbidden();
    }

    public function test_authorized_user_can_view_classes_page(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.classes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/classes/Index')
            ->has('classes')
            ->has('stats')
            ->has('stages')
        );
    }

    public function test_authorized_user_can_create_class_with_flexible_stage_and_custom_sections(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'name' => 'Primary 1',
            'stage' => 'primary',
            'level_order' => 1,
            'create_sections' => true,
            'initial_sections' => 'Red, Green, Blue',
            'section_capacity' => 35,
        ];

        $response = $this->actingAs($admin)->post(route('academic.classes.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('classes', [
            'name' => 'Primary 1',
            'stage' => 'primary',
            'level_order' => 1,
        ]);

        $class = SchoolClass::where('name', 'Primary 1')->firstOrFail();
        $this->assertCount(3, $class->sections);

        $this->assertDatabaseHas('sections', [
            'class_id' => $class->id,
            'name' => 'Red',
            'capacity' => 35,
        ]);
        $this->assertDatabaseHas('sections', [
            'class_id' => $class->id,
            'name' => 'Blue',
            'capacity' => 35,
        ]);
    }

    public function test_authorized_user_can_create_class_with_kindergarten_or_custom_stage(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'name' => 'KG 1 Sunshine',
            'stage' => 'kindergarten',
            'level_order' => 1,
            'create_sections' => false,
        ];

        $response = $this->actingAs($admin)->post(route('academic.classes.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('classes', [
            'name' => 'KG 1 Sunshine',
            'stage' => 'kindergarten',
        ]);
    }

    public function test_authorized_user_can_update_class(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $class = SchoolClass::create([
            'name' => 'Advanced Technical',
            'stage' => EducationalStage::SENIOR_SECONDARY->value,
            'level_order' => 8,
        ]);

        $response = $this->actingAs($admin)->put(route('academic.classes.update', $class), [
            'name' => 'Vocational Technical',
            'stage' => EducationalStage::SENIOR_SECONDARY->value,
            'level_order' => 8,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'name' => 'Vocational Technical',
        ]);
    }

    public function test_cannot_delete_class_with_dependencies(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        // Seeded class 'JSS 1' already has curriculum subjects attached
        $class = SchoolClass::where('name', 'JSS 1')->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('academic.classes.destroy', $class));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_authorized_user_can_delete_unused_class(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $class = SchoolClass::create([
            'name' => 'Temporary Class',
            'stage' => EducationalStage::JUNIOR_SECONDARY->value,
            'level_order' => 10,
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.classes.destroy', $class));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('classes', ['id' => $class->id]);
    }

    public function test_authorized_user_can_create_and_update_section(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $class = SchoolClass::firstOrFail();

        $response = $this->actingAs($admin)->post(route('academic.sections.store'), [
            'class_id' => $class->id,
            'name' => 'Gold',
            'capacity' => 50,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sections', [
            'class_id' => $class->id,
            'name' => 'Gold',
            'capacity' => 50,
        ]);

        $section = Section::where('class_id', $class->id)->where('name', 'Gold')->firstOrFail();

        $responseUpdate = $this->actingAs($admin)->put(route('academic.sections.update', $section), [
            'name' => 'Diamond',
            'capacity' => 55,
        ]);

        $responseUpdate->assertRedirect();
        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'name' => 'Diamond',
            'capacity' => 55,
        ]);
    }

    public function test_cannot_delete_section_with_active_enrollments(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $class = SchoolClass::firstOrFail();
        $section = Section::where('class_id', $class->id)->firstOrFail();

        $user = User::factory()->create(['role' => UserRole::STUDENT]);
        $student = Student::create([
            'user_id' => $user->id,
            'admission_number' => 'GSA/TEST/002',
            'admission_date' => now(),
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => 1,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'enrollment_date' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.sections.destroy', $section));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('sections', ['id' => $section->id]);
    }

    public function test_authorized_user_can_delete_unused_section(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $class = SchoolClass::firstOrFail();
        $section = Section::create([
            'class_id' => $class->id,
            'name' => 'Z',
            'capacity' => 40,
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.sections.destroy', $section));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_authorized_user_can_view_departments_page(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('academic.departments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('academic/departments/Index')
            ->has('departments')
            ->has('stats')
        );
    }

    public function test_authorized_user_can_create_and_update_department(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('academic.departments.store'), [
            'name' => 'Physical & Health Education',
            'description' => 'Athletics, sports sciences, and gymnastics faculty',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'name' => 'Physical & Health Education',
        ]);

        $dept = Department::where('name', 'Physical & Health Education')->firstOrFail();

        $responseUpdate = $this->actingAs($admin)->put(route('academic.departments.update', $dept), [
            'name' => 'Sports & Physical Education',
            'description' => 'Updated physical education description',
        ]);

        $responseUpdate->assertRedirect();
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'name' => 'Sports & Physical Education',
        ]);
    }

    public function test_cannot_delete_department_with_subjects(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        // Seeded department 'Sciences' has subjects like Mathematics, Physics, Chemistry
        $dept = Department::where('name', 'Sciences')->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('academic.departments.destroy', $dept));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('departments', ['id' => $dept->id]);
    }

    public function test_authorized_user_can_delete_unused_department(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $dept = Department::create([
            'name' => 'Specialized Languages',
            'description' => 'French and Arabic specialized studies',
        ]);

        $response = $this->actingAs($admin)->delete(route('academic.departments.destroy', $dept));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
    }

    public function test_inserting_class_before_existing_class_automatically_shifts_classes(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $jss1 = SchoolClass::where('name', 'JSS 1')->firstOrFail();
        $originalOrder = $jss1->level_order;

        $response = $this->actingAs($admin)->post(route('academic.classes.store'), [
            'name' => 'Primary 6',
            'stage' => 'primary',
            'position_mode' => 'before',
            'relative_class_id' => $jss1->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $primary6 = SchoolClass::where('name', 'Primary 6')->firstOrFail();
        $jss1->refresh();

        $this->assertEquals($originalOrder, $primary6->level_order);
        $this->assertEquals($originalOrder + 1, $jss1->level_order);
    }

    public function test_inserting_class_at_start_shifts_all_classes(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $lowestClass = SchoolClass::orderBy('level_order')->firstOrFail();
        $originalLowestOrder = $lowestClass->level_order;

        $response = $this->actingAs($admin)->post(route('academic.classes.store'), [
            'name' => 'Creche & Playgroup',
            'stage' => 'kindergarten',
            'position_mode' => 'start',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $creche = SchoolClass::where('name', 'Creche & Playgroup')->firstOrFail();
        $lowestClass->refresh();

        $this->assertEquals(1, $creche->level_order);
        $this->assertEquals($originalLowestOrder + 1, $lowestClass->level_order);
    }
}
