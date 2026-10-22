<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSchoolSettingRequest;
use App\Models\Exam;
use App\Models\FrontendAnnouncement;
use App\Models\FrontendContactMessage;
use App\Models\FrontendFaq;
use App\Models\FrontendGallery;
use App\Models\FrontendSlider;
use App\Models\Grade;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SchoolSettingController extends Controller
{
    /**
     * Show the institutional settings and consolidated CMS tabs.
     */
    public function edit(Request $request): Response
    {
        if (! $request->user()?->can('school_settings.view') && ! $request->user()?->can('frontend_cms.view')) {
            abort(403, 'Unauthorized to view institutional profile and CMS settings.');
        }

        $settings = SchoolSetting::current();
        $grades = Grade::query()->orderByDesc('mark_from')->get();
        $sliders = FrontendSlider::ordered()->get();
        $announcements = FrontendAnnouncement::latest()->paginate(10, ['*'], 'announcements_page')->withQueryString();
        $galleries = FrontendGallery::ordered()->get();
        $faqs = FrontendFaq::ordered()->get();
        $inquiries = FrontendContactMessage::latest()->paginate(15, ['*'], 'inquiries_page')->withQueryString();

        return Inertia::render('settings/School', [
            'settings' => $settings,
            'grades' => $grades,
            'sliders' => $sliders,
            'announcements' => $announcements,
            'galleries' => $galleries,
            'faqs' => $faqs,
            'inquiries' => $inquiries,
        ]);
    }

    /**
     * Update the institutional profile, branding assets, and CMS narrative.
     */
    public function update(UpdateSchoolSettingRequest $request): RedirectResponse
    {
        $settings = SchoolSetting::current();

        /** @var array<string, mixed> $data */
        $data = $request->safe()->except(['logo', 'stamp', 'principal_photo', 'prospectus', 'letterhead_file']);

        // 1. Process School Logo Upload
        if ($request->hasFile('logo')) {
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $logoFile = $request->file('logo');
            if ($logoFile) {
                $data['logo_path'] = $logoFile->store('branding', 'public');
            }
        }

        // 2. Process Principal Stamp / Signature Upload
        if ($request->hasFile('stamp')) {
            if ($settings->stamp_path && Storage::disk('public')->exists($settings->stamp_path)) {
                Storage::disk('public')->delete($settings->stamp_path);
            }

            $stampFile = $request->file('stamp');
            if ($stampFile) {
                $data['stamp_path'] = $stampFile->store('branding', 'public');
            }
        }

        // 3. Process Principal Portrait Upload
        if ($request->hasFile('principal_photo')) {
            if ($settings->principal_photo_path && Storage::disk('public')->exists($settings->principal_photo_path)) {
                Storage::disk('public')->delete($settings->principal_photo_path);
            }

            $principalFile = $request->file('principal_photo');
            if ($principalFile) {
                $data['principal_photo_path'] = $principalFile->store('branding', 'public');
            }
        }

        // 4. Process Academic Prospectus PDF Upload
        if ($request->hasFile('prospectus')) {
            if ($settings->prospectus_path && Storage::disk('public')->exists($settings->prospectus_path)) {
                Storage::disk('public')->delete($settings->prospectus_path);
            }

            $prospectusFile = $request->file('prospectus');
            if ($prospectusFile) {
                $data['prospectus_path'] = $prospectusFile->store('documents', 'public');
            }
        }

        // 5. Process Letterhead File Upload (stored as Base64 in DB)
        if ($request->hasFile('letterhead_file')) {
            $letterheadFile = $request->file('letterhead_file');
            if ($letterheadFile && $letterheadFile->isValid()) {
                $mime = $letterheadFile->getMimeType() ?: 'image/jpeg';
                $base64 = base64_encode(file_get_contents($letterheadFile->getRealPath()));
                $data['letterhead_base64'] = "data:{$mime};base64,{$base64}";
            }
        } elseif ($request->has('letterhead_base64')) {
            $base64Input = $request->input('letterhead_base64');
            $data['letterhead_base64'] = ! empty($base64Input) ? $base64Input : null;
        }

        $settings->update($data);

        // Synchronize institutional Continuous Assessment structure to unpublished exams
        Exam::where('is_published', false)->update([
            'ca_count' => $settings->ca_count,
            'max_ca1' => $settings->default_max_ca1,
            'max_ca2' => $settings->ca_count >= 2 ? $settings->default_max_ca2 : 0,
            'max_ca3' => $settings->ca_count >= 3 ? $settings->default_max_ca3 : 0,
            'max_exam' => $settings->default_max_exam,
            'ca1_label' => $settings->ca1_label,
            'ca2_label' => $settings->ca_count >= 2 ? $settings->ca2_label : null,
            'ca3_label' => $settings->ca_count >= 3 ? $settings->ca3_label : null,
        ]);

        return redirect()->back()->with('success', 'Institutional profile and branding updated successfully.');
    }

    // =========================================================================
    // SLIDERS CMS MANAGEMENT
    // =========================================================================

    public function storeSlider(Request $request): RedirectResponse
    {
        if (! $request->user()?->can('school_settings.update') && ! $request->user()?->can('frontend_cms.update')) {
            abort(403, 'Unauthorized to create hero slides.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'badge' => ['nullable', 'string', 'max:100'],
            'primary_button_text' => ['nullable', 'string', 'max:100'],
            'primary_button_url' => ['nullable', 'string', 'max:255'],
            'secondary_button_text' => ['nullable', 'string', 'max:100'],
            'secondary_button_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')?->store('sliders', 'public');
        }
        unset($validated['image']);

        FrontendSlider::create($validated);

        return redirect()->back()->with('success', 'Hero slide created successfully.');
    }

    public function updateSlider(Request $request, FrontendSlider $slider): RedirectResponse
    {
        if (! $request->user()?->can('school_settings.update') && ! $request->user()?->can('frontend_cms.update')) {
            abort(403, 'Unauthorized to update hero slides.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'badge' => ['nullable', 'string', 'max:100'],
            'primary_button_text' => ['nullable', 'string', 'max:100'],
            'primary_button_url' => ['nullable', 'string', 'max:255'],
            'secondary_button_text' => ['nullable', 'string', 'max:100'],
            'secondary_button_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            if ($slider->image_path && Storage::disk('public')->exists($slider->image_path)) {
                Storage::disk('public')->delete($slider->image_path);
            }
            $validated['image_path'] = $request->file('image')?->store('sliders', 'public');
        }
        unset($validated['image']);

        $slider->update($validated);

        return redirect()->back()->with('success', 'Hero slide updated successfully.');
    }

    public function destroySlider(Request $request, FrontendSlider $slider): RedirectResponse
    {
        if (! $request->user()?->can('school_settings.update') && ! $request->user()?->can('frontend_cms.update')) {
            abort(403, 'Unauthorized to delete hero slides.');
        }

        if ($slider->image_path && Storage::disk('public')->exists($slider->image_path)) {
            Storage::disk('public')->delete($slider->image_path);
        }

        $slider->delete();

        return redirect()->back()->with('success', 'Hero slide removed successfully.');
    }

    // =========================================================================
    // ANNOUNCEMENTS CMS MANAGEMENT
    // =========================================================================

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'is_pinned' => ['boolean'],
            'is_published' => ['boolean'],
            'cover_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,docx,doc', 'max:10240'],
        ]);

        $validated['author_id'] = $request->user()?->id;

        if ($request->hasFile('cover_image')) {
            $validated['cover_image_path'] = $request->file('cover_image')?->store('announcements', 'public');
        }

        if ($request->hasFile('attachment')) {
            $attachmentFile = $request->file('attachment');
            $validated['attachment_path'] = $attachmentFile?->store('announcements', 'public');
            $validated['attachment_name'] = $attachmentFile?->getClientOriginalName();
        }

        FrontendAnnouncement::create($validated);

        return redirect()->back()->with('success', 'Announcement published successfully.');
    }

    public function updateAnnouncement(Request $request, FrontendAnnouncement $announcement): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'is_pinned' => ['boolean'],
            'is_published' => ['boolean'],
            'cover_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,docx,doc', 'max:10240'],
        ]);

        if ($request->hasFile('cover_image')) {
            if ($announcement->cover_image_path && Storage::disk('public')->exists($announcement->cover_image_path)) {
                Storage::disk('public')->delete($announcement->cover_image_path);
            }
            $validated['cover_image_path'] = $request->file('cover_image')?->store('announcements', 'public');
        }

        if ($request->hasFile('attachment')) {
            if ($announcement->attachment_path && Storage::disk('public')->exists($announcement->attachment_path)) {
                Storage::disk('public')->delete($announcement->attachment_path);
            }
            $attachmentFile = $request->file('attachment');
            $validated['attachment_path'] = $attachmentFile?->store('announcements', 'public');
            $validated['attachment_name'] = $attachmentFile?->getClientOriginalName();
        }

        $announcement->update($validated);

        return redirect()->back()->with('success', 'Announcement updated successfully.');
    }

    public function destroyAnnouncement(FrontendAnnouncement $announcement): RedirectResponse
    {
        if ($announcement->cover_image_path && Storage::disk('public')->exists($announcement->cover_image_path)) {
            Storage::disk('public')->delete($announcement->cover_image_path);
        }
        if ($announcement->attachment_path && Storage::disk('public')->exists($announcement->attachment_path)) {
            Storage::disk('public')->delete($announcement->attachment_path);
        }

        $announcement->delete();

        return redirect()->back()->with('success', 'Announcement removed successfully.');
    }

    // =========================================================================
    // GALLERY CMS MANAGEMENT
    // =========================================================================

    public function storeGallery(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'caption' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);

        $validated['image_path'] = $request->file('image')->store('gallery', 'public');

        FrontendGallery::create($validated);

        return redirect()->back()->with('success', 'Campus photo added to gallery.');
    }

    public function destroyGallery(FrontendGallery $gallery): RedirectResponse
    {
        if ($gallery->image_path && Storage::disk('public')->exists($gallery->image_path)) {
            Storage::disk('public')->delete($gallery->image_path);
        }

        $gallery->delete();

        return redirect()->back()->with('success', 'Photo removed from gallery.');
    }

    // =========================================================================
    // FAQS CMS MANAGEMENT
    // =========================================================================

    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_published' => ['boolean'],
        ]);

        FrontendFaq::create($validated);

        return redirect()->back()->with('success', 'FAQ question added successfully.');
    }

    public function updateFaq(Request $request, FrontendFaq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_published' => ['boolean'],
        ]);

        $faq->update($validated);

        return redirect()->back()->with('success', 'FAQ updated successfully.');
    }

    public function destroyFaq(FrontendFaq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->back()->with('success', 'FAQ question deleted.');
    }

    // =========================================================================
    // INQUIRIES MANAGEMENT
    // =========================================================================

    public function updateInquiry(Request $request, FrontendContactMessage $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:unread,read,replied,archived'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === 'replied' && ! $inquiry->replied_at) {
            $validated['replied_at'] = now();
            $validated['replied_by'] = $request->user()?->id;
        }

        $inquiry->update($validated);

        return redirect()->back()->with('success', 'Inquiry status updated successfully.');
    }

    public function destroyInquiry(FrontendContactMessage $inquiry): RedirectResponse
    {
        $inquiry->delete();

        return redirect()->back()->with('success', 'Inquiry message deleted.');
    }

    // =========================================================================
    // GRADING SCALE MANAGEMENT
    // =========================================================================

    public function storeGrade(Request $request): RedirectResponse
    {
        if (! $request->user()?->can('school_settings.update')) {
            abort(403, 'Unauthorized to create grading scale rules.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:10'],
            'grade_point' => ['required', 'numeric', 'min:0', 'max:10'],
            'mark_from' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_to' => ['required', 'numeric', 'min:0', 'max:100'],
            'remark' => ['required', 'string', 'max:100'],
        ]);

        Grade::create($validated);

        return redirect()->back()->with('success', 'Grade band created successfully.');
    }

    public function updateGrade(Request $request, Grade $grade): RedirectResponse
    {
        if (! $request->user()?->can('school_settings.update')) {
            abort(403, 'Unauthorized to update grading scale rules.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:10'],
            'grade_point' => ['required', 'numeric', 'min:0', 'max:10'],
            'mark_from' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_to' => ['required', 'numeric', 'min:0', 'max:100'],
            'remark' => ['required', 'string', 'max:100'],
        ]);

        $grade->update($validated);

        return redirect()->back()->with('success', 'Grade band updated successfully.');
    }

    public function destroyGrade(Request $request, Grade $grade): RedirectResponse
    {
        if (! $request->user()?->can('school_settings.update')) {
            abort(403, 'Unauthorized to delete grading scale rules.');
        }

        $grade->delete();

        return redirect()->back()->with('success', 'Grade band removed.');
    }
}
