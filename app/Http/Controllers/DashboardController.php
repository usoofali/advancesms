<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentMark;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AcademicRankingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected AcademicRankingService $rankingService
    ) {}

    /**
     * Display role-aware institutional dashboard.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Student Dashboard Flow
        if ($user->hasRole('student')) {
            return $this->studentDashboard($user, $request);
        }

        // 2. Guardian / Parent Dashboard Flow
        if ($user->hasRole('guardian')) {
            return $this->guardianDashboard($user, $request);
        }

        // 3. Administrative / Staff / Teacher Dashboard Flow
        return $this->adminDashboard($user, $request);
    }

    /**
     * Build student personal portal dashboard.
     */
    protected function studentDashboard(User $user, Request $request): Response
    {
        $student = Student::where('user_id', $user->id)->first();
        $enrollment = null;
        $marksList = [];
        $totalScoreObtained = 0.0;
        $terminalAverage = 0.0;
        $attendanceStats = [
            'total_school_days' => 0,
            'days_present' => 0,
            'days_late' => 0,
            'days_absent' => 0,
            'days_excused' => 0,
            'attendance_rate' => 0.0,
        ];
        $recentAttendances = [];
        $termName = 'Current Term';

        if ($student) {
            $enrollment = Enrollment::where('student_id', $student->id)
                ->where('status', EnrollmentStatus::ACTIVE)
                ->with(['schoolClass', 'section', 'academicSession'])
                ->latest()
                ->first();

            if ($enrollment) {
                // Determine active term
                $terms = AcademicTerm::where('academic_session_id', $enrollment->academic_session_id)->get();
                $selectedTermId = (int) $request->input('term_id', 0);
                $activeTerm = $terms->firstWhere('id', $selectedTermId)
                    ?? $terms->firstWhere('is_current', true)
                    ?? $terms->first();

                if ($activeTerm) {
                    $termName = $activeTerm->name;
                    $exam = Exam::where('academic_session_id', $enrollment->academic_session_id)
                        ->where('academic_term_id', $activeTerm->id)
                        ->first();

                    if ($exam) {
                        $marks = StudentMark::where('enrollment_id', $enrollment->id)
                            ->where('exam_id', $exam->id)
                            ->with(['subject:id,name,code', 'grade:id,name,grade_point,remark'])
                            ->get();

                        foreach ($marks as $mark) {
                            $score = (float) $mark->total_score;
                            $totalScoreObtained += $score;

                            $marksList[] = [
                                'subject_name' => $mark->subject->name,
                                'subject_code' => $mark->subject->code,
                                'ca1' => $mark->ca1 !== null ? (float) $mark->ca1 : null,
                                'ca2' => $mark->ca2 !== null ? (float) $mark->ca2 : null,
                                'ca3' => $mark->ca3 !== null ? (float) $mark->ca3 : null,
                                'exam_score' => $mark->exam_score !== null ? (float) $mark->exam_score : null,
                                'total_score' => $score,
                                'grade' => $mark->grade ? $mark->grade->name : 'F',
                                'grade_point' => $mark->grade ? (float) $mark->grade->grade_point : 0.0,
                                'remark' => $mark->grade ? $mark->grade->remark : 'Fail',
                            ];
                        }

                        $subjectCount = count($marksList);
                        $terminalAverage = $subjectCount > 0 ? round($totalScoreObtained / $subjectCount, 2) : 0.0;
                    }
                }

                // Attendance profile
                $attendances = DailyAttendance::where('enrollment_id', $enrollment->id)
                    ->orderByDesc('attendance_date')
                    ->get();

                $totalDays = $attendances->count();
                $present = $attendances->where('status', AttendanceStatus::PRESENT)->count();
                $late = $attendances->where('status', AttendanceStatus::LATE)->count();
                $absent = $attendances->where('status', AttendanceStatus::ABSENT)->count();
                $excused = $attendances->where('status', AttendanceStatus::EXCUSED)->count();

                $attendanceStats = [
                    'total_school_days' => $totalDays,
                    'days_present' => $present,
                    'days_late' => $late,
                    'days_absent' => $absent,
                    'days_excused' => $excused,
                    'attendance_rate' => $totalDays > 0 ? round((($present + $late) / $totalDays) * 100, 1) : 0.0,
                ];

                $recentAttendances = $attendances->take(5)->map(function (DailyAttendance $att) {
                    return [
                        'date' => $att->attendance_date->toDateString(),
                        'status' => $att->status->value,
                        'remark' => $att->remark,
                    ];
                })->all();
            }
        }

        return Inertia::render('Dashboard', [
            'role_view' => 'student',
            'student_profile' => $student && $enrollment ? [
                'enrollment_id' => $enrollment->id,
                'admission_number' => $student->admission_number,
                'student_name' => $user->name,
                'class_name' => $enrollment->schoolClass->name,
                'section_name' => $enrollment->section->name,
                'session_name' => $enrollment->academicSession->name,
                'roll_number' => $enrollment->roll_number,
            ] : null,
            'term_name' => $termName,
            'marks' => $marksList,
            'total_score' => $totalScoreObtained,
            'terminal_average' => $terminalAverage,
            'attendance' => $attendanceStats,
            'recent_attendances' => $recentAttendances,
        ]);
    }

    /**
     * Build guardian / parent portal dashboard with ward selection.
     */
    protected function guardianDashboard(User $user, Request $request): Response
    {
        $guardian = Guardian::where('user_id', $user->id)->first();
        $wards = [];
        $selectedWardId = (int) $request->input('ward_id', 0);
        $activeWardData = null;

        if ($guardian) {
            $studentWards = Student::where('guardian_id', $guardian->id)
                ->with(['user:id,name,gender', 'currentEnrollment.schoolClass', 'currentEnrollment.section', 'currentEnrollment.academicSession'])
                ->get();

            foreach ($studentWards as $ward) {
                $curEnrollment = $ward->currentEnrollment;
                $wards[] = [
                    'id' => $ward->id,
                    'name' => $ward->user->name,
                    'admission_number' => $ward->admission_number,
                    'class_name' => $curEnrollment && $curEnrollment->schoolClass ? $curEnrollment->schoolClass->name : 'Unallocated',
                    'section_name' => $curEnrollment && $curEnrollment->section ? $curEnrollment->section->name : 'N/A',
                ];
            }

            $targetWard = $selectedWardId > 0
                ? $studentWards->firstWhere('id', $selectedWardId)
                : $studentWards->first();

            if ($targetWard) {
                $selectedWardId = $targetWard->id;
                $enrollment = $targetWard->currentEnrollment;

                $marksList = [];
                $totalScoreObtained = 0.0;
                $terminalAverage = 0.0;
                $attendanceStats = [
                    'total_school_days' => 0,
                    'days_present' => 0,
                    'days_late' => 0,
                    'days_absent' => 0,
                    'days_excused' => 0,
                    'attendance_rate' => 0.0,
                ];

                if ($enrollment) {
                    $terms = AcademicTerm::where('academic_session_id', $enrollment->academic_session_id)->get();
                    $activeTerm = $terms->firstWhere('is_current', true) ?? $terms->first();

                    if ($activeTerm) {
                        $exam = Exam::where('academic_session_id', $enrollment->academic_session_id)
                            ->where('academic_term_id', $activeTerm->id)
                            ->first();

                        if ($exam) {
                            $marks = StudentMark::where('enrollment_id', $enrollment->id)
                                ->where('exam_id', $exam->id)
                                ->with(['subject:id,name,code', 'grade:id,name,grade_point,remark'])
                                ->get();

                            foreach ($marks as $mark) {
                                $score = (float) $mark->total_score;
                                $totalScoreObtained += $score;

                                $marksList[] = [
                                    'subject_name' => $mark->subject->name,
                                    'subject_code' => $mark->subject->code,
                                    'ca1' => $mark->ca1 !== null ? (float) $mark->ca1 : null,
                                    'ca2' => $mark->ca2 !== null ? (float) $mark->ca2 : null,
                                    'ca3' => $mark->ca3 !== null ? (float) $mark->ca3 : null,
                                    'exam_score' => $mark->exam_score !== null ? (float) $mark->exam_score : null,
                                    'total_score' => $score,
                                    'grade' => $mark->grade ? $mark->grade->name : 'F',
                                    'grade_point' => $mark->grade ? (float) $mark->grade->grade_point : 0.0,
                                    'remark' => $mark->grade ? $mark->grade->remark : 'Fail',
                                ];
                            }

                            $subjectCount = count($marksList);
                            $terminalAverage = $subjectCount > 0 ? round($totalScoreObtained / $subjectCount, 2) : 0.0;
                        }
                    }

                    $attendances = DailyAttendance::where('enrollment_id', $enrollment->id)->get();
                    $totalDays = $attendances->count();
                    $present = $attendances->where('status', AttendanceStatus::PRESENT)->count();
                    $late = $attendances->where('status', AttendanceStatus::LATE)->count();
                    $absent = $attendances->where('status', AttendanceStatus::ABSENT)->count();
                    $excused = $attendances->where('status', AttendanceStatus::EXCUSED)->count();

                    $attendanceStats = [
                        'total_school_days' => $totalDays,
                        'days_present' => $present,
                        'days_late' => $late,
                        'days_absent' => $absent,
                        'days_excused' => $excused,
                        'attendance_rate' => $totalDays > 0 ? round((($present + $late) / $totalDays) * 100, 1) : 0.0,
                    ];
                }

                $activeWardData = [
                    'ward_id' => $targetWard->id,
                    'enrollment_id' => $enrollment ? $enrollment->id : null,
                    'name' => $targetWard->user->name,
                    'admission_number' => $targetWard->admission_number,
                    'class_name' => $enrollment && $enrollment->schoolClass ? $enrollment->schoolClass->name : 'Unallocated',
                    'section_name' => $enrollment && $enrollment->section ? $enrollment->section->name : 'N/A',
                    'session_name' => $enrollment && $enrollment->academicSession ? $enrollment->academicSession->name : '',
                    'marks' => $marksList,
                    'total_score' => $totalScoreObtained,
                    'terminal_average' => $terminalAverage,
                    'attendance' => $attendanceStats,
                ];
            }
        }

        return Inertia::render('Dashboard', [
            'role_view' => 'guardian',
            'wards' => $wards,
            'selected_ward_id' => $selectedWardId,
            'active_ward' => $activeWardData,
        ]);
    }

    /**
     * Build administrative / teacher institutional overview dashboard.
     */
    protected function adminDashboard(User $user, Request $request): Response
    {
        $activeSession = AcademicSession::where('is_current', true)->first(['id', 'name']);
        $activeTerm = AcademicTerm::where('is_current', true)->first(['id', 'name', 'term_number']);

        $totalStudents = Student::count();
        $totalTeachers = Teacher::count();
        $totalClasses = SchoolClass::count();
        $totalSections = Section::count();

        // Today's attendance quick stats
        $today = now()->toDateString();
        $todayAttendances = DailyAttendance::where('attendance_date', $today)->get();
        $presentToday = $todayAttendances->where('status', AttendanceStatus::PRESENT)->count();
        $absentToday = $todayAttendances->where('status', AttendanceStatus::ABSENT)->count();
        $lateToday = $todayAttendances->where('status', AttendanceStatus::LATE)->count();
        $totalTodayRecorded = $todayAttendances->count();

        // Recent student enrollments (5 items)
        $recentEnrollments = Enrollment::query()
            ->with(['student.user:id,name,gender', 'schoolClass:id,name', 'section:id,name'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Enrollment $enr) {
                return [
                    'id' => $enr->id,
                    'student_name' => $enr->student->user->name,
                    'gender' => $enr->student->user->gender,
                    'class_name' => $enr->schoolClass->name,
                    'section_name' => $enr->section->name,
                    'date' => $enr->created_at ? $enr->created_at->diffForHumans() : '',
                ];
            })
            ->all();

        return Inertia::render('Dashboard', [
            'role_view' => 'admin',
            'stats' => [
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'total_classes' => $totalClasses,
                'total_sections' => $totalSections,
                'active_session' => $activeSession ? $activeSession->name : 'None',
                'active_term' => $activeTerm ? $activeTerm->name : 'None',
                'present_today' => $presentToday,
                'absent_today' => $absentToday,
                'late_today' => $lateToday,
                'total_today_recorded' => $totalTodayRecorded,
            ],
            'recent_enrollments' => $recentEnrollments,
        ]);
    }
}
