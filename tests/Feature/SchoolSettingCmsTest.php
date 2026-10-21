<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FrontendAnnouncement;
use App\Models\FrontendContactMessage;
use App\Models\FrontendFaq;
use App\Models\FrontendGallery;
use App\Models\FrontendSlider;
use App\Models\SchoolSetting;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolSettingCmsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
        $this->admin = User::where('username', 'superadmin')->firstOrFail();
    }

    public function test_admin_can_view_consolidated_settings_screen_with_cms_props(): void
    {
        $response = $this->actingAs($this->admin)->get(route('school-settings.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/School')
            ->has('settings')
            ->has('sliders')
            ->has('announcements')
            ->has('galleries')
            ->has('faqs')
            ->has('inquiries')
        );
    }

    public function test_admin_can_update_cms_narrative_and_leadership(): void
    {
        Storage::fake('public');

        $payload = [
            'school_name' => 'YUM Academy',
            'school_code' => 'YUM-SA',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'ca_count' => 3,
            'default_max_ca1' => 10.0,
            'default_max_ca2' => 10.0,
            'default_max_ca3' => 10.0,
            'default_max_exam' => 70.0,
            'ca1_label' => '1st CA',
            'ca2_label' => '2nd CA',
            'ca3_label' => '3rd CA',
            'about_text' => 'Updated academic story narrative.',
            'mission' => 'Updated mission statement.',
            'vision' => 'Updated vision statement.',
            'core_values' => ['Discipline', 'Pioneering Innovation'],
            'principal_name' => 'Dr. Ibrahim Gusau',
            'principal_message' => 'New welcome address to scholars.',
            'office_hours' => 'Mon - Fri: 7:30 AM - 4:00 PM',
            'principal_photo' => UploadedFile::fake()->image('principal.jpg', 400, 400),
            'prospectus' => UploadedFile::fake()->create('prospectus.pdf', 1000, 'application/pdf'),
        ];

        $response = $this->actingAs($this->admin)->post(route('school-settings.update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $settings = SchoolSetting::current();
        $this->assertEquals('Updated academic story narrative.', $settings->about_text);
        $this->assertEquals('Updated mission statement.', $settings->mission);
        $this->assertEquals('Mon - Fri: 7:30 AM - 4:00 PM', $settings->office_hours);
        $this->assertNotNull($settings->principal_photo_path);
        $this->assertNotNull($settings->prospectus_path);
        Storage::disk('public')->assertExists($settings->principal_photo_path);
        Storage::disk('public')->assertExists($settings->prospectus_path);
    }

    public function test_admin_can_create_and_delete_hero_slider(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->post(route('school-settings.sliders.store'), [
            'title' => 'State of the Art Physics Laboratory',
            'subtitle' => 'Empowering discovery through modern laboratory tools.',
            'badge' => 'STEM Innovation',
            'primary_button_text' => 'Take Virtual Tour',
            'primary_button_url' => '/gallery',
            'sort_order' => 2,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('slider.jpg', 1200, 600),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $slider = FrontendSlider::where('title', 'State of the Art Physics Laboratory')->firstOrFail();
        $this->assertNotNull($slider->image_path);
        Storage::disk('public')->assertExists($slider->image_path);

        // Delete slider
        $deleteResponse = $this->actingAs($this->admin)->delete(route('school-settings.sliders.destroy', $slider));
        $deleteResponse->assertRedirect();

        $this->assertDatabaseMissing('frontend_sliders', ['id' => $slider->id]);
        Storage::disk('public')->assertMissing($slider->image_path);
    }

    public function test_admin_can_publish_and_delete_announcement_circular(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->post(route('school-settings.announcements.store'), [
            'title' => 'First Term Examination Schedule Released',
            'category' => 'exam',
            'summary' => 'Complete exam timetable for Junior and Senior Secondary.',
            'content' => 'Please find attached the timetable. Morning sessions begin strictly at 8:30 AM.',
            'is_pinned' => true,
            'is_published' => true,
            'attachment' => UploadedFile::fake()->create('exam_timetable.pdf', 500, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $announcement = FrontendAnnouncement::where('title', 'First Term Examination Schedule Released')->firstOrFail();
        $this->assertTrue($announcement->is_pinned);
        $this->assertNotNull($announcement->attachment_path);
        $this->assertEquals('exam_timetable.pdf', $announcement->attachment_name);
        Storage::disk('public')->assertExists($announcement->attachment_path);

        // Delete announcement
        $deleteResponse = $this->actingAs($this->admin)->delete(route('school-settings.announcements.destroy', $announcement));
        $deleteResponse->assertRedirect();

        $this->assertDatabaseMissing('frontend_announcements', ['id' => $announcement->id]);
        Storage::disk('public')->assertMissing($announcement->attachment_path);
    }

    public function test_admin_can_add_and_delete_gallery_photo(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->post(route('school-settings.galleries.store'), [
            'title' => 'Inter-House Sports Relay Final',
            'category' => 'sports',
            'caption' => 'Yellow House sprinters taking first position.',
            'sort_order' => 1,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('sports.jpg', 800, 600),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $photo = FrontendGallery::where('title', 'Inter-House Sports Relay Final')->firstOrFail();
        Storage::disk('public')->assertExists($photo->image_path);

        $deleteResponse = $this->actingAs($this->admin)->delete(route('school-settings.galleries.destroy', $photo));
        $deleteResponse->assertRedirect();

        $this->assertDatabaseMissing('frontend_galleries', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->image_path);
    }

    public function test_admin_can_manage_faqs(): void
    {
        $response = $this->actingAs($this->admin)->post(route('school-settings.faqs.store'), [
            'category' => 'Admissions',
            'question' => 'What is the boarding facility capacity?',
            'answer' => 'Our campus accommodates up to 600 boarding scholars across 4 hostels.',
            'sort_order' => 2,
            'is_published' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $faq = FrontendFaq::where('question', 'What is the boarding facility capacity?')->firstOrFail();

        $deleteResponse = $this->actingAs($this->admin)->delete(route('school-settings.faqs.destroy', $faq));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('frontend_faqs', ['id' => $faq->id]);
    }

    public function test_admin_can_update_inquiry_status_and_add_notes(): void
    {
        $inquiry = FrontendContactMessage::factory()->create([
            'status' => 'unread',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('school-settings.inquiries.update', $inquiry), [
            'status' => 'replied',
            'admin_notes' => 'Spoke with parent via phone. Sent application form via email.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertEquals('replied', $inquiry->status);
        $this->assertEquals('Spoke with parent via phone. Sent application form via email.', $inquiry->admin_notes);
        $this->assertNotNull($inquiry->replied_at);
        $this->assertEquals($this->admin->id, $inquiry->replied_by);
    }

    public function test_unauthorized_user_cannot_update_school_settings_or_cms(): void
    {
        $student = User::factory()->create(['role' => UserRole::STUDENT]);
        $student->assignRole(UserRole::STUDENT->value);

        $response = $this->actingAs($student)->get(route('school-settings.edit'));
        $response->assertForbidden();
    }
}
