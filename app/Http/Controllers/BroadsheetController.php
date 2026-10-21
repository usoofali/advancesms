<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StudentMark;
use App\Models\Subject;
use App\Services\AcademicRankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BroadsheetController extends Controller
{
    public function __construct(
        protected AcademicRankingService $rankingService
    ) {}

    /**
     * Display the broadsheet report matrix for class and section.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        $user = $request->user();

        if (! $user?->can('reports.broadsheet.view') && ! $user?->can('reports.broadsheet.generate')) {
            abort(403, 'Unauthorized to view academic broadsheet reports.');
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

        // Classes & Sections
        $classes = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order')
            ->get(['id', 'name', 'stage', 'level_order']);

        $firstClass = $classes->first();
        $selectedClassId = (int) $request->input('class_id', $firstClass ? $firstClass->id : 0);
        $selectedClass = $classes->firstWhere('id', $selectedClassId) ?? $firstClass;

        // Section filter: 0 means all sections
        $selectedSectionId = (int) $request->input('section_id', 0);

        // School Settings Branding
        $school = SchoolSetting::current();

        // Subjects for this class
        $subjects = Subject::query()
            ->whereHas('classSubjects', function ($q) use ($selectedClassId) {
                $q->where('class_id', $selectedClassId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // Fallback: If no class subjects explicitly attached, get all subjects
        if ($subjects->isEmpty()) {
            $subjects = Subject::query()->orderBy('name')->get(['id', 'name', 'code']);
        }

        // Exam Milestone
        $exam = null;
        if ($selectedSessionId && $selectedTermId) {
            $exam = Exam::where('academic_session_id', $selectedSessionId)
                ->where('academic_term_id', $selectedTermId)
                ->first();
        }

        $matrixRows = [];
        $subjectStats = [];
        $classMetrics = [
            'total_students' => 0,
            'class_average' => 0.0,
            'highest_total' => 0.0,
            'lowest_total' => 0.0,
        ];

        if ($selectedSessionId && $selectedClassId) {
            $enrollmentsQuery = Enrollment::query()
                ->where('academic_session_id', $selectedSessionId)
                ->where('class_id', $selectedClassId)
                ->where('status', EnrollmentStatus::ACTIVE)
                ->with([
                    'student:id,user_id,admission_number',
                    'student.user:id,name,gender',
                    'section:id,name',
                ]);

            if ($selectedSectionId > 0) {
                $enrollmentsQuery->where('section_id', $selectedSectionId);
            }

            $enrollments = $enrollmentsQuery->get();

            if ($enrollments->isNotEmpty() && $exam) {
                // Fetch all marks for these enrollments in this exam
                $marks = StudentMark::query()
                    ->whereIn('enrollment_id', $enrollments->pluck('id'))
                    ->where('exam_id', $exam->id)
                    ->with('grade:id,name')
                    ->get()
                    ->groupBy('enrollment_id');

                $preRankRows = [];

                foreach ($enrollments as $enrollment) {
                    $studentMarks = $marks->get($enrollment->id, collect());
                    $studentMarksBySubject = $studentMarks->keyBy('subject_id');

                    $subjectScores = [];
                    $totalScore = 0.0;
                    $subjectsTakenCount = 0;

                    foreach ($subjects as $subject) {
                        /** @var StudentMark|null $mark */
                        $mark = $studentMarksBySubject->get($subject->id);
                        $score = $mark ? (float) $mark->total_score : null;
                        $gradeName = $mark && $mark->grade ? $mark->grade->name : null;

                        if ($score !== null) {
                            $totalScore += $score;
                            $subjectsTakenCount++;
                        }

                        $subjectScores[$subject->id] = [
                            'score' => $score,
                            'grade' => $gradeName,
                        ];
                    }

                    $average = $subjectsTakenCount > 0
                        ? round($totalScore / $subjectsTakenCount, 2)
                        : 0.0;

                    $preRankRows[] = [
                        'enrollment_id' => $enrollment->id,
                        'student_id' => $enrollment->student_id,
                        'admission_number' => $enrollment->student->admission_number,
                        'student_name' => $enrollment->student->user->name,
                        'gender' => $enrollment->student->user->gender,
                        'roll_number' => $enrollment->roll_number,
                        'section_id' => $enrollment->section_id,
                        'section_name' => $enrollment->section->name,
                        'subject_scores' => $subjectScores,
                        'total_score' => $totalScore,
                        'average' => $average,
                        'subjects_taken' => $subjectsTakenCount,
                        'class_rank' => 0,
                        'class_position' => '',
                        'section_rank' => 0,
                        'section_position' => '',
                    ];
                }

                // Rank across entire class
                $rankedByClass = $this->rankingService->rankItems($preRankRows, 'total_score', 'class_rank', 'class_position');

                // Group by section and rank within each section
                /** @var Collection<int, Collection<int, array<string, mixed>>> $groupedBySection */
                $groupedBySection = collect($rankedByClass)->groupBy('section_id');
                $finalRankedList = [];

                foreach ($groupedBySection as $sectionItems) {
                    /** @var array<int, array<string, mixed>> $itemsArray */
                    $itemsArray = $sectionItems->values()->all();
                    $rankedSection = $this->rankingService->rankItems($itemsArray, 'total_score', 'section_rank', 'section_position');
                    foreach ($rankedSection as $row) {
                        $finalRankedList[] = $row;
                    }
                }

                // Sort final list by class rank ascending
                usort($finalRankedList, fn ($a, $b) => $a['class_rank'] <=> $b['class_rank']);
                $matrixRows = $finalRankedList;

                // Compute subject statistics
                foreach ($subjects as $subject) {
                    $scores = [];
                    foreach ($matrixRows as $row) {
                        if (isset($row['subject_scores'][$subject->id]['score'])) {
                            $scores[] = (float) $row['subject_scores'][$subject->id]['score'];
                        }
                    }

                    $count = count($scores);
                    $avg = $count > 0 ? round(array_sum($scores) / $count, 1) : 0.0;
                    $highest = ! empty($scores) ? max($scores) : 0.0;
                    $lowest = ! empty($scores) ? min($scores) : 0.0;
                    $passes = count(array_filter($scores, fn ($s) => $s >= 50.0));
                    $fails = count(array_filter($scores, fn ($s) => $s < 50.0));

                    $subjectStats[$subject->id] = [
                        'average' => $avg,
                        'highest' => $highest,
                        'lowest' => $lowest,
                        'passes' => $passes,
                        'fails' => $fails,
                        'total_sat' => $count,
                    ];
                }

                // Compute class summary metrics
                $totalScores = array_column($matrixRows, 'total_score');
                $averages = array_column($matrixRows, 'average');
                $studentCount = count($matrixRows);

                $classMetrics = [
                    'total_students' => $studentCount,
                    'class_average' => $studentCount > 0 ? round(array_sum($averages) / $studentCount, 2) : 0.0,
                    'highest_total' => ! empty($totalScores) ? max($totalScores) : 0.0,
                    'lowest_total' => ! empty($totalScores) ? min($totalScores) : 0.0,
                ];
            }
        }

        // Export as CSV if requested
        if ($request->query('export') === 'csv') {
            return $this->exportCsv($matrixRows, $subjects, $selectedClass, $selectedSession, $activeTerm);
        }

        return Inertia::render('academic/reports/Broadsheet', [
            'sessions' => $sessions,
            'classes' => $classes,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId,
            'selectedClassId' => $selectedClassId,
            'selectedSectionId' => $selectedSectionId,
            'subjects' => $subjects,
            'matrixRows' => $matrixRows,
            'subjectStats' => $subjectStats,
            'classMetrics' => $classMetrics,
            'school' => [
                'name' => $school->school_name,
                'school_name' => $school->school_name,
                'code' => $school->school_code,
                'school_code' => $school->school_code,
                'motto' => $school->motto,
                'address' => $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'logo_url' => $school->logo_url,
            ],
        ]);
    }

    /**
     * Generate streamed CSV export for broadsheet matrix.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, Subject>  $subjects
     */
    protected function exportCsv(
        array $rows,
        Collection $subjects,
        ?SchoolClass $class,
        ?AcademicSession $session,
        ?AcademicTerm $term
    ): StreamedResponse {
        $className = $class ? $class->name : 'Class';
        $sessionName = $session ? $session->name : 'Session';
        $termName = $term ? $term->name : 'Term';
        $filename = "broadsheet_{$className}_{$sessionName}_{$termName}.csv";
        $filename = str_replace(['/', ' '], '_', strtolower($filename));

        return response()->streamDownload(function () use ($rows, $subjects) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // CSV Header Row
            $headers = ['Class Rank', 'Section Rank', 'Admission No', 'Student Name', 'Gender', 'Section'];
            foreach ($subjects as $subject) {
                $headers[] = $subject->code ?: $subject->name;
            }
            $headers[] = 'Total Score';
            $headers[] = 'Average (%)';

            fputcsv($handle, $headers, escape: '\\');

            // CSV Data Rows
            foreach ($rows as $row) {
                $gender = $row['gender'] ?? null;
                $genderLabel = $gender instanceof Gender
                    ? $gender->label()
                    : ($gender ? ucfirst((string) ($gender instanceof \BackedEnum ? $gender->value : $gender)) : '—');

                $line = [
                    $row['class_position'],
                    $row['section_position'],
                    $row['admission_number'],
                    $row['student_name'],
                    $genderLabel,
                    $row['section_name'],
                ];

                foreach ($subjects as $subject) {
                    $scoreData = $row['subject_scores'][$subject->id] ?? null;
                    $scoreVal = $scoreData && $scoreData['score'] !== null ? $scoreData['score'] : '-';
                    $gradeVal = $scoreData && $scoreData['grade'] ? " ({$scoreData['grade']})" : '';
                    $line[] = $scoreVal !== '-' ? "{$scoreVal}{$gradeVal}" : '-';
                }

                $line[] = $row['total_score'];
                $line[] = $row['average'].'%';

                fputcsv($handle, $line, escape: '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
