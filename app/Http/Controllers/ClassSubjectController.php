<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAssignClassSubjectsRequest;
use App\Http\Requests\StoreClassSubjectRequest;
use App\Http\Requests\UpdateClassSubjectRequest;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\StudentMark;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassSubjectController extends Controller
{
    /**
     * Store a newly allocated subject for a class.
     */
    public function store(StoreClassSubjectRequest $request): RedirectResponse
    {
        /** @var array{class_id: int, subject_id: int, is_compulsory?: bool} $validated */
        $validated = $request->validated();

        $classSubject = ClassSubject::create([
            'class_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'is_compulsory' => $validated['is_compulsory'] ?? true,
        ]);

        $subjectName = $classSubject->subject->name;
        $className = $classSubject->schoolClass->name;

        return redirect()->back()->with('success', "'{$subjectName}' allocated to {$className} successfully.");
    }

    /**
     * Update the compulsory/elective setting for a class subject.
     */
    public function update(UpdateClassSubjectRequest $request, ClassSubject $classSubject): RedirectResponse
    {
        /** @var array{is_compulsory: bool} $validated */
        $validated = $request->validated();

        $classSubject->update([
            'is_compulsory' => $validated['is_compulsory'],
        ]);

        $status = $classSubject->is_compulsory ? 'Compulsory' : 'Elective';
        $subjectName = $classSubject->subject->name;

        return redirect()->back()->with('success', "'{$subjectName}' status updated to {$status}.");
    }

    /**
     * Remove the allocated subject from a class curriculum.
     */
    public function destroy(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        if (! $request->user()?->can('class_subjects.delete')) {
            abort(403, 'Unauthorized to remove subjects from class.');
        }

        $activeSession = AcademicSession::where('is_current', true)->first()
            ?? AcademicSession::latest('id')->first();
        $activeSessionId = $activeSession?->id;

        $activeTerm = AcademicTerm::where('academic_session_id', $activeSessionId)
            ->where('is_current', true)
            ->first();
        $activeTermId = $activeTerm?->id;

        // Check if teachers are assigned to teach this subject in this class for the active term
        $hasCurrentTeacherAssignments = TeacherSubjectAssignment::query()
            ->where('class_id', $classSubject->class_id)
            ->where('subject_id', $classSubject->subject_id)
            ->when($activeSessionId, fn ($q) => $q->where('academic_session_id', $activeSessionId))
            ->when($activeTermId, fn ($q) => $q->where('academic_term_id', $activeTermId))
            ->exists();

        if ($hasCurrentTeacherAssignments) {
            $termLabel = $activeTerm ? "in {$activeTerm->name}" : 'for the active term';

            return redirect()->back()->withErrors([
                'error' => "Cannot remove this subject because teachers are currently assigned to teach it in this class {$termLabel}. Remove teacher allocations first.",
            ]);
        }

        // Check if student marks are recorded for this subject in this class for the active term
        $currentExam = null;
        if ($activeSessionId && $activeTermId) {
            $currentExam = Exam::where('academic_session_id', $activeSessionId)
                ->where('academic_term_id', $activeTermId)
                ->first();
        }

        $hasCurrentStudentMarks = false;
        if ($currentExam) {
            $hasCurrentStudentMarks = StudentMark::query()
                ->where('subject_id', $classSubject->subject_id)
                ->where('exam_id', $currentExam->id)
                ->whereHas('enrollment', fn ($q) => $q->where('class_id', $classSubject->class_id))
                ->exists();
        } elseif ($activeSessionId) {
            $hasCurrentStudentMarks = StudentMark::query()
                ->where('subject_id', $classSubject->subject_id)
                ->whereHas('enrollment', fn ($q) => $q->where('class_id', $classSubject->class_id)->where('academic_session_id', $activeSessionId))
                ->exists();
        }

        if ($hasCurrentStudentMarks) {
            $termLabel = $activeTerm ? "in {$activeTerm->name}" : 'in the current term';

            return redirect()->back()->withErrors([
                'error' => "Cannot remove this subject because student assessment scores are already recorded for it {$termLabel}. Clear marks for the active term first if you wish to remove it.",
            ]);
        }

        $subjectName = $classSubject->subject->name;
        $className = $classSubject->schoolClass->name;

        $classSubject->delete();

        return redirect()->back()->with('success', "'{$subjectName}' removed from {$className}.");
    }

    /**
     * Bulk allocate multiple subjects to a class.
     */
    public function bulkAssign(BulkAssignClassSubjectsRequest $request): RedirectResponse
    {
        /** @var array{class_id: int, subject_ids: int[], is_compulsory?: bool} $validated */
        $validated = $request->validated();

        $class = SchoolClass::findOrFail($validated['class_id']);
        $subjectIds = $validated['subject_ids'];
        $isCompulsory = $validated['is_compulsory'] ?? true;

        DB::transaction(function () use ($class, $subjectIds, $isCompulsory) {
            $syncData = [];
            foreach ($subjectIds as $subjectId) {
                $syncData[$subjectId] = ['is_compulsory' => $isCompulsory];
            }
            $class->subjects()->syncWithoutDetaching($syncData);
        });

        $count = count($subjectIds);

        return redirect()->back()->with('success', "{$count} subject(s) allocated to {$class->name}.");
    }
}
