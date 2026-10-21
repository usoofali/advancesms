<?php

namespace Tests\Feature;

use App\Models\FrontendAnnouncement;
use App\Models\FrontendContactMessage;
use App\Models\FrontendFaq;
use App\Models\FrontendGallery;
use App\Models\FrontendSlider;
use App\Models\SchoolSetting;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontendCmsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_standard_seeder_initializes_cms_profile_and_minimal_records(): void
    {
        $settings = SchoolSetting::current();

        $this->assertEquals('YUM Academy', $settings->school_name);
        $this->assertNotEmpty($settings->about_text);
        $this->assertNotEmpty($settings->mission);
        $this->assertNotEmpty($settings->vision);
        $this->assertIsArray($settings->core_values);
        $this->assertCount(4, $settings->core_values);
        $this->assertIsArray($settings->social_links);

        $this->assertDatabaseHas('frontend_sliders', [
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('frontend_announcements', [
            'slug' => 'welcome-to-new-academic-session',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('frontend_faqs', [
            'category' => 'Admissions',
            'is_published' => true,
        ]);
    }

    public function test_school_setting_accessors_and_json_casts(): void
    {
        Storage::fake('public');

        $settings = SchoolSetting::current();
        $settings->update([
            'principal_photo_path' => 'branding/principal.jpg',
            'prospectus_path' => 'documents/prospectus.pdf',
            'core_values' => ['Discipline', 'Innovation'],
            'social_links' => ['facebook' => 'https://facebook.com/gsa'],
        ]);

        $settings->refresh();

        $this->assertStringContainsString('branding/principal.jpg', $settings->principal_photo_url ?? '');
        $this->assertStringContainsString('documents/prospectus.pdf', $settings->prospectus_url ?? '');
        $this->assertEquals(['Discipline', 'Innovation'], $settings->core_values);
        $this->assertEquals(['facebook' => 'https://facebook.com/gsa'], $settings->social_links);
    }

    public function test_frontend_announcement_slug_generation_and_scopes(): void
    {
        $author = User::factory()->create();

        $announcement = FrontendAnnouncement::create([
            'title' => 'Inter-House Sports Festival 2026',
            'content' => 'Annual sports competition between houses.',
            'category' => 'sports',
            'author_id' => $author->id,
            'is_published' => true,
            'is_pinned' => true,
        ]);

        $this->assertEquals('inter-house-sports-festival-2026', $announcement->slug);
        $this->assertTrue($announcement->is_pinned);
        $this->assertEquals($author->id, $announcement->author?->id);

        $published = FrontendAnnouncement::published()->pluck('id');
        $this->assertTrue($published->contains($announcement->id));

        $pinned = FrontendAnnouncement::pinned()->pluck('id');
        $this->assertTrue($pinned->contains($announcement->id));
    }

    public function test_frontend_slider_ordering_and_active_scope(): void
    {
        FrontendSlider::query()->delete();

        $slider2 = FrontendSlider::factory()->create(['sort_order' => 20, 'is_active' => true]);
        $slider1 = FrontendSlider::factory()->create(['sort_order' => 10, 'is_active' => true]);
        $inactive = FrontendSlider::factory()->create(['sort_order' => 5, 'is_active' => false]);

        $activeSliders = FrontendSlider::active()->ordered()->get();

        $this->assertCount(2, $activeSliders);
        $this->assertEquals($slider1->id, $activeSliders->first()?->id);
        $this->assertEquals($slider2->id, $activeSliders->last()?->id);
        $this->assertFalse($activeSliders->contains('id', $inactive->id));
    }

    public function test_frontend_gallery_active_and_ordered_scopes(): void
    {
        $item1 = FrontendGallery::factory()->create(['sort_order' => 5, 'is_active' => true]);
        $inactive = FrontendGallery::factory()->create(['sort_order' => 1, 'is_active' => false]);

        $items = FrontendGallery::active()->ordered()->get();

        $this->assertTrue($items->contains('id', $item1->id));
        $this->assertFalse($items->contains('id', $inactive->id));
    }

    public function test_frontend_faq_published_and_ordered_scopes(): void
    {
        $faq = FrontendFaq::factory()->create(['sort_order' => 1, 'is_published' => true]);
        $draft = FrontendFaq::factory()->create(['sort_order' => 2, 'is_published' => false]);

        $published = FrontendFaq::published()->ordered()->get();

        $this->assertTrue($published->contains('id', $faq->id));
        $this->assertFalse($published->contains('id', $draft->id));
    }

    public function test_frontend_contact_message_unread_scope(): void
    {
        $unread = FrontendContactMessage::factory()->create(['status' => 'unread']);
        $read = FrontendContactMessage::factory()->create(['status' => 'read']);

        $messages = FrontendContactMessage::unread()->get();

        $this->assertTrue($messages->contains('id', $unread->id));
        $this->assertFalse($messages->contains('id', $read->id));
    }
}
