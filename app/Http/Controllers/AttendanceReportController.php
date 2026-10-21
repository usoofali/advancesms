<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Models\AcademicSession;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    /**
     * Display the student attendance report & analytics.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        $user = $request->user();

        if (! $user?->can('daily_attendances.view') && ! $user?->can('reports.broadsheet.view')) {
            abort(403, 'Unauthorized to view student attendance reports.');
        }

        // Sessions & Terms
        $sessions = AcademicSession::query()
            ->with(['terms:id,academic_session_id,name,start_date,end_date,is_current'])
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
        $selectedTerm = $terms->firstWhere('id', $selectedTermId) ?? $activeTerm;

        // Date selection
        $now = now();
        $todayStr = $now->toDateString();

        $attQuery = DailyAttendance::query()
            ->join('enrollments', 'daily_attendances.enrollment_id', '=', 'enrollments.id');

        if ($selectedSessionId) {
            $attQuery->where('enrollments.academic_session_id', $selectedSessionId);
        }
        if ($selectedTerm && $selectedTerm->start_date && $selectedTerm->end_date) {
            $attQuery->whereBetween('daily_attendances.attendance_date', [
                $selectedTerm->start_date->toDateString(),
                $selectedTerm->end_date->toDateString(),
            ]);
        }

        $minRecorded = (clone $attQuery)->min('daily_attendances.attendance_date');
        $maxRecorded = (clone $attQuery)->max('daily_attendances.attendance_date');

        if ($minRecorded) {
            $defaultDateFrom = Carbon::parse($minRecorded)->toDateString();
            $defaultDateTo = max($todayStr, Carbon::parse($maxRecorded)->toDateString());
        } elseif ($selectedTerm?->start_date && $selectedTerm->start_date <= $now) {
            $defaultDateFrom = $selectedTerm->start_date->toDateString();
            $defaultDateTo = ($selectedTerm->end_date && $selectedTerm->end_date <= $now)
                ? $selectedTerm->end_date->toDateString()
                : $todayStr;
        } else {
            $defaultDateFrom = $now->copy()->startOfMonth()->toDateString();
            $defaultDateTo = $todayStr;
        }

        $rawDateFrom = $request->input('date_from');
        $rawDateTo = $request->input('date_to');

        $dateFrom = filled($rawDateFrom) ? (string) $rawDateFrom : $defaultDateFrom;
        $dateTo = filled($rawDateTo) ? (string) $rawDateTo : $defaultDateTo;

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Classes & Sections
        $classes = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order')
            ->get(['id', 'name', 'stage', 'level_order']);

        $rawClassId = $request->input('class_id', 'all');
        $selectedClassId = ($rawClassId === 'all' || ! is_numeric($rawClassId)) ? 'all' : (int) $rawClassId;

        $rawSectionId = $request->input('section_id', 'all');
        $selectedSectionId = ($rawSectionId === 'all' || ! is_numeric($rawSectionId)) ? 'all' : (int) $rawSectionId;

        $school = SchoolSetting::current();

        // 1. School-Wide View (class_id === 'all')
        if ($selectedClassId === 'all') {
            $totalStudents = Enrollment::query()
                ->where('academic_session_id', $selectedSessionId)
                ->where('status', EnrollmentStatus::ACTIVE)
                ->count();

            $aggregate = DailyAttendance::query()
                ->join('enrollments', 'daily_attendances.enrollment_id', '=', 'enrollments.id')
                ->where('enrollments.academic_session_id', $selectedSessionId)
                ->where('enrollments.status', EnrollmentStatus::ACTIVE)
                ->whereBetween('daily_attendances.attendance_date', [$dateFrom, $dateTo])
                ->selectRaw("
                    count(distinct daily_attendances.attendance_date) as distinct_dates,
                    count(daily_attendances.id) as total_records,
                    sum(case when daily_attendances.status = 'present' then 1 else 0 end) as present_count,
                    sum(case when daily_attendances.status = 'late' then 1 else 0 end) as late_count,
                    sum(case when daily_attendances.status = 'excused' then 1 else 0 end) as excused_count,
                    sum(case when daily_attendances.status = 'absent' then 1 else 0 end) as absent_count
                ")
                ->first();

            $totalRecords = (int) ($aggregate?->total_records ?? 0);
            $presentCount = (int) ($aggregate?->present_count ?? 0);
            $lateCount = (int) ($aggregate?->late_count ?? 0);
            $excusedCount = (int) ($aggregate?->excused_count ?? 0);
            $absentCount = (int) ($aggregate?->absent_count ?? 0);
            $attendanceRate = $totalRecords > 0
                ? round((($presentCount + $lateCount) / $totalRecords) * 100, 1)
                : 0.0;

            $overallMetrics = [
                'total_students' => $totalStudents,
                'distinct_dates' => (int) ($aggregate?->distinct_dates ?? 0),
                'total_records' => $totalRecords,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'excused_count' => $excusedCount,
                'absent_count' => $absentCount,
                'attendance_rate' => $attendanceRate,
            ];

            $classEnrollmentCounts = Enrollment::query()
                ->where('academic_session_id', $selectedSessionId)
                ->where('status', EnrollmentStatus::ACTIVE)
                ->selectRaw('class_id, count(*) as count')
                ->groupBy('class_id')
                ->pluck('count', 'class_id');

            $classStats = DailyAttendance::query()
                ->join('enrollments', 'daily_attendances.enrollment_id', '=', 'enrollments.id')
                ->where('enrollments.academic_session_id', $selectedSessionId)
                ->where('enrollments.status', EnrollmentStatus::ACTIVE)
                ->whereBetween('daily_attendances.attendance_date', [$dateFrom, $dateTo])
                ->selectRaw("
                    enrollments.class_id,
                    count(distinct daily_attendances.attendance_date) as distinct_dates,
                    count(daily_attendances.id) as total_records,
                    sum(case when daily_attendances.status = 'present' then 1 else 0 end) as present_count,
                    sum(case when daily_attendances.status = 'late' then 1 else 0 end) as late_count,
                    sum(case when daily_attendances.status = 'excused' then 1 else 0 end) as excused_count,
                    sum(case when daily_attendances.status = 'absent' then 1 else 0 end) as absent_count
                ")
                ->groupBy('enrollments.class_id')
                ->get()
                ->keyBy('class_id');

            $chronicAbsenteesPerClass = DailyAttendance::query()
                ->join('enrollments', 'daily_attendances.enrollment_id', '=', 'enrollments.id')
                ->where('enrollments.academic_session_id', $selectedSessionId)
                ->where('enrollments.status', EnrollmentStatus::ACTIVE)
                ->whereBetween('daily_attendances.attendance_date', [$dateFrom, $dateTo])
                ->where('daily_attendances.status', 'absent')
                ->selectRaw('enrollments.class_id, enrollments.id as enrollment_id, count(*) as absences')
                ->groupBy('enrollments.class_id', 'enrollments.id')
                ->having('absences', '>=', 3)
                ->get()
                ->groupBy('class_id')
                ->map(fn ($group) => $group->count());

            $classSummaries = $classes->map(function ($class) use ($classEnrollmentCounts, $classStats, $chronicAbsenteesPerClass) {
                $stat = $classStats->get($class->id);
                $totalRec = (int) ($stat?->total_records ?? 0);
                $present = (int) ($stat?->present_count ?? 0);
                $late = (int) ($stat?->late_count ?? 0);
                $excused = (int) ($stat?->excused_count ?? 0);
                $absent = (int) ($stat?->absent_count ?? 0);
                $rate = $totalRec > 0 ? round((($present + $late) / $totalRec) * 100, 1) : 0.0;

                return [
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                    'stage' => $class->stage,
                    'sections_count' => $class->sections->count(),
                    'total_students' => (int) ($classEnrollmentCounts->get($class->id) ?? 0),
                    'distinct_dates' => (int) ($stat?->distinct_dates ?? 0),
                    'total_records' => $totalRec,
                    'present_count' => $present,
                    'late_count' => $late,
                    'excused_count' => $excused,
                    'absent_count' => $absent,
                    'attendance_rate' => $rate,
                    'chronic_absentees' => (int) ($chronicAbsenteesPerClass->get($class->id) ?? 0),
                ];
            });

            // Handle CSV Export
            if ($request->query('export') === 'csv') {
                return $this->exportClassSummariesCsv($classSummaries, $selectedSession?->name ?? 'Session', $selectedTerm?->name ?? 'Term', $dateFrom, $dateTo);
            }

            return Inertia::render('academic/reports/AttendanceReport', [
                'sessions' => $sessions,
                'classes' => $classes,
                'selectedSessionId' => $selectedSessionId,
                'selectedTermId' => $selectedTermId,
                'selectedClassId' => 'all',
                'selectedSectionId' => 'all',
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'viewMode' => 'school',
                'overallMetrics' => $overallMetrics,
                'classSummaries' => $classSummaries,
                'studentRoster' => [],
                'recordedDates' => [],
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

        // 2. Class / Section Roster View (selectedClassId is specific class ID)
        $selectedClass = $classes->firstWhere('id', $selectedClassId);
        $enrollmentsQuery = Enrollment::query()
            ->where('academic_session_id', $selectedSessionId)
            ->where('class_id', $selectedClassId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->with([
                'student:id,user_id,admission_number',
                'student.user:id,name,gender',
                'section:id,name',
            ]);

        if ($selectedSectionId !== 'all') {
            $enrollmentsQuery->where('section_id', $selectedSectionId);
        }

        $enrollments = $enrollmentsQuery->get()->sortBy(function ($enrollment) {
            return $enrollment->student?->user?->name ?? '';
        })->values();
        $enrollmentIds = $enrollments->pluck('id');

        $studentStats = DailyAttendance::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->selectRaw("
                enrollment_id,
                count(id) as total_days,
                sum(case when status = 'present' then 1 else 0 end) as present_count,
                sum(case when status = 'late' then 1 else 0 end) as late_count,
                sum(case when status = 'excused' then 1 else 0 end) as excused_count,
                sum(case when status = 'absent' then 1 else 0 end) as absent_count
            ")
            ->groupBy('enrollment_id')
            ->get()
            ->keyBy('enrollment_id');

        $recordedDates = DailyAttendance::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->distinct()
            ->orderBy('attendance_date')
            ->pluck('attendance_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->values()
            ->all();

        $dailyRecords = DailyAttendance::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->get(['enrollment_id', 'attendance_date', 'status'])
            ->groupBy('enrollment_id')
            ->map(function ($group) {
                return $group->mapWithKeys(function ($item) {
                    $date = Carbon::parse($item->attendance_date)->toDateString();

                    return [$date => $item->status->value];
                });
            });

        $classTotalDays = count($recordedDates);
        $presentSum = 0;
        $lateSum = 0;
        $excusedSum = 0;
        $absentSum = 0;
        $chronicCount = 0;

        $studentRoster = $enrollments->map(function ($enrollment) use (
            $studentStats,
            $dailyRecords,
            &$presentSum,
            &$lateSum,
            &$excusedSum,
            &$absentSum,
            &$chronicCount
        ) {
            $stat = $studentStats->get($enrollment->id);
            $totalDays = (int) ($stat?->total_days ?? 0);
            $present = (int) ($stat?->present_count ?? 0);
            $late = (int) ($stat?->late_count ?? 0);
            $excused = (int) ($stat?->excused_count ?? 0);
            $absent = (int) ($stat?->absent_count ?? 0);
            $rate = $totalDays > 0 ? round((($present + $late) / $totalDays) * 100, 1) : 0.0;

            $presentSum += $present;
            $lateSum += $late;
            $excusedSum += $excused;
            $absentSum += $absent;

            if ($absent >= 3 || ($totalDays >= 5 && $rate < 85)) {
                $chronicCount++;
            }

            $statusLevel = 'good';
            if ($totalDays > 0) {
                if ($rate >= 95) {
                    $statusLevel = 'excellent';
                } elseif ($rate >= 85) {
                    $statusLevel = 'good';
                } elseif ($rate >= 75) {
                    $statusLevel = 'warning';
                } else {
                    $statusLevel = 'critical';
                }
            }

            return [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'admission_number' => $enrollment->student->admission_number ?? 'N/A',
                'student_name' => $enrollment->student->user->name ?? 'Unknown',
                'gender' => $enrollment->student->user->gender ?? null,
                'section_name' => $enrollment->section->name ?? 'N/A',
                'total_days' => $totalDays,
                'present_count' => $present,
                'late_count' => $late,
                'excused_count' => $excused,
                'absent_count' => $absent,
                'attendance_rate' => $rate,
                'status_level' => $statusLevel,
                'daily_statuses' => $dailyRecords->get($enrollment->id, []),
            ];
        });

        $totalRecords = $presentSum + $lateSum + $excusedSum + $absentSum;
        $classMetrics = [
            'total_students' => $enrollments->count(),
            'distinct_dates' => $classTotalDays,
            'total_records' => $totalRecords,
            'present_count' => $presentSum,
            'late_count' => $lateSum,
            'excused_count' => $excusedSum,
            'absent_count' => $absentSum,
            'attendance_rate' => $totalRecords > 0 ? round((($presentSum + $lateSum) / $totalRecords) * 100, 1) : 0.0,
            'chronic_absentees' => $chronicCount,
        ];

        // Handle CSV Export
        if ($request->query('export') === 'csv') {
            return $this->exportStudentRosterCsv(
                $studentRoster,
                $selectedClass?->name ?? 'Class',
                $selectedSession?->name ?? 'Session',
                $selectedTerm?->name ?? 'Term',
                $dateFrom,
                $dateTo
            );
        }

        return Inertia::render('academic/reports/AttendanceReport', [
            'sessions' => $sessions,
            'classes' => $classes,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId,
            'selectedClassId' => $selectedClassId,
            'selectedSectionId' => $selectedSectionId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'viewMode' => 'class',
            'overallMetrics' => $classMetrics,
            'classSummaries' => [],
            'studentRoster' => $studentRoster,
            'recordedDates' => $recordedDates,
            'selectedClassName' => $selectedClass?->name ?? 'Class',
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
     * Stream CSV export of class breakdown summary.
     *
     * @param  Collection<int, array<string, mixed>>  $classSummaries
     */
    protected function exportClassSummariesCsv($classSummaries, string $session, string $term, string $from, string $to): StreamedResponse
    {
        $filename = sprintf('attendance-summary-%s-%s.csv', str($session)->slug(), str($term)->slug());

        return response()->streamDownload(function () use ($classSummaries, $session, $term, $from, $to) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['School Attendance Summary Report'], escape: '\\');
            fputcsv($handle, ['Session', $session, 'Term', $term, 'Period', "{$from} to {$to}"], escape: '\\');
            fputcsv($handle, [], escape: '\\');
            fputcsv($handle, [
                'Class',
                'Stage',
                'Sections',
                'Total Students',
                'Days Recorded',
                'Present',
                'Late',
                'Excused',
                'Absent',
                'Attendance Rate (%)',
                'Chronic Absentees (>=3 absences)',
            ], escape: '\\');

            foreach ($classSummaries as $row) {
                $stage = $row['stage'] ?? '';
                $stageLabel = $stage instanceof \BackedEnum ? $stage->value : (string) $stage;

                fputcsv($handle, [
                    $row['class_name'],
                    str($stageLabel)->headline(),
                    $row['sections_count'],
                    $row['total_students'],
                    $row['distinct_dates'],
                    $row['present_count'],
                    $row['late_count'],
                    $row['excused_count'],
                    $row['absent_count'],
                    $row['attendance_rate'].'%',
                    $row['chronic_absentees'],
                ], escape: '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Stream CSV export of class student roster.
     *
     * @param  Collection<int, array<string, mixed>>  $roster
     */
    protected function exportStudentRosterCsv($roster, string $className, string $session, string $term, string $from, string $to): StreamedResponse
    {
        $filename = sprintf('attendance-roster-%s-%s.csv', str($className)->slug(), str($term)->slug());

        return response()->streamDownload(function () use ($roster, $className, $session, $term, $from, $to) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ["Student Attendance Roster - {$className}"], escape: '\\');
            fputcsv($handle, ['Session', $session, 'Term', $term, 'Period', "{$from} to {$to}"], escape: '\\');
            fputcsv($handle, [], escape: '\\');
            fputcsv($handle, [
                '#',
                'Admission No',
                'Student Name',
                'Gender',
                'Section',
                'Days Evaluated',
                'Present',
                'Late',
                'Excused',
                'Absent',
                'Attendance %',
                'Status',
            ], escape: '\\');

            foreach ($roster as $index => $student) {
                $gender = $student['gender'] ?? null;
                $genderLabel = $gender instanceof Gender
                    ? $gender->label()
                    : ($gender ? str((string) ($gender instanceof \BackedEnum ? $gender->value : $gender))->headline() : '—');

                fputcsv($handle, [
                    $index + 1,
                    $student['admission_number'],
                    $student['student_name'],
                    $genderLabel,
                    $student['section_name'],
                    $student['total_days'],
                    $student['present_count'],
                    $student['late_count'],
                    $student['excused_count'],
                    $student['absent_count'],
                    $student['attendance_rate'].'%',
                    str($student['status_level'])->headline(),
                ], escape: '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
