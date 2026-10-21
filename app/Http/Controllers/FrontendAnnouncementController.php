<?php

namespace App\Http\Controllers;

use App\Models\FrontendAnnouncement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FrontendAnnouncementController extends Controller
{
    /**
     * Display a listing of public institutional announcements.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $category = $request->query('category');

        $query = FrontendAnnouncement::published();

        if ($search && is_string($search)) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($category && is_string($category) && $category !== 'all') {
            $query->where('category', $category);
        }

        $announcements = $query->orderBy('is_pinned', 'desc')
            ->orderBy('published_at', 'desc')
            ->paginate(9)
            ->withQueryString();

        $categories = FrontendAnnouncement::published()
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        $pinned = FrontendAnnouncement::published()
            ->pinned()
            ->latest('published_at')
            ->take(3)
            ->get();

        return Inertia::render('frontend/announcements/Index', [
            'announcements' => $announcements,
            'categories' => $categories,
            'pinned' => $pinned,
            'filters' => [
                'search' => $search ?? '',
                'category' => $category ?? 'all',
            ],
        ]);
    }

    /**
     * Display the specified public announcement circular.
     */
    public function show(string $slug): Response
    {
        $announcement = FrontendAnnouncement::with('author:id,name,username')
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $announcement->is_published) {
            abort(404, 'Announcement not found or unpublished.');
        }

        $recentAnnouncements = FrontendAnnouncement::published()
            ->where('id', '!=', $announcement->id)
            ->latest('published_at')
            ->take(3)
            ->get();

        return Inertia::render('frontend/announcements/Show', [
            'announcement' => $announcement,
            'recentAnnouncements' => $recentAnnouncements,
        ]);
    }
}
