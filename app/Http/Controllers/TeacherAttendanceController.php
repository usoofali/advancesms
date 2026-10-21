<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordTeacherAttendanceRequest;
use App\Models\AcademicSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAttendanceController extends Controller
{
    /**
     * Display the daily teacher attendance register.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user?->can('teacher_attendances.view') && ! $user?->can('teacher_attendances.take')) {
            abort(403, 'Unauthorized to access teacher attendance register.');
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

        // Date selection: default to today (or closest weekday if weekend)
        $defaultDate = now()->isWeekend() ? now()->next(Carbon::MONDAY)->toDateString() : now()->toDateString();
        $selectedDate = (string) $request->input('attendance_date', $defaultDate);
        $carbonDate = Carbon::parse($selectedDate);
        $isWeekend = $carbonDate->isWeekend();

        // Active Teachers
        $teachers = Teacher::query()
            ->with(['user:id,name,email,gender,avatar,is_active', 'department:id,name'])
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->get()
            ->sortBy(fn ($t) => $t->user?->name ?? '')
            ->values();

        // Existing records for the selected date
        $existingAttendances = TeacherAttendance::query()
            ->where('attendance_date', $selectedDate)
            ->get()
            ->keyBy('teacher_id');

        $roster = [];
        $metrics = [
            'total' => $teachers->count(),
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'unrecorded' => 0,
            'attendance_rate' => 0.0,
        ];

        foreach ($teachers as $teacher) {
            $attendance = $existingAttendances->get($teacher->id);
            $statusVal = $attendance ? $attendance->status->value : null;

            $roster[] = [
                'teacher_id' => $teacher->id,
                'staff_id' => $teacher->staff_id,
                'name' => $teacher->user->name ?? 'Unknown',
                'email' => $teacher->user->email ?? '',
                'gender' => $teacher->user->gender ?? null,
                'avatar' => $teacher->user->avatar ?? null,
                'department_name' => $teacher->department->name ?? 'General',
                'designation' => $teacher->designation ?? 'Teacher',
                'qualification' => $teacher->qualification ?? '',
                'attendance_id' => $attendance?->id,
                'status' => $statusVal,
                'remark' => $attendance?->remark ?? '',
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

        $recordedCount = $metrics['present'] + $metrics['late'] + $metrics['excused'] + $metrics['absent'];
        $metrics['attendance_rate'] = $recordedCount > 0
            ? round((($metrics['present'] + $metrics['late']) / $recordedCount) * 100, 1)
            : 0.0;

        return Inertia::render('people/teachers/attendance/Index', [
            'sessions' => $sessions,
            'selectedSessionId' => $selectedSessionId,
            'selectedTermId' => $selectedTermId,
            'selectedDate' => $selectedDate,
            'isWeekend' => $isWeekend,
            'roster' => $roster,
            'metrics' => $metrics,
            'canRecord' => (bool) $user?->can('teacher_attendances.take'),
            'canUpdate' => (bool) $user?->can('teacher_attendances.update'),
            'canDelete' => (bool) $user?->can('teacher_attendances.delete'),
        ]);
    }

    /**
     * Batch store or update teacher daily attendance.
     */
    public function store(RecordTeacherAttendanceRequest $request): JsonResponse|RedirectResponse
    {
        /** @var array{attendance_date: string, academic_session_id: int, term_id?: int|null, records: array<array{teacher_id: int, status: string, remark?: string|null}>} $validated */
        $validated = $request->validated();

        $date = $validated['attendance_date'];
        $sessionId = $validated['academic_session_id'];
        $termId = $validated['term_id'] ?? null;
        $records = $validated['records'];
        $userId = $request->user()?->id;

        DB::transaction(function () use ($date, $sessionId, $termId, $records, $userId) {
            foreach ($records as $record) {
                TeacherAttendance::updateOrCreate(
                    [
                        'teacher_id' => $record['teacher_id'],
                        'attendance_date' => $date,
                    ],
                    [
                        'academic_session_id' => $sessionId,
                        'term_id' => $termId,
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
                'message' => 'Teacher attendance recorded successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Teacher attendance recorded successfully.');
    }

    /**
     * Delete teacher attendance records for a specific date.
     */
    public function destroy(Request $request, string $date): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user?->can('teacher_attendances.delete')) {
            abort(403, 'Unauthorized to delete teacher attendance records.');
        }

        TeacherAttendance::where('attendance_date', $date)->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Teacher attendance records for {$date} have been removed.",
            ]);
        }

        return redirect()->back()->with('success', "Teacher attendance records for {$date} have been removed.");
    }
}
