<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeacherSubjectAssignmentRequest;
use App\Http\Requests\UpdateTeacherSubjectAssignmentRequest;
use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubjectAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherSubjectAssignmentController extends Controller
{
    /**
     * Display a listing of teacher subject allocations.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('teacher_subject_assignments.view')) {
            abort(403, 'Unauthorized to view teacher subject allocations.');
        }

        $sessions = AcademicSession::query()
            ->with(['terms:id,academic_session_id,name,is_current'])
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_current']);

        $activeSession = $sessions->firstWhere('is_current', true) ?? $sessions->first();
        $defaultSessionId = $activeSession ? $activeSession->id : 0;
        $selectedSessionId = (int) $request->input('session_id', $defaultSessionId);

        $selectedSession = $sessions->firstWhere('id', $selectedSessionId) ?? $activeSession;

        $terms = $selectedSession ? $selectedSession->terms : collect();
        $activeTerm = $terms->firstWhere('is_current', true) ?? $terms->first();
        $defaultTermId = $activeTerm ? $activeTerm->id : 0;
        $selectedTermId = (int) $request->input('term_id', $defaultTermId);

        $assignments = TeacherSubjectAssignment::query()
            ->where('academic_session_id', $selectedSessionId)
            ->where('academic_term_id', $selectedTermId)
            ->with([
                'teacher:id,user_id,staff_id,department_id,designation',
                'teacher.user:id,name,phone,email',
                'teacher.department:id,name',
                'schoolClass:id,name,stage',
                'section:id,name,capacity',
                'subject:id,name,code,department_id',
                'subject.department:id,name',
                'academicSession:id,name,is_current',
                'academicTerm:id,name,is_current',
            ])
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->get();

        $classes = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order')
            ->get(['id', 'name', 'stage']);

        $teachers = Teacher::query()
            ->with(['user:id,name,phone,email', 'department:id,name'])
            ->orderBy('staff_id')
            ->get(['id', 'user_id', 'staff_id', 'department_id', 'designation']);

        $subjects = Subject::query()
            ->with(['department:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'department_id']);

        $classSubjects = ClassSubject::query()
            ->get(['id', 'class_id', 'subject_id', 'is_compulsory']);

        $stats = [
            'total_allocations' => $assignments->count(),
            'assigned_teachers_count' => $assignments->pluck('teacher_id')->unique()->count(),
            'can_input_marks_count' => $assignments->where('can_input_marks', true)->count(),
            'can_take_attendance_count' => $assignments->where('can_take_attendance', true)->count(),
        ];

        return Inertia::render('academic/allocations/Index', [
            'assignments' => $assignments,
            'sessions' => $sessions,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId,
            'classes' => $classes,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'classSubjects' => $classSubjects,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created teacher subject assignment in storage.
     */
    public function store(StoreTeacherSubjectAssignmentRequest $request): RedirectResponse
    {
        /** @var array{academic_session_id: int, academic_term_id: int, teacher_id: int, class_id: int, section_id: int, subject_id: int, can_input_marks?: bool|null, can_take_attendance?: bool|null} $validated */
        $validated = $request->validated();

        $assignment = TeacherSubjectAssignment::create([
            'academic_session_id' => $validated['academic_session_id'],
            'academic_term_id' => $validated['academic_term_id'],
            'teacher_id' => $validated['teacher_id'],
            'class_id' => $validated['class_id'],
            'section_id' => $validated['section_id'],
            'subject_id' => $validated['subject_id'],
            'can_input_marks' => $validated['can_input_marks'] ?? true,
            'can_take_attendance' => $validated['can_take_attendance'] ?? false,
        ]);

        $teacherName = $assignment->teacher->user->name;
        $subjectName = $assignment->subject->name;
        $className = $assignment->schoolClass->name;
        $sectionName = $assignment->section->name;

        return redirect()->back()->with('success', "Assigned '{$teacherName}' to {$subjectName} ({$className} - Section {$sectionName}) successfully.");
    }

    /**
     * Update the specified assignment in storage.
     */
    public function update(UpdateTeacherSubjectAssignmentRequest $request, TeacherSubjectAssignment $assignment): RedirectResponse
    {
        /** @var array{teacher_id: int, can_input_marks?: bool|null, can_take_attendance?: bool|null} $validated */
        $validated = $request->validated();

        $assignment->update([
            'teacher_id' => $validated['teacher_id'],
            'can_input_marks' => $validated['can_input_marks'] ?? true,
            'can_take_attendance' => $validated['can_take_attendance'] ?? false,
        ]);

        return redirect()->back()->with('success', 'Teaching allocation and permissions updated successfully.');
    }

    /**
     * Remove the specified assignment from storage.
     */
    public function destroy(Request $request, TeacherSubjectAssignment $assignment): RedirectResponse
    {
        if (! $request->user()?->can('teacher_subject_assignments.delete')) {
            abort(403, 'Unauthorized to remove teaching allocation.');
        }

        $subjectName = $assignment->subject->name;
        $assignment->delete();

        return redirect()->back()->with('success', "Teaching allocation for {$subjectName} removed successfully.");
    }
}
