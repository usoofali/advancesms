<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademicSessionRequest;
use App\Http\Requests\UpdateAcademicSessionRequest;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcademicSessionController extends Controller
{
    /**
     * Display a listing of academic sessions and their terms.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('academic_sessions.view')) {
            abort(403, 'Unauthorized to view academic sessions.');
        }

        $sessions = AcademicSession::query()
            ->with(['terms' => fn ($query) => $query->orderBy('term_number')])
            ->withCount(['enrollments', 'exams', 'teacherSubjectAssignments'])
            ->orderByDesc('start_date')
            ->get();

        $currentSession = AcademicSession::current();
        $currentTerm = AcademicTerm::current();

        $stats = [
            'total_sessions' => $sessions->count(),
            'total_terms' => AcademicTerm::count(),
            'active_session_name' => $currentSession !== null ? $currentSession->name : 'None Active',
            'active_term_name' => $currentTerm !== null ? "{$currentTerm->name} ({$currentTerm->academicSession->name})" : 'None Active',
        ];

        return Inertia::render('academic/sessions/Index', [
            'sessions' => $sessions,
            'currentSession' => $currentSession,
            'currentTerm' => $currentTerm,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created academic session.
     */
    public function store(StoreAcademicSessionRequest $request): RedirectResponse
    {
        /** @var array{name: string, start_date: string, end_date: string, is_current?: bool, generate_terms?: bool} $validated */
        $validated = $request->validated();

        $isCurrent = $validated['is_current'] ?? false;
        $generateTerms = $validated['generate_terms'] ?? false;

        DB::transaction(function () use ($validated, $isCurrent, $generateTerms) {
            $session = AcademicSession::create([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_current' => false,
            ]);

            if ($generateTerms) {
                $start = Carbon::parse($session->start_date);
                $end = Carbon::parse($session->end_date);
                $totalDays = $start->diffInDays($end);
                $termDays = (int) floor($totalDays / 3);

                $t1Start = $start->copy();
                $t1End = $t1Start->copy()->addDays(max(1, $termDays - 10));

                $t2Start = $t1End->copy()->addDays(10);
                $t2End = $t2Start->copy()->addDays(max(1, $termDays - 10));

                $t3Start = $t2End->copy()->addDays(10);
                $t3End = $end->copy();

                AcademicTerm::create([
                    'academic_session_id' => $session->id,
                    'name' => 'First Term',
                    'term_number' => 1,
                    'start_date' => $t1Start->toDateString(),
                    'end_date' => $t1End->toDateString(),
                    'is_current' => false,
                ]);

                AcademicTerm::create([
                    'academic_session_id' => $session->id,
                    'name' => 'Second Term',
                    'term_number' => 2,
                    'start_date' => $t2Start->toDateString(),
                    'end_date' => $t2End->toDateString(),
                    'is_current' => false,
                ]);

                AcademicTerm::create([
                    'academic_session_id' => $session->id,
                    'name' => 'Third Term',
                    'term_number' => 3,
                    'start_date' => $t3Start->toDateString(),
                    'end_date' => $t3End->toDateString(),
                    'is_current' => false,
                ]);
            }

            if ($isCurrent) {
                $session->makeCurrent();
            }
        });

        return redirect()->back()->with('success', "Academic session {$validated['name']} created successfully.");
    }

    /**
     * Update the specified academic session.
     */
    public function update(UpdateAcademicSessionRequest $request, AcademicSession $session): RedirectResponse
    {
        /** @var array{name: string, start_date: string, end_date: string} $validated */
        $validated = $request->validated();

        $session->update($validated);

        return redirect()->back()->with('success', "Academic session {$session->name} updated successfully.");
    }

    /**
     * Remove the specified academic session from storage.
     */
    public function destroy(Request $request, AcademicSession $session): RedirectResponse
    {
        if (! $request->user()?->can('academic_sessions.delete')) {
            abort(403, 'Unauthorized to delete academic sessions.');
        }

        if ($session->is_current) {
            return redirect()->back()->with('error', 'Cannot delete the currently active academic session. Switch to another active session first.');
        }

        if (! $session->canBeDeleted()) {
            return redirect()->back()->with('error', 'Cannot delete this academic session because it contains existing student enrollments, exams, or staff assignments.');
        }

        $sessionName = $session->name;
        $session->delete();

        return redirect()->back()->with('success', "Academic session {$sessionName} deleted successfully.");
    }

    /**
     * Set the specified academic session as the currently active session.
     */
    public function setCurrent(Request $request, AcademicSession $session): RedirectResponse
    {
        if (! $request->user()?->can('academic_sessions.set_current')) {
            abort(403, 'Unauthorized to set active academic session.');
        }

        $session->makeCurrent();

        return redirect()->back()->with('success', "Academic session {$session->name} is now set as the active session.");
    }
}
