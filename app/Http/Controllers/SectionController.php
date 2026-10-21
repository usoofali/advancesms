<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Store a newly created section in storage.
     */
    public function store(StoreSectionRequest $request): RedirectResponse
    {
        /** @var array{class_id: int, name: string, capacity: int} $validated */
        $validated = $request->validated();

        $section = Section::create($validated);

        return redirect()->back()->with('success', "Section '{$section->full_name}' created successfully.");
    }

    /**
     * Update the specified section in storage.
     */
    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        /** @var array{name: string, capacity: int} $validated */
        $validated = $request->validated();

        $section->update($validated);

        return redirect()->back()->with('success', "Section '{$section->full_name}' updated successfully.");
    }

    /**
     * Remove the specified section from storage.
     */
    public function destroy(Request $request, Section $section): RedirectResponse
    {
        if (! $request->user()?->can('sections.delete')) {
            abort(403, 'Unauthorized to delete class sections.');
        }

        if (! $section->canBeDeleted()) {
            return redirect()->back()->with('error', 'Cannot delete this section because it contains active student enrollments or teacher subject assignments.');
        }

        $fullName = $section->full_name;
        $section->delete();

        return redirect()->back()->with('success', "Section '{$fullName}' deleted successfully.");
    }
}
