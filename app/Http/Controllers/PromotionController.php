<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\PromoteStudentsRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    /**
     * Display the student promotion and progression management page.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('enrollments.promote')) {
            abort(403, 'Unauthorized to manage student promotions.');
        }

        $sessions = AcademicSession::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_current']);

        $activeSession = $sessions->firstWhere('is_current', true) ?? $sessions->first();
        $defaultSessionId = $activeSession ? $activeSession->id : 0;
        $selectedSessionId = (int) $request->input('session_id', $defaultSessionId);

        $classes = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order')
            ->get(['id', 'name', 'stage', 'level_order']);

        $firstClass = $classes->first();
        $selectedClassId = (int) $request->input('class_id', $firstClass ? $firstClass->id : 0);
        $selectedSectionId = (int) $request->input('section_id', 0);

        // Whole-session statistics via high-speed conditional SQL aggregation
        $statsRaw = Enrollment::where('academic_session_id', $selectedSessionId)
            ->selectRaw("
                COUNT(*) as total_in_session,
                SUM(status = 'active') as active_count,
                SUM(status = 'promoted') as promoted_count,
                SUM(status = 'repeated') as repeated_count,
                SUM(status = 'graduated') as graduated_count
            ")
            ->first();

        $stats = [
            'total_in_session' => (int) ($statsRaw->total_in_session ?? 0),
            'active_count' => (int) ($statsRaw->active_count ?? 0),
            'promoted_count' => (int) ($statsRaw->promoted_count ?? 0),
            'repeated_count' => (int) ($statsRaw->repeated_count ?? 0),
            'graduated_count' => (int) ($statsRaw->graduated_count ?? 0),
        ];

        // Fetch candidate enrollments scoped to selected class & section
        $enrollmentsQuery = Enrollment::query()
            ->where('academic_session_id', $selectedSessionId)
            ->where('class_id', $selectedClassId)
            ->with([
                'student:id,user_id,admission_number',
                'student.user:id,name',
                'student.enrollments' => fn ($query) => $query->with([
                    'schoolClass:id,name,stage',
                    'section:id,name',
                    'academicSession:id,name,is_current',
                ]),
                'schoolClass:id,name,stage',
                'section:id,name',
                'academicSession:id,name,is_current',
                'previousEnrollment.schoolClass:id,name',
                'previousEnrollment.section:id,name',
                'previousEnrollment.academicSession:id,name',
            ])
            ->orderBy('section_id')
            ->orderBy('roll_number');

        if ($selectedSectionId > 0) {
            $enrollmentsQuery->where('section_id', $selectedSectionId);
        }

        $enrollments = $enrollmentsQuery->get();

        // High-speed MySQL aggregation for terminal performance metrics (Term 1, Term 2, Term 3 & Annual Cumulative)
        $studentPerformanceMap = [];

        if ($enrollments->isNotEmpty()) {
            $performanceQuery = DB::table('enrollments as e')
                ->join('student_marks as sm', 'sm.enrollment_id', '=', 'e.id')
                ->join('exams as ex', 'sm.exam_id', '=', 'ex.id')
                ->join('academic_terms as t', 'ex.academic_term_id', '=', 't.id')
                ->where('e.academic_session_id', $selectedSessionId)
                ->where('e.class_id', $selectedClassId);

            if ($selectedSectionId > 0) {
                $performanceQuery->where('e.section_id', $selectedSectionId);
            }

            $termAverages = $performanceQuery
                ->select([
                    'e.id as enrollment_id',
                    'e.student_id',
                    't.term_number',
                    DB::raw('ROUND(AVG(sm.total_score), 1) as term_avg'),
                    DB::raw('COUNT(sm.id) as subjects_count'),
                ])
                ->groupBy('e.id', 'e.student_id', 't.term_number')
                ->get()
                ->groupBy('student_id');

            $allGrades = Grade::orderBy('mark_from', 'desc')->get();

            foreach ($enrollments as $enrollment) {
                $terms = $termAverages->get($enrollment->student_id, collect());

                $term1Avg = null;
                $term2Avg = null;
                $term3Avg = null;
                $validTermAvgs = [];

                foreach ($terms as $t) {
                    $avgVal = (float) $t->term_avg;
                    if ($t->term_number == 1) {
                        $term1Avg = $avgVal;
                        $validTermAvgs[] = $avgVal;
                    } elseif ($t->term_number == 2) {
                        $term2Avg = $avgVal;
                        $validTermAvgs[] = $avgVal;
                    } elseif ($t->term_number == 3) {
                        $term3Avg = $avgVal;
                        $validTermAvgs[] = $avgVal;
                    }
                }

                $annualAvg = ! empty($validTermAvgs)
                    ? round(array_sum($validTermAvgs) / count($validTermAvgs), 1)
                    : null;

                $gradeLetter = null;
                if ($annualAvg !== null) {
                    $matchedGrade = $allGrades->first(fn ($g) => $annualAvg >= (float) $g->mark_from && $annualAvg <= (float) $g->mark_to);
                    $gradeLetter = $matchedGrade ? $matchedGrade->name : ($annualAvg >= 50 ? 'P' : 'F');
                }

                $studentPerformanceMap[$enrollment->student_id] = [
                    'term1_avg' => $term1Avg,
                    'term2_avg' => $term2Avg,
                    'term3_avg' => $term3Avg,
                    'annual_avg' => $annualAvg,
                    'grade' => $gradeLetter,
                    'terms_sat_count' => count($validTermAvgs),
                ];
            }
        }

        return Inertia::render('academic/promotions/Index', [
            'sessions' => $sessions,
            'selectedSessionId' => $selectedSessionId,
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedSectionId' => $selectedSectionId,
            'enrollments' => $enrollments,
            'studentPerformanceMap' => $studentPerformanceMap,
            'stats' => $stats,
        ]);
    }

    /**
     * Execute an administrative, management-initiated batch progression / promotion.
     */
    public function store(PromoteStudentsRequest $request): RedirectResponse
    {
        /** @var array{source_session_id: int, target_session_id: int, action: string, target_class_id?: int|null, target_section_id?: int|null, student_ids: int[]} $validated */
        $validated = $request->validated();

        $sourceSessionId = $validated['source_session_id'];
        $targetSessionId = $validated['target_session_id'];
        $action = $validated['action'];
        $targetClassId = $validated['target_class_id'] ?? null;
        $targetSectionId = $validated['target_section_id'] ?? null;
        $studentIds = $validated['student_ids'];

        $count = DB::transaction(function () use ($sourceSessionId, $targetSessionId, $action, $targetClassId, $targetSectionId, $studentIds) {
            $processedCount = 0;

            foreach ($studentIds as $studentId) {
                $sourceEnrollment = Enrollment::where('academic_session_id', $sourceSessionId)
                    ->where('student_id', $studentId)
                    ->first();

                if (! $sourceEnrollment) {
                    continue;
                }

                if ($action === 'graduate') {
                    $sourceEnrollment->update(['status' => EnrollmentStatus::GRADUATED]);
                    $processedCount++;

                    continue;
                }

                // Status mapping for source
                $sourceStatus = $action === 'repeat' ? EnrollmentStatus::REPEATED : EnrollmentStatus::PROMOTED;
                $sourceEnrollment->update(['status' => $sourceStatus]);

                // Create or update target enrollment linked to previous enrollment
                if ($targetClassId !== null && $targetSectionId !== null) {
                    Enrollment::updateOrCreate(
                        [
                            'student_id' => $studentId,
                            'academic_session_id' => $targetSessionId,
                        ],
                        [
                            'previous_enrollment_id' => $sourceEnrollment->id,
                            'class_id' => $targetClassId,
                            'section_id' => $targetSectionId,
                            'status' => EnrollmentStatus::ACTIVE,
                        ]
                    );
                }

                $processedCount++;
            }

            return $processedCount;
        });

        $actionLabel = match ($action) {
            'promote' => 'promoted',
            'repeat' => 'marked to repeat',
            'graduate' => 'graduated',
            default => 'processed',
        };

        if ($count > 0) {
            $sourceSession = AcademicSession::find($sourceSessionId);
            $targetSession = AcademicSession::find($targetSessionId);
            $targetClass = $targetClassId ? SchoolClass::find($targetClassId) : null;
            $targetClassName = $targetClass ? $targetClass->name : ($action === 'graduate' ? 'Alumni' : 'Same Grade');

            ActivityLogger::make('academic')
                ->event('promoted')
                ->withProperties([
                    'context' => [
                        'action' => $action,
                        'source_session' => $sourceSession?->name,
                        'target_session' => $targetSession?->name,
                        'target_class' => $targetClassName,
                        'students_count' => $count,
                        'student_ids' => $studentIds,
                    ],
                ])
                ->log("Executed {$actionLabel} progression for {$count} student(s) into {$targetClassName} ({$targetSession?->name})");
        }

        return redirect()->back()->with('success', "Batch operation completed: {$count} student(s) successfully {$actionLabel}.");
    }
}
