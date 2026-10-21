<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\BatchSaveStudentMarksRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\StudentMark;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubjectAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentMarkController extends Controller
{
    /**
     * Display the continuous assessment and exam mark entry grid.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user?->can('student_marks.enter') && ! $user?->can('student_marks.update')) {
            abort(403, 'Unauthorized to access academic marks entry.');
        }

        // Sessions & Terms
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

        $schoolSetting = SchoolSetting::current();
        $caCount = $schoolSetting->ca_count ?? 3;
        $maxCa1 = (float) ($schoolSetting->default_max_ca1 ?? 10.00);
        $maxCa2 = $caCount >= 2 ? (float) ($schoolSetting->default_max_ca2 ?? 10.00) : 0.00;
        $maxCa3 = $caCount >= 3 ? (float) ($schoolSetting->default_max_ca3 ?? 10.00) : 0.00;
        $maxExam = (float) ($schoolSetting->default_max_exam ?? 70.00);

        // Ensure Exam Milestone exists for this session & term
        $exam = Exam::firstOrCreate(
            [
                'academic_session_id' => $selectedSessionId,
                'academic_term_id' => $selectedTermId,
            ],
            [
                'name' => ($activeTerm ? $activeTerm->name : 'Terminal').' Examination '.($selectedSession ? $selectedSession->name : ''),
                'ca_count' => $caCount,
                'max_ca1' => $maxCa1,
                'max_ca2' => $maxCa2,
                'max_ca3' => $maxCa3,
                'max_exam' => $maxExam,
                'ca1_label' => $schoolSetting->ca1_label ?? '1st CA',
                'ca2_label' => $caCount >= 2 ? ($schoolSetting->ca2_label ?? '2nd CA') : null,
                'ca3_label' => $caCount >= 3 ? ($schoolSetting->ca3_label ?? '3rd CA') : null,
                'is_published' => false,
            ]
        );

        // Ensure unpublished exam milestones always reflect current institutional CA settings
        if (! $exam->is_published) {
            $exam->update([
                'ca_count' => $caCount,
                'max_ca1' => $maxCa1,
                'max_ca2' => $maxCa2,
                'max_ca3' => $maxCa3,
                'max_exam' => $maxExam,
                'ca1_label' => $schoolSetting->ca1_label ?? '1st CA',
                'ca2_label' => $caCount >= 2 ? ($schoolSetting->ca2_label ?? '2nd CA') : null,
                'ca3_label' => $caCount >= 3 ? ($schoolSetting->ca3_label ?? '3rd CA') : null,
            ]);
            $exam->refresh();
        }

        // Determine if user is a teacher restricted to assigned classes/subjects
        $isTeacher = $user->hasRole('teacher') && ! $user->hasAnyRole(['superadmin', 'admin', 'exam_officer']);
        $teacher = $isTeacher ? Teacher::where('user_id', $user->id)->first() : null;

        // Load Classes and Sections
        $classesQuery = SchoolClass::query()->with(['sections:id,class_id,name,capacity'])->orderBy('level_order');

        if ($isTeacher && $teacher) {
            $assignedClassIds = TeacherSubjectAssignment::where('teacher_id', $teacher->id)
                ->where('academic_session_id', $selectedSessionId)
                ->where('academic_term_id', $selectedTermId)
                ->pluck('class_id')
                ->unique();

            $classesQuery->whereIn('id', $assignedClassIds);
        }

        $classes = $classesQuery->get(['id', 'name', 'stage']);

        $firstClass = $classes->first();
        $selectedClassId = (int) $request->input('class_id', $firstClass ? $firstClass->id : 0);

        $selectedClass = $classes->firstWhere('id', $selectedClassId) ?? $firstClass;
        $sections = $selectedClass ? $selectedClass->sections : collect();

        if ($isTeacher && $teacher && $selectedClass) {
            $assignedSectionIds = TeacherSubjectAssignment::where('teacher_id', $teacher->id)
                ->where('academic_session_id', $selectedSessionId)
                ->where('academic_term_id', $selectedTermId)
                ->where('class_id', $selectedClass->id)
                ->pluck('section_id')
                ->unique();

            $sections = $sections->whereIn('id', $assignedSectionIds)->values();
        }

        $firstSection = $sections->first();

        $requestedSectionId = (int) $request->input('section_id', 0);
        if ($requestedSectionId && $sections->contains('id', $requestedSectionId)) {
            $selectedSectionId = $requestedSectionId;
        } else {
            $selectedSectionId = $firstSection ? $firstSection->id : 0;
        }

        // Load Subjects (filtered by curriculum allocation to selected class)
        $subjectsQuery = Subject::query()->orderBy('name');

        if ($selectedClassId) {
            $subjectsQuery->whereHas('classSubjects', function ($q) use ($selectedClassId) {
                $q->where('class_id', $selectedClassId);
            });
        }

        if ($isTeacher && $teacher) {
            $assignedSubjectIds = TeacherSubjectAssignment::where('teacher_id', $teacher->id)
                ->where('academic_session_id', $selectedSessionId)
                ->where('academic_term_id', $selectedTermId)
                ->where('class_id', $selectedClassId)
                ->where('section_id', $selectedSectionId)
                ->pluck('subject_id')
                ->unique();

            $subjectsQuery->whereIn('id', $assignedSubjectIds);
        }

        $subjects = $subjectsQuery->get(['id', 'name', 'code', 'department_id']);
        $firstSubject = $subjects->first();

        $requestedSubjectId = (int) $request->input('subject_id', 0);
        if ($requestedSubjectId && $subjects->contains('id', $requestedSubjectId)) {
            $selectedSubjectId = $requestedSubjectId;
        } else {
            $selectedSubjectId = $firstSubject ? $firstSubject->id : 0;
        }

        // Load Enrolled Students and Marks
        $gridRows = [];
        if ($selectedSessionId && $selectedClassId && $selectedSectionId && $selectedSubjectId) {
            $enrollments = Enrollment::query()
                ->where('academic_session_id', $selectedSessionId)
                ->where('class_id', $selectedClassId)
                ->where('section_id', $selectedSectionId)
                ->whereIn('status', [EnrollmentStatus::ACTIVE, EnrollmentStatus::PROMOTED, EnrollmentStatus::REPEATED])
                ->with(['student:id,user_id,admission_number', 'student.user:id,name,gender'])
                ->orderBy('roll_number')
                ->orderBy('id')
                ->get();

            $existingMarks = StudentMark::query()
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->where('subject_id', $selectedSubjectId)
                ->where('exam_id', $exam->id)
                ->with('grade:id,name,remark')
                ->get()
                ->keyBy('enrollment_id');

            foreach ($enrollments as $enrollment) {
                $mark = $existingMarks->get($enrollment->id);

                $ca1 = $mark ? (float) $mark->ca1 : null;
                $ca2 = $mark ? (float) $mark->ca2 : null;
                $ca3 = $mark ? (float) $mark->ca3 : null;
                $examScore = $mark ? (float) $mark->exam_score : null;

                $total = ($ca1 ?? 0) + ($ca2 ?? 0) + ($ca3 ?? 0) + ($examScore ?? 0);
                $gradeName = $mark && $mark->grade ? $mark->grade->name : null;

                $defaultRemark = $mark && $mark->grade ? $mark->grade->remark : (Grade::forScore($total)?->remark ?? '');
                $teacherComment = $mark && ! empty($mark->teacher_comment) ? $mark->teacher_comment : ($total > 0 ? $defaultRemark : '');

                $gridRows[] = [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'admission_number' => $enrollment->student->admission_number,
                    'student_name' => $enrollment->student->user->name,
                    'gender' => $enrollment->student->user->gender,
                    'roll_number' => $enrollment->roll_number,
                    'mark_id' => $mark ? $mark->id : null,
                    'ca1' => $ca1,
                    'ca2' => $ca2,
                    'ca3' => $ca3,
                    'exam_score' => $examScore,
                    'total_score' => $total,
                    'grade_name' => $gradeName,
                    'teacher_comment' => $teacherComment,
                ];
            }
        }

        // Grades catalog for dynamic client-side grade resolution
        $grades = Grade::query()
            ->orderByDesc('mark_from')
            ->get(['id', 'name', 'grade_point', 'mark_from', 'mark_to', 'remark']);

        return Inertia::render('academic/marks/Index', [
            'sessions' => $sessions,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId,
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'sections' => $sections,
            'selectedSectionId' => $selectedSectionId,
            'subjects' => $subjects,
            'selectedSubjectId' => $selectedSubjectId,
            'exam' => $exam,
            'gridRows' => $gridRows,
            'grades' => $grades,
        ]);
    }

    /**
     * Batch save continuous assessment and examination scores.
     */
    public function batchSave(BatchSaveStudentMarksRequest $request): JsonResponse|RedirectResponse
    {
        /** @var array{exam_id: int, subject_id: int, marks: array<array{enrollment_id: int, ca1?: float|null, ca2?: float|null, ca3?: float|null, exam_score?: float|null, teacher_comment?: string|null}>} $validated */
        $validated = $request->validated();

        $examId = $validated['exam_id'];
        $subjectId = $validated['subject_id'];
        $marksData = $validated['marks'];

        $user = $request->user();
        $isTeacher = $user?->hasRole('teacher') && ! $user->hasAnyRole(['superadmin', 'admin', 'exam_officer']);

        if ($isTeacher) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if (! $teacher) {
                abort(403, 'Unauthorized. No teacher profile linked to your user account.');
            }

            $exam = Exam::findOrFail($examId);
            $enrollmentIds = collect($marksData)->pluck('enrollment_id')->unique();
            $enrollments = Enrollment::whereIn('id', $enrollmentIds)->get(['id', 'class_id', 'section_id']);

            foreach ($enrollments as $enrollment) {
                $hasAllocation = TeacherSubjectAssignment::where('teacher_id', $teacher->id)
                    ->where('academic_session_id', $exam->academic_session_id)
                    ->where('academic_term_id', $exam->academic_term_id)
                    ->where('class_id', $enrollment->class_id)
                    ->where('section_id', $enrollment->section_id)
                    ->where('subject_id', $subjectId)
                    ->exists();

                if (! $hasAllocation) {
                    abort(403, 'Unauthorized. You are not allocated to enter marks for this subject in the specified class section.');
                }
            }
        }

        DB::transaction(function () use ($examId, $subjectId, $marksData) {
            $exam = Exam::find($examId);
            $caCount = $exam?->ca_count ?? SchoolSetting::current()->ca_count ?? 3;

            foreach ($marksData as $entry) {
                $ca1 = isset($entry['ca1']) && $entry['ca1'] !== '' ? (float) $entry['ca1'] : null;
                $ca2 = ($caCount >= 2 && isset($entry['ca2']) && $entry['ca2'] !== '') ? (float) $entry['ca2'] : null;
                $ca3 = ($caCount >= 3 && isset($entry['ca3']) && $entry['ca3'] !== '') ? (float) $entry['ca3'] : null;
                $examScore = isset($entry['exam_score']) && $entry['exam_score'] !== '' ? (float) $entry['exam_score'] : null;

                $computedTotal = ($ca1 ?? 0.0) + ($ca2 ?? 0.0) + ($ca3 ?? 0.0) + ($examScore ?? 0.0);
                $matchedGrade = Grade::forScore($computedTotal);

                StudentMark::updateOrCreate(
                    [
                        'enrollment_id' => $entry['enrollment_id'],
                        'subject_id' => $subjectId,
                        'exam_id' => $examId,
                    ],
                    [
                        'ca1' => $ca1,
                        'ca2' => $ca2,
                        'ca3' => $ca3,
                        'exam_score' => $examScore,
                        'grade_id' => $matchedGrade?->id,
                        'teacher_comment' => $entry['teacher_comment'] ?? null,
                    ]
                );
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Assessment marks autosaved successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Continuous assessment & exam marks saved successfully.');
    }
}
