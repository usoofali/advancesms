<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademicTermRequest;
use App\Http\Requests\UpdateAcademicTermRequest;
use App\Models\AcademicTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicTermController extends Controller
{
    /**
     * Store a newly created academic term.
     */
    public function store(StoreAcademicTermRequest $request): RedirectResponse
    {
        /** @var array{academic_session_id: int, name: string, term_number: int, start_date: string, end_date: string, is_current?: bool} $validated */
        $validated = $request->validated();

        $isCurrent = $validated['is_current'] ?? false;

        DB::transaction(function () use ($validated, $isCurrent) {
            $term = AcademicTerm::create([
                'academic_session_id' => $validated['academic_session_id'],
                'name' => $validated['name'],
                'term_number' => $validated['term_number'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_current' => false,
            ]);

            if ($isCurrent) {
                $term->makeCurrent();
            }
        });

        return redirect()->back()->with('success', "Academic term {$validated['name']} created successfully.");
    }

    /**
     * Update the specified academic term.
     */
    public function update(UpdateAcademicTermRequest $request, AcademicTerm $term): RedirectResponse
    {
        /** @var array{name: string, term_number: int, start_date: string, end_date: string} $validated */
        $validated = $request->validated();

        $term->update($validated);

        return redirect()->back()->with('success', "Academic term {$term->name} updated successfully.");
    }

    /**
     * Remove the specified academic term from storage.
     */
    public function destroy(Request $request, AcademicTerm $term): RedirectResponse
    {
        if (! $request->user()?->can('academic_terms.delete')) {
            abort(403, 'Unauthorized to delete academic terms.');
        }

        if ($term->is_current) {
            return redirect()->back()->with('error', 'Cannot delete the currently active academic term. Switch to another active term first.');
        }

        if (! $term->canBeDeleted()) {
            return redirect()->back()->with('error', 'Cannot delete this academic term because it contains existing exams or teacher subject assignments.');
        }

        $termName = $term->name;
        $term->delete();

        return redirect()->back()->with('success', "Academic term {$termName} deleted successfully.");
    }

    /**
     * Set the specified academic term as the currently active term.
     */
    public function setCurrent(Request $request, AcademicTerm $term): RedirectResponse
    {
        if (! $request->user()?->can('academic_terms.set_current')) {
            abort(403, 'Unauthorized to set active academic term.');
        }

        $term->makeCurrent();

        return redirect()->back()->with('success', "{$term->name} ({$term->academicSession->name}) is now set as the active term.");
    }
}
