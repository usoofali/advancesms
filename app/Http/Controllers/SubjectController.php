<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    /**
     * Display a listing of subjects and class curriculum allocations.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('subjects.view')) {
            abort(403, 'Unauthorized to view master subjects catalog.');
        }

        $subjects = Subject::query()
            ->with(['department:id,name'])
            ->withCount(['classes', 'teacherSubjectAssignments', 'studentMarks'])
            ->orderBy('code')
            ->get();

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $classes = SchoolClass::query()
            ->with(['subjects' => function ($query) {
                $query->withPivot('id', 'is_compulsory')
                    ->with('department:id,name')
                    ->orderBy('code');
            }])
            ->orderBy('level_order')
            ->get();

        $stats = [
            'total_subjects' => $subjects->count(),
            'assigned_subjects' => $subjects->where('classes_count', '>', 0)->count(),
            'unassigned_subjects' => $subjects->where('classes_count', 0)->count(),
            'total_curriculum_allocations' => ClassSubject::count(),
        ];

        return Inertia::render('academic/subjects/Index', [
            'subjects' => $subjects,
            'departments' => $departments,
            'classes' => $classes,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created subject in the catalog.
     */
    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        /** @var array{code: string, name: string, department_id?: int|null} $validated */
        $validated = $request->validated();

        $subject = Subject::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'department_id' => $validated['department_id'] ?? null,
        ]);

        return redirect()->back()->with('success', "Subject '{$subject->name}' ({$subject->code}) added to catalog successfully.");
    }

    /**
     * Update the specified subject in the catalog.
     */
    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        /** @var array{code: string, name: string, department_id?: int|null} $validated */
        $validated = $request->validated();

        $subject->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'department_id' => $validated['department_id'] ?? null,
        ]);

        return redirect()->back()->with('success', "Subject '{$subject->name}' updated successfully.");
    }

    /**
     * Remove the specified subject from storage.
     */
    public function destroy(Request $request, Subject $subject): RedirectResponse
    {
        if (! $request->user()?->can('subjects.delete')) {
            abort(403, 'Unauthorized to delete subjects.');
        }

        if ($subject->classes()->exists()) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete '{$subject->name}' ({$subject->code}) because it is allocated to {$subject->classes()->count()} class(es). Remove it from all classes first.",
            ]);
        }

        if ($subject->teacherSubjectAssignments()->exists()) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete '{$subject->name}' because teachers are currently assigned to teach it.",
            ]);
        }

        if ($subject->studentMarks()->exists()) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete '{$subject->name}' because student examination/assessment marks are recorded for it.",
            ]);
        }

        $name = $subject->name;
        $subject->delete();

        return redirect()->back()->with('success', "Subject '{$name}' deleted successfully.");
    }
}
