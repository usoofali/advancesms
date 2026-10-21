<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\FrontendAnnouncement;
use App\Models\FrontendFaq;
use App\Models\FrontendGallery;
use App\Models\FrontendSlider;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FrontendController extends Controller
{
    /**
     * Display the public institutional landing page.
     */
    public function index(): Response
    {
        $settings = SchoolSetting::current();

        $sliders = FrontendSlider::active()->ordered()->get();

        $pinned = FrontendAnnouncement::published()->pinned()->latest('published_at')->take(4)->get();
        if ($pinned->count() < 4) {
            $pinnedIds = $pinned->pluck('id')->all();
            $recent = FrontendAnnouncement::published()
                ->whereNotIn('id', $pinnedIds)
                ->latest('published_at')
                ->take(4 - $pinned->count())
                ->get();
            $announcements = $pinned->concat($recent);
        } else {
            $announcements = $pinned;
        }

        $stats = [
            'students' => Student::count(),
            'teachers' => Teacher::count(),
            'classes' => SchoolClass::count(),
            'subjects' => Subject::count(),
        ];

        $principal = [
            'name' => $settings->principal_name,
            'message' => $settings->principal_message,
            'photo_url' => $settings->principal_photo_url,
            'stamp_url' => $settings->stamp_url,
        ];

        $galleryPreview = FrontendGallery::active()->ordered()->take(6)->get();

        return Inertia::render('frontend/Home', [
            'sliders' => $sliders,
            'announcements' => $announcements,
            'stats' => $stats,
            'principal' => $principal,
            'galleryPreview' => $galleryPreview,
            'coreValues' => $settings->core_values ?? [],
        ]);
    }

    /**
     * Display the public About Us institutional history and leadership page.
     */
    public function about(): Response
    {
        $settings = SchoolSetting::current();
        $departments = Department::withCount('subjects')->get();

        return Inertia::render('frontend/About', [
            'settings' => $settings,
            'departments' => $departments,
        ]);
    }

    /**
     * Display the public dynamic Academics curriculum, classes, and prospectus page.
     */
    public function academics(): Response
    {
        $settings = SchoolSetting::current();

        $classes = SchoolClass::with(['sections', 'subjects.department'])
            ->orderBy('level_order')
            ->get();

        $departments = Department::withCount('subjects')
            ->with(['subjects' => fn ($query) => $query->select('id', 'name', 'code', 'department_id')])
            ->get();

        return Inertia::render('frontend/Academics', [
            'classes' => $classes,
            'departments' => $departments,
            'activeSession' => AcademicSession::current(),
            'activeTerm' => AcademicTerm::current(),
            'prospectusUrl' => $settings->prospectus_url,
        ]);
    }

    /**
     * Display the public campus life photo gallery with category filtering.
     */
    public function gallery(Request $request): Response
    {
        $category = $request->query('category');

        $query = FrontendGallery::active();
        if ($category && is_string($category)) {
            $query->where('category', $category);
        }

        $items = $query->ordered()->paginate(12)->withQueryString();
        $categories = FrontendGallery::active()->distinct()->pluck('category')->filter()->values();

        return Inertia::render('frontend/Gallery', [
            'items' => $items,
            'categories' => $categories,
            'selectedCategory' => $category,
        ]);
    }

    /**
     * Display the public frequently asked questions page grouped by category.
     */
    public function faq(): Response
    {
        $groupedFaqs = FrontendFaq::published()
            ->ordered()
            ->get()
            ->groupBy('category');

        return Inertia::render('frontend/Faq', [
            'faqGroups' => $groupedFaqs,
        ]);
    }
}
