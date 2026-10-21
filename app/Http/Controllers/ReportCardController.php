<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StudentMark;
use App\Services\AcademicRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReportCardController extends Controller
{
    public function __construct(
        protected AcademicRankingService $rankingService
    ) {}

    /**
     * Display the report cards directory and student roster selector.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user?->can('reports.report_card.view') && ! $user?->can('reports.report_card.generate')) {
            abort(403, 'Unauthorized to view student report cards.');
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

        $sections = $selectedClass ? $selectedClass->sections : collect();
        $firstSection = $sections->first();
        $selectedSectionId = (int) $request->input('section_id', $firstSection ? $firstSection->id : 0);

        $search = $request->input('search');

        // Query Enrollments with pagination (15 per page)
        $enrollmentsQuery = Enrollment::query()
            ->where('academic_session_id', $selectedSessionId)
            ->where('class_id', $selectedClassId)
            ->where('section_id', $selectedSectionId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->with([
                'student:id,user_id,admission_number',
                'student.user:id,name,gender',
                'schoolClass:id,name',
                'section:id,name',
            ])
            ->when($search, function ($q, $search) {
                $q->whereHas('student.user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                })->orWhereHas('student', function ($sq) use ($search) {
                    $sq->where('admission_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('roll_number')
            ->orderBy('id');

        $enrollments = $enrollmentsQuery->paginate(15)->withQueryString();

        // Get exam milestone
        $exam = null;
        if ($selectedSessionId && $selectedTermId) {
            $exam = Exam::where('academic_session_id', $selectedSessionId)
                ->where('academic_term_id', $selectedTermId)
                ->first();
        }

        // Count marks entered per enrollment
        $marksCounts = collect();
        if ($exam && $enrollments->isNotEmpty()) {
            $marksCounts = DB::table('student_marks')
                ->where('exam_id', $exam->id)
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->selectRaw('enrollment_id, COUNT(*) as count, AVG(total_score) as avg_score')
                ->groupBy('enrollment_id')
                ->get()
                ->keyBy('enrollment_id');
        }

        $students = $enrollments->through(function (Enrollment $enrollment) use ($marksCounts) {
            /** @var object{count: int|string, avg_score: float|string|null}|null $markStats */
            $markStats = $marksCounts->get($enrollment->id);

            return [
                'id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'admission_number' => $enrollment->student->admission_number,
                'student_name' => $enrollment->student->user->name,
                'gender' => $enrollment->student->user->gender,
                'roll_number' => $enrollment->roll_number,
                'class_name' => $enrollment->schoolClass->name,
                'section_name' => $enrollment->section->name,
                'subjects_evaluated' => $markStats ? (int) $markStats->count : 0,
                'average_score' => $markStats && $markStats->avg_score !== null ? round((float) $markStats->avg_score, 1) : null,
            ];
        });

        return Inertia::render('academic/reports/ReportCardIndex', [
            'sessions' => $sessions,
            'classes' => $classes,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId,
            'selectedClassId' => $selectedClassId,
            'selectedSectionId' => $selectedSectionId,
            'students' => $students,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display a comprehensive, printable terminal report card for a student.
     */
    public function show(Enrollment $enrollment, Request $request): Response
    {
        $user = $request->user();

        if (! $user?->can('reports.report_card.view') && ! $user?->can('reports.report_card.generate')) {
            abort(403, 'Unauthorized to view student report card.');
        }

        $enrollment->load([
            'student.user:id,name,gender,avatar',
            'student.guardian.user:id,name,phone',
            'schoolClass:id,name,stage',
            'section:id,name',
            'academicSession:id,name',
        ]);

        // Determine Active or Selected Term
        $terms = AcademicTerm::where('academic_session_id', $enrollment->academic_session_id)
            ->orderBy('term_number')
            ->get();

        $selectedTermId = (int) $request->input('term_id', 0);
        $term = $terms->firstWhere('id', $selectedTermId)
            ?? $terms->firstWhere('is_current', true)
            ?? $terms->first();

        // Exam Milestone
        $exam = null;
        if ($term) {
            $exam = Exam::where('academic_session_id', $enrollment->academic_session_id)
                ->where('academic_term_id', $term->id)
                ->first();
        }

        // Student Marks Breakdown
        $marksList = [];
        $totalScoreObtained = 0.0;
        $totalMaxScore = 0.0;

        if ($exam) {
            $marks = StudentMark::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('exam_id', $exam->id)
                ->with(['subject:id,name,code', 'grade:id,name,grade_point,remark'])
                ->orderBy('subject_id')
                ->get();

            foreach ($marks as $mark) {
                $total = (float) $mark->total_score;
                $totalScoreObtained += $total;
                $totalMaxScore += 100.0;

                $marksList[] = [
                    'id' => $mark->id,
                    'subject_name' => $mark->subject->name,
                    'subject_code' => $mark->subject->code,
                    'ca1' => $mark->ca1 !== null ? (float) $mark->ca1 : null,
                    'ca2' => $mark->ca2 !== null ? (float) $mark->ca2 : null,
                    'ca3' => $mark->ca3 !== null ? (float) $mark->ca3 : null,
                    'exam_score' => $mark->exam_score !== null ? (float) $mark->exam_score : null,
                    'total_score' => $total,
                    'grade' => $mark->grade ? $mark->grade->name : 'F',
                    'grade_point' => $mark->grade ? (float) $mark->grade->grade_point : 0.0,
                    'remark' => $mark->grade ? $mark->grade->remark : 'Fail',
                    'teacher_comment' => $mark->teacher_comment,
                ];
            }
        }

        $subjectsCount = count($marksList);
        $terminalAverage = $subjectsCount > 0 ? round($totalScoreObtained / $subjectsCount, 2) : 0.0;

        // Attendance Record for this Enrollment in this Session/Term
        $attendances = DailyAttendance::query()
            ->where('enrollment_id', $enrollment->id)
            ->get();

        $daysPresent = $attendances->where('status', AttendanceStatus::PRESENT)->count();
        $daysLate = $attendances->where('status', AttendanceStatus::LATE)->count();
        $daysAbsent = $attendances->where('status', AttendanceStatus::ABSENT)->count();
        $daysExcused = $attendances->where('status', AttendanceStatus::EXCUSED)->count();
        $totalSchoolDays = $attendances->count();
        $attendanceRate = $totalSchoolDays > 0
            ? round((($daysPresent + $daysLate) / $totalSchoolDays) * 100, 1)
            : 0.0;

        // Class & Section Position Calculation
        $allClassEnrollments = Enrollment::query()
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->where('class_id', $enrollment->class_id)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->pluck('id', 'section_id');

        $sectionTotalStudents = Enrollment::query()
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->where('section_id', $enrollment->section_id)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->count();

        $classTotalStudents = Enrollment::query()
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->where('class_id', $enrollment->class_id)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->count();

        // Calculate positions across all peers if exam exists
        $sectionPosition = '-';
        $classPosition = '-';
        $highestClassAverage = 0.0;
        $lowestClassAverage = 0.0;
        $classTotalAverage = 0.0;

        if ($exam) {
            $classPeerEnrollmentIds = Enrollment::query()
                ->where('academic_session_id', $enrollment->academic_session_id)
                ->where('class_id', $enrollment->class_id)
                ->where('status', EnrollmentStatus::ACTIVE)
                ->pluck('id');

            $allPeerMarks = DB::table('student_marks')
                ->where('exam_id', $exam->id)
                ->whereIn('enrollment_id', $classPeerEnrollmentIds)
                ->selectRaw('enrollment_id, SUM(total_score) as total_marks, AVG(total_score) as avg_marks')
                ->groupBy('enrollment_id')
                ->get();

            $peerRankItems = [];
            foreach ($allPeerMarks as $peer) {
                /** @var object{enrollment_id: int|string, total_marks: float|string, avg_marks: float|string} $peer */
                $peerRankItems[] = [
                    'enrollment_id' => (int) $peer->enrollment_id,
                    'total_score' => (float) $peer->total_marks,
                    'average' => (float) $peer->avg_marks,
                ];
            }

            if (! empty($peerRankItems)) {
                $rankedClass = $this->rankingService->rankItems($peerRankItems, 'total_score', 'rank', 'position');
                $myClassRank = collect($rankedClass)->firstWhere('enrollment_id', $enrollment->id);
                if ($myClassRank) {
                    $classPosition = (string) $myClassRank['position'];
                }

                $sectionPeerEnrollmentIds = Enrollment::query()
                    ->where('academic_session_id', $enrollment->academic_session_id)
                    ->where('section_id', $enrollment->section_id)
                    ->where('status', EnrollmentStatus::ACTIVE)
                    ->pluck('id');

                $sectionRankItems = array_values(array_filter($peerRankItems, fn ($i) => $sectionPeerEnrollmentIds->contains($i['enrollment_id'])));
                $rankedSection = $this->rankingService->rankItems($sectionRankItems, 'total_score', 'rank', 'position');
                $mySectionRank = collect($rankedSection)->firstWhere('enrollment_id', $enrollment->id);
                if ($mySectionRank) {
                    $sectionPosition = (string) $mySectionRank['position'];
                }

                $averages = array_column($peerRankItems, 'average');
                $highestClassAverage = max($averages);
                $lowestClassAverage = min($averages);
                $classTotalAverage = round(array_sum($averages) / count($averages), 2);
            }
        }

        // Institutional White-label Branding
        $school = SchoolSetting::current();

        // Grading Scale Reference
        $grades = Grade::query()
            ->orderByDesc('mark_from')
            ->get(['name', 'grade_point', 'mark_from', 'mark_to', 'remark']);

        // Default automated Principal & Form Master remarks based on average
        $principalRemark = match (true) {
            $terminalAverage >= 80 => 'An outstanding academic performance. Demonstrated exceptional discipline and intellect. Keep shining!',
            $terminalAverage >= 70 => 'Very commendable result. Shows great diligence and aptitude. Capable of even higher heights.',
            $terminalAverage >= 60 => 'Good performance. Consistent effort observed. More concentration in core subjects recommended.',
            $terminalAverage >= 50 => 'Satisfactory result. Passed but needs to put in more study hours next term.',
            default => 'Below expectations. Requires immediate academic intervention, remedial classes, and intense parental supervision.',
        };

        $teacherRemark = match (true) {
            $terminalAverage >= 75 => 'A brilliant, well-behaved and industrious student with exemplary character.',
            $terminalAverage >= 65 => 'Shows positive attitude toward schoolwork and actively participates in class activities.',
            $terminalAverage >= 50 => 'Fair progress shown. Needs encouragement to cultivate more consistent personal study habits.',
            default => 'Weak terminal outcome. Must work much harder and take class assignments seriously.',
        };

        return Inertia::render('academic/reports/ReportCard', [
            'enrollment' => [
                'id' => $enrollment->id,
                'roll_number' => $enrollment->roll_number,
                'status' => $enrollment->status->value,
                'student' => [
                    'id' => $enrollment->student->id,
                    'admission_number' => $enrollment->student->admission_number,
                    'name' => $enrollment->student->user->name,
                    'gender' => $enrollment->student->user->gender,
                    'date_of_birth' => $enrollment->student->date_of_birth ? Carbon::parse($enrollment->student->date_of_birth)->toDateString() : null,
                    'state_of_origin' => $enrollment->student->state_of_origin,
                    'avatar' => $enrollment->student->user->avatar,
                    'guardian_name' => $enrollment->student->guardian && $enrollment->student->guardian->user ? $enrollment->student->guardian->user->name : null,
                    'guardian_phone' => $enrollment->student->guardian && $enrollment->student->guardian->user ? $enrollment->student->guardian->user->phone : null,
                ],
                'class_name' => $enrollment->schoolClass->name,
                'class_stage' => $enrollment->schoolClass->stage,
                'section_name' => $enrollment->section->name,
                'session_name' => $enrollment->academicSession->name,
            ],
            'term' => [
                'id' => $term ? $term->id : 0,
                'name' => $term ? $term->name : 'Terminal Examination',
                'term_number' => $term ? $term->term_number : 1,
            ],
            'marks' => $marksList,
            'summary' => [
                'total_score_obtained' => $totalScoreObtained,
                'total_max_score' => $totalMaxScore,
                'subjects_count' => $subjectsCount,
                'terminal_average' => $terminalAverage,
                'section_position' => $sectionPosition,
                'section_total_students' => $sectionTotalStudents,
                'class_position' => $classPosition,
                'class_total_students' => $classTotalStudents,
                'highest_class_average' => $highestClassAverage,
                'lowest_class_average' => $lowestClassAverage,
                'class_total_average' => $classTotalAverage,
            ],
            'attendance' => [
                'days_present' => $daysPresent,
                'days_late' => $daysLate,
                'days_absent' => $daysAbsent,
                'days_excused' => $daysExcused,
                'total_school_days' => $totalSchoolDays,
                'attendance_rate' => $attendanceRate,
            ],
            'remarks' => [
                'teacher' => $teacherRemark,
                'principal' => $principalRemark,
            ],
            'school' => [
                'name' => $school->school_name,
                'school_name' => $school->school_name,
                'code' => $school->school_code,
                'school_code' => $school->school_code,
                'motto' => $school->motto,
                'address' => $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'principal_name' => $school->principal_name ?? 'The Principal',
                'logo_url' => $school->logo_url,
                'stamp_url' => $school->stamp_url,
            ],
            'exam' => $exam ? [
                'id' => $exam->id,
                'name' => $exam->name,
                'ca_count' => $exam->ca_count ?? $school->ca_count ?? 3,
                'max_ca1' => (float) $exam->max_ca1,
                'max_ca2' => (float) $exam->max_ca2,
                'max_ca3' => (float) $exam->max_ca3,
                'max_exam' => (float) $exam->max_exam,
                'ca1_label' => $exam->ca1_label ?: ($school->ca1_label ?: '1st CA'),
                'ca2_label' => $exam->ca2_label ?: ($school->ca2_label ?: '2nd CA'),
                'ca3_label' => $exam->ca3_label ?: ($school->ca3_label ?: '3rd CA'),
            ] : [
                'id' => 0,
                'name' => 'Terminal Examination',
                'ca_count' => $school->ca_count ?? 3,
                'max_ca1' => (float) ($school->default_max_ca1 ?? 10.0),
                'max_ca2' => (float) ($school->default_max_ca2 ?? 10.0),
                'max_ca3' => (float) ($school->default_max_ca3 ?? 10.0),
                'max_exam' => (float) ($school->default_max_exam ?? 70.0),
                'ca1_label' => $school->ca1_label ?: '1st CA',
                'ca2_label' => $school->ca2_label ?: '2nd CA',
                'ca3_label' => $school->ca3_label ?: '3rd CA',
            ],
            'grades' => $grades,
        ]);
    }

    /**
     * Fetch complete marks roster and class rankings for batch PDF report card generation.
     */
    public function batchData(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->can('reports.report_card.view') && ! $user?->can('reports.report_card.generate')) {
            abort(403, 'Unauthorized to view student report cards.');
        }

        $sessionId = (int) $request->input('session_id', 0);
        $termId = (int) $request->input('term_id', 0);
        $classId = (int) $request->input('class_id', 0);
        $sectionId = (int) $request->input('section_id', 0);

        $session = AcademicSession::find($sessionId);
        $term = AcademicTerm::find($termId);
        $class = SchoolClass::find($classId);
        $section = Section::find($sectionId);

        if (! $session || ! $term || ! $class || ! $section) {
            return response()->json(['error' => 'Invalid session, term, class, or section.'], 400);
        }

        $school = SchoolSetting::current();

        $exam = Exam::where('academic_session_id', $sessionId)
            ->where('academic_term_id', $termId)
            ->first();

        $enrollments = Enrollment::query()
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->with([
                'student:id,user_id,guardian_id,admission_number,date_of_birth,state_of_origin',
                'student.user:id,name,gender',
                'student.guardian.user:id,name,phone',
            ])
            ->orderBy('roll_number')
            ->orderBy('id')
            ->get();

        $classTotalStudents = Enrollment::query()
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->count();

        // Calculate positions across all class peers and section peers if exam exists
        $classPositions = [];
        $sectionPositions = [];
        $highestClassAverage = 0.0;
        $lowestClassAverage = 0.0;
        $classTotalAverage = 0.0;

        if ($exam) {
            $classPeerEnrollmentIds = Enrollment::query()
                ->where('academic_session_id', $sessionId)
                ->where('class_id', $classId)
                ->where('status', EnrollmentStatus::ACTIVE)
                ->pluck('id');

            $allPeerMarks = DB::table('student_marks')
                ->where('exam_id', $exam->id)
                ->whereIn('enrollment_id', $classPeerEnrollmentIds)
                ->selectRaw('enrollment_id, SUM(total_score) as total_marks, AVG(total_score) as avg_marks')
                ->groupBy('enrollment_id')
                ->get();

            $peerRankItems = [];
            foreach ($allPeerMarks as $peer) {
                /** @var object{enrollment_id: int|string, total_marks: float|string, avg_marks: float|string} $peer */
                $peerRankItems[] = [
                    'enrollment_id' => (int) $peer->enrollment_id,
                    'total_score' => (float) $peer->total_marks,
                    'average' => (float) $peer->avg_marks,
                ];
            }

            if (! empty($peerRankItems)) {
                $rankedClass = $this->rankingService->rankItems($peerRankItems, 'total_score', 'rank', 'position');
                foreach ($rankedClass as $item) {
                    $classPositions[$item['enrollment_id']] = (string) $item['position'];
                }

                // Section ranks
                $currentSectionEnrollmentIds = $enrollments->pluck('id')->all();
                $sectionRankItems = array_values(array_filter(
                    $peerRankItems,
                    fn ($item) => in_array($item['enrollment_id'], $currentSectionEnrollmentIds, true)
                ));
                if (! empty($sectionRankItems)) {
                    $rankedSection = $this->rankingService->rankItems($sectionRankItems, 'total_score', 'rank', 'position');
                    foreach ($rankedSection as $item) {
                        $sectionPositions[$item['enrollment_id']] = (string) $item['position'];
                    }
                }

                $averages = array_column($peerRankItems, 'average');
                if (! empty($averages)) {
                    $highestClassAverage = max($averages);
                    $lowestClassAverage = min($averages);
                    $classTotalAverage = round(array_sum($averages) / count($averages), 2);
                }
            }
        }

        // Preload all marks for these enrollments
        $enrollmentIds = $enrollments->pluck('id');
        $allMarks = collect();
        if ($exam) {
            $allMarks = StudentMark::query()
                ->where('exam_id', $exam->id)
                ->whereIn('enrollment_id', $enrollmentIds)
                ->with(['subject:id,name,code', 'grade:id,name,grade_point,remark'])
                ->orderBy('subject_id')
                ->get()
                ->groupBy('enrollment_id');
        }

        // Preload all attendances for these enrollments
        $allAttendances = DailyAttendance::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->get()
            ->groupBy('enrollment_id');

        $grades = Grade::query()
            ->orderByDesc('mark_from')
            ->get(['name', 'grade_point', 'mark_from', 'mark_to', 'remark']);

        $studentsData = [];
        foreach ($enrollments as $enrollment) {
            $studentMarks = $allMarks->get($enrollment->id, collect());
            $marksList = [];
            $totalScoreObtained = 0.0;

            foreach ($studentMarks as $mark) {
                $total = (float) $mark->total_score;
                $totalScoreObtained += $total;

                $marksList[] = [
                    'id' => $mark->id,
                    'subject_name' => $mark->subject->name,
                    'subject_code' => $mark->subject->code,
                    'ca1' => $mark->ca1 !== null ? (float) $mark->ca1 : 0.0,
                    'ca2' => $mark->ca2 !== null ? (float) $mark->ca2 : 0.0,
                    'ca3' => $mark->ca3 !== null ? (float) $mark->ca3 : 0.0,
                    'exam_score' => $mark->exam_score !== null ? (float) $mark->exam_score : 0.0,
                    'total_score' => $total,
                    'grade' => $mark->grade ? $mark->grade->name : 'F',
                    'grade_point' => $mark->grade ? (float) $mark->grade->grade_point : 0.0,
                    'remark' => $mark->grade ? $mark->grade->remark : 'Fail',
                    'teacher_comment' => $mark->teacher_comment,
                ];
            }

            $subjectsCount = count($marksList);
            $terminalAverage = $subjectsCount > 0 ? round($totalScoreObtained / $subjectsCount, 2) : 0.0;

            $att = $allAttendances->get($enrollment->id, collect());
            $daysPresent = $att->where('status', AttendanceStatus::PRESENT)->count();
            $daysLate = $att->where('status', AttendanceStatus::LATE)->count();
            $daysAbsent = $att->where('status', AttendanceStatus::ABSENT)->count();
            $daysExcused = $att->where('status', AttendanceStatus::EXCUSED)->count();
            $totalSchoolDays = $att->count();
            $attendanceRate = $totalSchoolDays > 0
                ? round((($daysPresent + $daysLate) / $totalSchoolDays) * 100, 1)
                : 0.0;

            // Automated Remarks based on student average
            $principalRemark = match (true) {
                $terminalAverage >= 80 => 'An outstanding academic performance. Demonstrated exceptional discipline and intellect. Keep shining!',
                $terminalAverage >= 70 => 'Very commendable result. Shows great diligence and aptitude. Capable of even higher heights.',
                $terminalAverage >= 60 => 'Good performance. Consistent effort observed. More concentration in core subjects recommended.',
                $terminalAverage >= 50 => 'Satisfactory result. Passed but needs to put in more study hours next term.',
                default => 'Below expectations. Requires immediate academic intervention, remedial classes, and intense parental supervision.',
            };

            $teacherRemark = match (true) {
                $terminalAverage >= 75 => 'A brilliant, well-behaved and industrious student with exemplary character.',
                $terminalAverage >= 65 => 'Shows positive attitude toward schoolwork and actively participates in class activities.',
                $terminalAverage >= 50 => 'Fair progress shown. Needs encouragement to cultivate more consistent personal study habits.',
                default => 'Weak terminal outcome. Must work much harder and take class assignments seriously.',
            };

            $studentsData[] = [
                'id' => $enrollment->id,
                'admission_number' => $enrollment->student->admission_number,
                'student_name' => $enrollment->student->user->name,
                'gender' => $enrollment->student->user->gender,
                'roll_number' => $enrollment->roll_number,
                'date_of_birth' => $enrollment->student->date_of_birth ? Carbon::parse($enrollment->student->date_of_birth)->toDateString() : null,
                'state_of_origin' => $enrollment->student->state_of_origin,
                'guardian_name' => $enrollment->student->guardian && $enrollment->student->guardian->user ? $enrollment->student->guardian->user->name : null,
                'guardian_phone' => $enrollment->student->guardian && $enrollment->student->guardian->user ? $enrollment->student->guardian->user->phone : null,
                'marks' => $marksList,
                'total_score' => $totalScoreObtained,
                'average_score' => $terminalAverage,
                'subjects_count' => $subjectsCount,
                'class_position' => $classPositions[$enrollment->id] ?? '-',
                'section_position' => $sectionPositions[$enrollment->id] ?? '-',
                'attendance' => [
                    'days_present' => $daysPresent,
                    'days_late' => $daysLate,
                    'days_absent' => $daysAbsent,
                    'days_excused' => $daysExcused,
                    'total_days' => $totalSchoolDays,
                    'rate' => $attendanceRate,
                ],
                'remarks' => [
                    'teacher' => $teacherRemark,
                    'principal' => $principalRemark,
                ],
            ];
        }

        // Generate base64 representations for official stamp/signature and logo
        $stampBase64 = null;
        if ($school->stamp_path && Storage::disk('public')->exists($school->stamp_path)) {
            $mime = Storage::disk('public')->mimeType($school->stamp_path) ?: 'image/png';
            $stampBase64 = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($school->stamp_path));
        }

        $logoBase64 = null;
        if ($school->logo_path && Storage::disk('public')->exists($school->logo_path)) {
            $mime = Storage::disk('public')->mimeType($school->logo_path) ?: 'image/png';
            $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($school->logo_path));
        }

        return response()->json([
            'session_name' => $session->name,
            'term_name' => $term->name,
            'class_name' => $class->name,
            'section_name' => $section->name,
            'class_total_students' => $classTotalStudents,
            'section_total_students' => $enrollments->count(),
            'highest_class_average' => $highestClassAverage,
            'lowest_class_average' => $lowestClassAverage,
            'class_total_average' => $classTotalAverage,
            'school' => [
                'name' => $school->school_name,
                'code' => $school->school_code,
                'motto' => $school->motto,
                'address' => $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'principal_name' => $school->principal_name ?? 'The Principal',
                'logo_url' => $school->logo_url,
                'logo_base64' => $logoBase64,
                'stamp_url' => $school->stamp_url,
                'stamp_base64' => $stampBase64,
                'letterhead_base64' => $school->letterhead_base64,
                'letterhead_top_margin' => $school->letterhead_top_margin ?? 120,
                'letterhead_bottom_margin' => $school->letterhead_bottom_margin ?? 50,
                'letterhead_left_margin' => $school->letterhead_left_margin ?? 40,
                'letterhead_right_margin' => $school->letterhead_right_margin ?? 40,
            ],
            'exam' => $exam ? [
                'id' => $exam->id,
                'name' => $exam->name,
                'ca_count' => $exam->ca_count ?? $school->ca_count ?? 3,
                'max_ca1' => (float) $exam->max_ca1,
                'max_ca2' => (float) $exam->max_ca2,
                'max_ca3' => (float) $exam->max_ca3,
                'max_exam' => (float) $exam->max_exam,
                'ca1_label' => $exam->ca1_label ?: ($school->ca1_label ?: '1st CA'),
                'ca2_label' => $exam->ca2_label ?: ($school->ca2_label ?: '2nd CA'),
                'ca3_label' => $exam->ca3_label ?: ($school->ca3_label ?: '3rd CA'),
            ] : [
                'id' => 0,
                'name' => 'Terminal Examination',
                'ca_count' => $school->ca_count ?? 3,
                'max_ca1' => (float) ($school->default_max_ca1 ?? 10.0),
                'max_ca2' => (float) ($school->default_max_ca2 ?? 10.0),
                'max_ca3' => (float) ($school->default_max_ca3 ?? 10.0),
                'max_exam' => (float) ($school->default_max_exam ?? 70.0),
                'ca1_label' => $school->ca1_label ?: '1st CA',
                'ca2_label' => $school->ca2_label ?: '2nd CA',
                'ca3_label' => $school->ca3_label ?: '3rd CA',
            ],
            'students' => $studentsData,
            'grades' => $grades,
        ]);
    }
}
