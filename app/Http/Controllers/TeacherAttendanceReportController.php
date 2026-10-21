<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\SchoolSetting;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherAttendanceReportController extends Controller
{
    /**
     * Display the teacher attendance performance summary report.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        $user = $request->user();

        if (! $user?->can('teacher_attendances.view')) {
            abort(403, 'Unauthorized to view teacher attendance reports.');
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

        // Resolve intelligent default date boundaries
        $now = now();
        $todayStr = $now->toDateString();

        $attQuery = TeacherAttendance::query();
        if ($selectedSessionId) {
            $attQuery->where('academic_session_id', $selectedSessionId);
        }
        if ($selectedTermId) {
            $attQuery->where('term_id', $selectedTermId);
        }

        $minRecorded = (clone $attQuery)->min('attendance_date');
        $maxRecorded = (clone $attQuery)->max('attendance_date');

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

        // Departments Filter
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $rawDepartmentId = $request->input('department_id', 'all');
        $selectedDepartmentId = ($rawDepartmentId === 'all' || ! is_numeric($rawDepartmentId)) ? 'all' : (int) $rawDepartmentId;

        // Active Teachers
        $teachersQuery = Teacher::query()
            ->with(['user:id,name,email,gender,avatar,is_active', 'department:id,name'])
            ->whereHas('user', fn ($q) => $q->where('is_active', true));

        if ($selectedDepartmentId !== 'all') {
            $teachersQuery->where('department_id', $selectedDepartmentId);
        }

        $teachers = $teachersQuery->get()->sortBy(fn ($t) => $t->user?->name ?? '')->values();
        $teacherIds = $teachers->pluck('id');

        // Aggregated Attendance Statistics
        $stats = TeacherAttendance::query()
            ->whereIn('teacher_id', $teacherIds)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->selectRaw("
                teacher_id,
                count(id) as total_days,
                sum(case when status = 'present' then 1 else 0 end) as present_count,
                sum(case when status = 'late' then 1 else 0 end) as late_count,
                sum(case when status = 'excused' then 1 else 0 end) as excused_count,
                sum(case when status = 'absent' then 1 else 0 end) as absent_count
            ")
            ->groupBy('teacher_id')
            ->get()
            ->keyBy('teacher_id');

        // Distinct Recorded Days
        $recordedDates = TeacherAttendance::query()
            ->whereIn('teacher_id', $teacherIds)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->distinct()
            ->orderBy('attendance_date')
            ->pluck('attendance_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->values()
            ->all();

        // Daily records matrix for timeline grid
        $dailyRecords = TeacherAttendance::query()
            ->whereIn('teacher_id', $teacherIds)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->get(['teacher_id', 'attendance_date', 'status'])
            ->groupBy('teacher_id')
            ->map(function ($group) {
                return $group->mapWithKeys(function ($item) {
                    $date = Carbon::parse($item->attendance_date)->toDateString();

                    return [$date => $item->status->value];
                });
            });

        $school = SchoolSetting::current();

        $presentSum = 0;
        $lateSum = 0;
        $excusedSum = 0;
        $absentSum = 0;
        $atRiskCount = 0;

        $teacherSummaries = $teachers->map(function ($teacher) use (
            $stats,
            $dailyRecords,
            &$presentSum,
            &$lateSum,
            &$excusedSum,
            &$absentSum,
            &$atRiskCount
        ) {
            $stat = $stats->get($teacher->id);
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

            if ($absent >= 3 || ($totalDays >= 5 && $rate < 85)) {
                $atRiskCount++;
                if ($statusLevel !== 'critical') {
                    $statusLevel = 'warning';
                }
            }

            return [
                'teacher_id' => $teacher->id,
                'staff_id' => $teacher->staff_id,
                'name' => $teacher->user->name ?? 'Unknown',
                'email' => $teacher->user->email ?? '',
                'gender' => $teacher->user->gender ?? null,
                'avatar' => $teacher->user->avatar ?? null,
                'department_name' => $teacher->department->name ?? 'General',
                'designation' => $teacher->designation ?? 'Teacher',
                'qualification' => $teacher->qualification ?? '',
                'total_days' => $totalDays,
                'present_count' => $present,
                'late_count' => $late,
                'excused_count' => $excused,
                'absent_count' => $absent,
                'attendance_rate' => $rate,
                'status_level' => $statusLevel,
                'daily_statuses' => $dailyRecords->get($teacher->id, []),
            ];
        });

        $totalRecords = $presentSum + $lateSum + $excusedSum + $absentSum;
        $overallMetrics = [
            'total_staff' => $teachers->count(),
            'distinct_dates' => count($recordedDates),
            'total_records' => $totalRecords,
            'present_count' => $presentSum,
            'late_count' => $lateSum,
            'excused_count' => $excusedSum,
            'absent_count' => $absentSum,
            'attendance_rate' => $totalRecords > 0 ? round((($presentSum + $lateSum) / $totalRecords) * 100, 1) : 0.0,
            'at_risk_count' => $atRiskCount,
        ];

        // CSV Export
        if ($request->query('export') === 'csv') {
            return $this->exportCsv(
                $teacherSummaries,
                $selectedSession?->name ?? 'Session',
                $selectedTerm?->name ?? 'Term',
                $dateFrom,
                $dateTo
            );
        }

        return Inertia::render('people/teachers/attendance/Report', [
            'sessions' => $sessions,
            'departments' => $departments,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId,
            'selectedDepartmentId' => $selectedDepartmentId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'overallMetrics' => $overallMetrics,
            'teacherSummaries' => $teacherSummaries,
            'recordedDates' => $recordedDates,
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
     * Stream CSV export of teacher attendance performance report.
     *
     * @param  Collection<int, array<string, mixed>>  $summaries
     */
    protected function exportCsv($summaries, string $session, string $term, string $from, string $to): StreamedResponse
    {
        $filename = sprintf('teacher-attendance-report-%s-%s.csv', str($session)->slug(), str($term)->slug());

        return response()->streamDownload(function () use ($summaries, $session, $term, $from, $to) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Teacher & Staff Attendance Performance Report'], escape: '\\');
            fputcsv($handle, ['Session', $session, 'Term', $term, 'Period', "{$from} to {$to}"], escape: '\\');
            fputcsv($handle, [], escape: '\\');
            fputcsv($handle, [
                '#',
                'Staff ID',
                'Teacher Name',
                'Department',
                'Designation',
                'Gender',
                'Days Evaluated',
                'Present',
                'Late',
                'Excused',
                'Absent',
                'Attendance %',
                'Punctuality Status',
            ], escape: '\\');

            foreach ($summaries as $index => $row) {
                $gender = $row['gender'] ?? null;
                $genderLabel = $gender instanceof Gender
                    ? $gender->label()
                    : ($gender ? str((string) ($gender instanceof \BackedEnum ? $gender->value : $gender))->headline() : '—');

                fputcsv($handle, [
                    $index + 1,
                    $row['staff_id'],
                    $row['name'],
                    $row['department_name'],
                    $row['designation'],
                    $genderLabel,
                    $row['total_days'],
                    $row['present_count'],
                    $row['late_count'],
                    $row['excused_count'],
                    $row['absent_count'],
                    $row['attendance_rate'].'%',
                    str($row['status_level'])->headline(),
                ], escape: '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
