<?php

namespace Tests\Feature;

use App\Models\FrontendAnnouncement;
use App\Models\FrontendFaq;
use App\Models\FrontendGallery;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendPublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_public_guest_can_view_home_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('frontend/Home')
            ->has('sliders')
            ->has('announcements')
            ->has('stats')
            ->has('principal')
            ->has('galleryPreview')
            ->where('principal.name', 'Dr. Ibrahim Gusau')
        );
    }

    public function test_public_guest_can_view_about_page(): void
    {
        $response = $this->get(route('frontend.about'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('frontend/About')
            ->has('settings')
            ->has('departments')
            ->where('settings.school_name', 'YUM Academy')
        );
    }

    public function test_public_guest_can_view_academics_page_with_dynamic_data(): void
    {
        $response = $this->get(route('frontend.academics'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('frontend/Academics')
            ->has('classes')
            ->has('departments')
            ->has('activeSession')
            ->has('activeTerm')
        );
    }

    public function test_public_guest_can_view_gallery_page(): void
    {
        FrontendGallery::factory()->create([
            'title' => 'Biology Science Laboratory',
            'category' => 'science_labs',
            'is_active' => true,
        ]);

        $response = $this->get(route('frontend.gallery'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('frontend/Gallery')
            ->has('items.data')
            ->has('categories')
        );
    }

    public function test_public_guest_can_view_faq_page(): void
    {
        FrontendFaq::factory()->create([
            'category' => 'Admissions',
            'question' => 'What is the cutoff score for JSS 1?',
            'answer' => 'A minimum score of 60% in the entrance examination is required.',
            'is_published' => true,
        ]);

        $response = $this->get(route('frontend.faq'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('frontend/Faq')
            ->has('faqGroups.Admissions')
        );
    }

    public function test_public_guest_can_view_announcements_index_and_filter(): void
    {
        FrontendAnnouncement::factory()->create([
            'title' => 'Annual Cultural and Sports Day',
            'category' => 'sports',
            'is_published' => true,
        ]);

        $response = $this->get(route('frontend.announcements.index', ['category' => 'sports']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('frontend/announcements/Index')
            ->has('announcements.data')
            ->where('filters.category', 'sports')
        );
    }

    public function test_public_guest_can_view_published_announcement_details(): void
    {
        $announcement = FrontendAnnouncement::factory()->create([
            'title' => 'End of Term Assessment Timetable',
            'slug' => 'end-of-term-assessment-timetable',
            'content' => 'Full timetable instructions.',
            'is_published' => true,
        ]);

        $response = $this->get(route('frontend.announcements.show', ['slug' => $announcement->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('frontend/announcements/Show')
            ->where('announcement.title', 'End of Term Assessment Timetable')
            ->has('recentAnnouncements')
        );
    }

    public function test_public_guest_cannot_view_unpublished_announcement(): void
    {
        $draft = FrontendAnnouncement::factory()->create([
            'title' => 'Confidential Board Meeting Notice',
            'slug' => 'confidential-board-meeting-notice',
            'is_published' => false,
        ]);

        $response = $this->get(route('frontend.announcements.show', ['slug' => $draft->slug]));

        $response->assertNotFound();
    }

    public function test_public_guest_can_view_contact_page(): void
    {
        $response = $this->get(route('frontend.contact.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('frontend/Contact')
            ->has('contactInfo')
            ->where('contactInfo.school_name', 'YUM Academy')
        );
    }

    public function test_public_guest_can_submit_contact_message(): void
    {
        $payload = [
            'name' => 'Musa Abdullahi',
            'email' => 'musa@example.com',
            'phone' => '+234 802 333 4444',
            'subject' => 'Inquiry on JSS 1 Admissions',
            'message' => 'Good day, I would like to know if admission forms for next session are still on sale.',
        ];

        $response = $this->from(route('frontend.contact.index'))
            ->post(route('frontend.contact.store'), $payload);

        $response->assertRedirect(route('frontend.contact.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('frontend_contact_messages', [
            'name' => 'Musa Abdullahi',
            'email' => 'musa@example.com',
            'subject' => 'Inquiry on JSS 1 Admissions',
            'status' => 'unread',
        ]);
    }

    public function test_contact_form_validation_requires_all_essential_fields(): void
    {
        $response = $this->from(route('frontend.contact.index'))
            ->post(route('frontend.contact.store'), [
                'name' => '',
                'email' => 'invalid-email',
                'subject' => '',
                'message' => 'too short',
            ]);

        $response->assertRedirect(route('frontend.contact.index'));
        $response->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    }
}
