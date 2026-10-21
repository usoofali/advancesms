<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of academic departments.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('departments.view')) {
            abort(403, 'Unauthorized to view academic departments.');
        }

        $departments = Department::query()
            ->withCount(['teachers', 'subjects'])
            ->orderBy('name')
            ->get();

        $stats = [
            'total_departments' => $departments->count(),
            'total_teachers_assigned' => Teacher::whereNotNull('department_id')->count(),
            'total_subjects_assigned' => Subject::whereNotNull('department_id')->count(),
        ];

        return Inertia::render('academic/departments/Index', [
            'departments' => $departments,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created academic department in storage.
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        /** @var array{name: string, description?: string|null} $validated */
        $validated = $request->validated();

        Department::create($validated);

        return redirect()->back()->with('success', "Department {$validated['name']} created successfully.");
    }

    /**
     * Update the specified academic department in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        /** @var array{name: string, description?: string|null} $validated */
        $validated = $request->validated();

        $department->update($validated);

        return redirect()->back()->with('success', "Department {$department->name} updated successfully.");
    }

    /**
     * Remove the specified academic department from storage.
     */
    public function destroy(Request $request, Department $department): RedirectResponse
    {
        if (! $request->user()?->can('departments.delete')) {
            abort(403, 'Unauthorized to delete academic departments.');
        }

        if (! $department->canBeDeleted()) {
            return redirect()->back()->with('error', 'Cannot delete this department because it contains assigned teachers or subjects.');
        }

        $departmentName = $department->name;
        $department->delete();

        return redirect()->back()->with('success', "Department {$departmentName} deleted successfully.");
    }
}
