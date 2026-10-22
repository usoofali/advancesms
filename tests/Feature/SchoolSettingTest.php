<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FrontendSlider;
use App\Models\Grade;
use App\Models\SchoolSetting;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_school_settings(): void
    {
        $response = $this->get(route('school-settings.edit'));
        $response->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_school_settings(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $student->assignRole(UserRole::STUDENT->value);

        $response = $this->actingAs($student)->get(route('school-settings.edit'));
        $response->assertForbidden();
    }

    public function test_superadmin_can_view_school_settings_page(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('school-settings.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/School')
            ->has('settings')
            ->where('settings.school_code', 'YUM-SA')
        );
    }

    public function test_user_with_explicit_permission_can_view_school_settings(): void
    {
        $staff = User::factory()->create([
            'role' => UserRole::TEACHER,
            'email_verified_at' => now(),
        ]);
        $staff->givePermissionTo('school_settings.view');

        $response = $this->actingAs($staff)->get(route('school-settings.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/School')
            ->has('settings')
        );
    }

    public function test_user_without_update_permission_cannot_update_settings(): void
    {
        $staff = User::factory()->create([
            'role' => UserRole::TEACHER,
            'email_verified_at' => now(),
        ]);
        $staff->givePermissionTo('school_settings.view');

        $response = $this->actingAs($staff)->post(route('school-settings.update'), [
            'school_name' => 'Hacked School Name',
            'school_code' => 'HACK',
            'currency_symbol' => '$',
            'currency_code' => 'USD',
        ]);

        $response->assertForbidden();
    }

    public function test_authorized_user_can_update_text_fields(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'school_name' => 'Premier Science Academy',
            'school_code' => 'PSA-NG',
            'motto' => 'Leading Innovation & Scholarship',
            'address' => 'Plot 42 Innovation Way, Gusau',
            'phone' => '+234 809 111 2222',
            'email' => 'info@psa.sch.ng',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'principal_name' => 'Dr. Fatima Umar Ph.D',
        ];

        $response = $this->actingAs($admin)->post(route('school-settings.update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Institutional profile and branding updated successfully.');

        $this->assertDatabaseHas('school_settings', [
            'school_name' => 'Premier Science Academy',
            'school_code' => 'PSA-NG',
            'motto' => 'Leading Innovation & Scholarship',
            'address' => 'Plot 42 Innovation Way, Gusau',
            'phone' => '+234 809 111 2222',
            'email' => 'info@psa.sch.ng',
            'principal_name' => 'Dr. Fatima Umar Ph.D',
        ]);
    }

    public function test_authorized_user_can_upload_school_logo_and_stamp(): void
    {
        Storage::fake('public');

        $admin = User::where('username', 'superadmin')->firstOrFail();

        $logo = UploadedFile::fake()->image('crest.png', 300, 300);
        $stamp = UploadedFile::fake()->image('stamp.png', 400, 200);

        $payload = [
            'school_name' => 'Apex High School',
            'school_code' => 'AHS-2026',
            'currency_symbol' => '$',
            'currency_code' => 'USD',
            'logo' => $logo,
            'stamp' => $stamp,
        ];

        $response = $this->actingAs($admin)->post(route('school-settings.update'), $payload);

        $response->assertRedirect();

        $settings = SchoolSetting::current();
        $this->assertNotNull($settings->logo_path);
        $this->assertNotNull($settings->stamp_path);

        Storage::disk('public')->assertExists($settings->logo_path);
        Storage::disk('public')->assertExists($settings->stamp_path);

        $this->assertNotNull($settings->logo_url);
        $this->assertNotNull($settings->stamp_url);
    }

    public function test_subsequent_upload_replaces_old_files_on_disk(): void
    {
        Storage::fake('public');

        $admin = User::where('username', 'superadmin')->firstOrFail();

        // Initial upload
        $logo1 = UploadedFile::fake()->image('logo1.png', 200, 200);
        $this->actingAs($admin)->post(route('school-settings.update'), [
            'school_name' => 'Test School',
            'school_code' => 'TS-01',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'logo' => $logo1,
        ]);

        $settings = SchoolSetting::current();
        $oldLogoPath = $settings->logo_path;
        Storage::disk('public')->assertExists($oldLogoPath);

        // Upload new logo
        $logo2 = UploadedFile::fake()->image('logo2.png', 250, 250);
        $this->actingAs($admin)->post(route('school-settings.update'), [
            'school_name' => 'Test School',
            'school_code' => 'TS-01',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'logo' => $logo2,
        ]);

        $settings->refresh();
        $this->assertNotEquals($oldLogoPath, $settings->logo_path);
        Storage::disk('public')->assertMissing($oldLogoPath);
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_validation_fails_for_oversized_images(): void
    {
        Storage::fake('public');

        $admin = User::where('username', 'superadmin')->firstOrFail();

        // 3MB file exceeds 2048KB limit
        $oversizedLogo = UploadedFile::fake()->create('huge_logo.png', 3072, 'image/png');

        $response = $this->actingAs($admin)->post(route('school-settings.update'), [
            'school_name' => 'Valid Name',
            'school_code' => 'VALID',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'logo' => $oversizedLogo,
        ]);

        $response->assertSessionHasErrors(['logo']);
    }

    public function test_can_update_ca_configuration_policy(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'school_name' => 'Government Secondary School',
            'school_code' => 'YUM-SA',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'ca_count' => 2,
            'default_max_ca1' => 15.00,
            'default_max_ca2' => 15.00,
            'default_max_ca3' => 0.00,
            'default_max_exam' => 70.00,
            'ca1_label' => '1st Continuous Assessment',
            'ca2_label' => '2nd Continuous Assessment',
        ];

        $response = $this->actingAs($admin)->post(route('school-settings.update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('school_settings', [
            'ca_count' => 2,
            'default_max_ca1' => 15.00,
            'default_max_ca2' => 15.00,
            'default_max_ca3' => 0.00,
            'default_max_exam' => 70.00,
            'ca1_label' => '1st Continuous Assessment',
            'ca2_label' => '2nd Continuous Assessment',
        ]);
    }

    public function test_validation_fails_when_ca_weights_do_not_sum_to_100(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'school_name' => 'Government Secondary School',
            'school_code' => 'YUM-SA',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'ca_count' => 2,
            'default_max_ca1' => 20.00,
            'default_max_ca2' => 20.00,
            'default_max_exam' => 70.00, // Sum = 110% != 100%
        ];

        $response = $this->actingAs($admin)->post(route('school-settings.update'), $payload);

        $response->assertSessionHasErrors(['default_max_exam']);
    }

    public function test_validation_fails_for_invalid_ca_count(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('school-settings.update'), [
            'school_name' => 'Government Secondary School',
            'school_code' => 'YUM-SA',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'ca_count' => 4, // Max allowed is 3
        ]);

        $response->assertSessionHasErrors(['ca_count']);
    }

    public function test_admin_can_create_update_and_delete_grade_band(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        // 1. Create Grade Band
        $createResponse = $this->actingAs($admin)->post(route('school-settings.grades.store'), [
            'name' => 'A1',
            'grade_point' => 5.0,
            'mark_from' => 75.0,
            'mark_to' => 100.0,
            'remark' => 'Distinction',
        ]);

        $createResponse->assertRedirect();
        $this->assertDatabaseHas('grades', [
            'name' => 'A1',
            'remark' => 'Distinction',
        ]);

        /** @var Grade $grade */
        $grade = Grade::where('name', 'A1')->firstOrFail();

        // 2. Update Grade Band
        $updateResponse = $this->actingAs($admin)->put(route('school-settings.grades.update', $grade), [
            'name' => 'A1',
            'grade_point' => 5.0,
            'mark_from' => 80.0,
            'mark_to' => 100.0,
            'remark' => 'Outstanding Distinction',
        ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'mark_from' => 80.0,
            'remark' => 'Outstanding Distinction',
        ]);

        // 3. Delete Grade Band
        $deleteResponse = $this->actingAs($admin)->delete(route('school-settings.grades.destroy', $grade));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('grades', [
            'id' => $grade->id,
        ]);
    }

    public function test_admin_can_create_update_and_delete_hero_slider(): void
    {
        Storage::fake('public');
        $admin = User::where('username', 'superadmin')->firstOrFail();

        // 1. Create Slider
        $image = UploadedFile::fake()->image('banner1.jpg', 1920, 1080);
        $createResponse = $this->actingAs($admin)->post(route('school-settings.sliders.store'), [
            'title' => 'Empowering Future Leaders',
            'subtitle' => 'Excellence in education and innovation.',
            'badge' => 'Admissions Open',
            'primary_button_text' => 'Apply Now',
            'primary_button_url' => '/admissions',
            'secondary_button_text' => 'Contact Us',
            'secondary_button_url' => '/contact',
            'sort_order' => 1,
            'is_active' => true,
            'image' => $image,
        ]);

        $createResponse->assertRedirect();
        $this->assertDatabaseHas('frontend_sliders', [
            'title' => 'Empowering Future Leaders',
            'badge' => 'Admissions Open',
            'is_active' => true,
        ]);

        /** @var FrontendSlider $slider */
        $slider = FrontendSlider::where('title', 'Empowering Future Leaders')->firstOrFail();
        $initialImagePath = $slider->image_path;
        $this->assertNotNull($initialImagePath);
        Storage::disk('public')->assertExists($initialImagePath);

        // 2. Update Slider without changing image
        $updateResponse = $this->actingAs($admin)->post(route('school-settings.sliders.update', $slider), [
            'title' => 'Updated Future Leaders Title',
            'subtitle' => 'Updated subtitle description.',
            'badge' => 'Limited Seats',
            'primary_button_text' => 'Enroll Today',
            'primary_button_url' => '/enroll',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $updateResponse->assertRedirect();
        $slider->refresh();
        $this->assertEquals('Updated Future Leaders Title', $slider->title);
        $this->assertEquals('Limited Seats', $slider->badge);
        $this->assertFalse($slider->is_active);
        $this->assertEquals($initialImagePath, $slider->image_path);

        // 3. Update Slider with a new image (verifying replacement)
        $newImage = UploadedFile::fake()->image('banner2.jpg', 1920, 1080);
        $updateWithImageResponse = $this->actingAs($admin)->post(route('school-settings.sliders.update', $slider), [
            'title' => 'Updated With New Banner',
            'sort_order' => 3,
            'is_active' => true,
            'image' => $newImage,
        ]);

        $updateWithImageResponse->assertRedirect();
        $slider->refresh();
        $this->assertEquals('Updated With New Banner', $slider->title);
        $this->assertTrue($slider->is_active);
        $this->assertNotEquals($initialImagePath, $slider->image_path);
        Storage::disk('public')->assertExists($slider->image_path);
        Storage::disk('public')->assertMissing($initialImagePath);

        // 4. Delete Slider
        $deleteResponse = $this->actingAs($admin)->delete(route('school-settings.sliders.destroy', $slider));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('frontend_sliders', [
            'id' => $slider->id,
        ]);
        Storage::disk('public')->assertMissing($slider->image_path);
    }
}
