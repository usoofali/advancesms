<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\RecordDailyAttendanceRequest;
use App\Models\AcademicSession;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TeacherSubjectAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DailyAttendanceController extends Controller
{
    /**
     * Display the daily attendance register and roster.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user?->can('daily_attendances.take') && ! $user?->can('daily_attendances.update')) {
            abort(403, 'Unauthorized to access daily attendance register.');
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

        // Date selection (default today)
        $selectedDate = (string) $request->input('attendance_date', now()->toDateString());

        // Teacher scoping if logged in as teacher without global admin role
        $isTeacher = $user->hasRole('teacher') && ! $user->hasAnyRole(['superadmin', 'admin']);
        $teacher = $isTeacher ? Teacher::where('user_id', $user->id)->first() : null;

        // Classes & Sections
        $classesQuery = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order');

        if ($isTeacher && $teacher) {
            $assignedClassIds = TeacherSubjectAssignment::where('teacher_id', $teacher->id)
                ->where('academic_session_id', $selectedSessionId)
                ->where('can_take_attendance', true)
                ->pluck('class_id')
                ->unique();

            // If the teacher has specific attendance allocations, limit to those; otherwise show all
            if ($assignedClassIds->isNotEmpty()) {
                $classesQuery->whereIn('id', $assignedClassIds);
            }
        }

        $classes = $classesQuery->get(['id', 'name', 'stage', 'level_order']);

        $firstClass = $classes->first();
        $selectedClassId = (int) $request->input('class_id', $firstClass ? $firstClass->id : 0);

        $selectedClass = $classes->firstWhere('id', $selectedClassId) ?? $firstClass;
        $sections = $selectedClass ? $selectedClass->sections : collect();
        $firstSection = $sections->first();
        $selectedSectionId = (int) $request->input('section_id', $firstSection ? $firstSection->id : 0);

        // Student Roster & Attendance Records
        $roster = [];
        $metrics = [
            'total' => 0,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'unrecorded' => 0,
            'attendance_rate' => 0.0,
        ];

        if ($selectedSessionId && $selectedClassId && $selectedSectionId) {
            $enrollments = Enrollment::query()
                ->where('academic_session_id', $selectedSessionId)
                ->where('class_id', $selectedClassId)
                ->where('section_id', $selectedSectionId)
                ->where('status', EnrollmentStatus::ACTIVE)
                ->with(['student:id,user_id,admission_number', 'student.user:id,name,gender'])
                ->orderBy('roll_number')
                ->orderBy('id')
                ->get();

            $existingAttendances = DailyAttendance::query()
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->where('attendance_date', $selectedDate)
                ->get()
                ->keyBy('enrollment_id');

            foreach ($enrollments as $enrollment) {
                $attendance = $existingAttendances->get($enrollment->id);
                $statusVal = $attendance ? $attendance->status->value : null;

                $roster[] = [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'admission_number' => $enrollment->student->admission_number,
                    'student_name' => $enrollment->student->user->name,
                    'gender' => $enrollment->student->user->gender,
                    'roll_number' => $enrollment->roll_number,
                    'attendance_id' => $attendance ? $attendance->id : null,
                    'status' => $statusVal,
                    'remark' => $attendance ? ($attendance->remark ?? '') : '',
                ];

                if ($statusVal === 'present') {
                    $metrics['present']++;
                } elseif ($statusVal === 'absent') {
                    $metrics['absent']++;
                } elseif ($statusVal === 'late') {
                    $metrics['late']++;
                } elseif ($statusVal === 'excused') {
                    $metrics['excused']++;
                } else {
                    $metrics['unrecorded']++;
                }
            }

            $metrics['total'] = count($roster);
            $metrics['attendance_rate'] = $metrics['total'] > 0
                ? round((($metrics['present'] + $metrics['late']) / $metrics['total']) * 100, 1)
                : 0.0;
        }

        return Inertia::render('academic/attendance/Index', [
            'sessions' => $sessions,
            'classes' => $classes,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId,
            'selectedClassId' => $selectedClassId,
            'selectedSectionId' => $selectedSectionId,
            'selectedDate' => $selectedDate,
            'roster' => $roster,
            'metrics' => $metrics,
            'attendanceStatuses' => [
                ['value' => 'present', 'label' => 'Present'],
                ['value' => 'absent', 'label' => 'Absent'],
                ['value' => 'late', 'label' => 'Late'],
                ['value' => 'excused', 'label' => 'Excused'],
            ],
        ]);
    }

    /**
     * Batch store or update daily attendance records.
     */
    public function store(RecordDailyAttendanceRequest $request): JsonResponse|RedirectResponse
    {
        /** @var array{attendance_date: string, class_id: int, section_id: int, records: array<array{enrollment_id: int, status: string, remark?: string|null}>} $validated */
        $validated = $request->validated();

        $date = $validated['attendance_date'];
        $records = $validated['records'];
        $userId = $request->user()?->id;

        DB::transaction(function () use ($date, $records, $userId) {
            foreach ($records as $record) {
                DailyAttendance::updateOrCreate(
                    [
                        'enrollment_id' => $record['enrollment_id'],
                        'attendance_date' => $date,
                    ],
                    [
                        'status' => $record['status'],
                        'recorded_by' => $userId,
                        'remark' => $record['remark'] ?? null,
                    ]
                );
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Daily attendance recorded successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Daily attendance recorded successfully.');
    }
}
