<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\PromoteStudentsRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
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

        $enrollments = Enrollment::query()
            ->where('academic_session_id', $selectedSessionId)
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
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->orderBy('roll_number')
            ->get();

        $classes = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order')
            ->get(['id', 'name', 'stage', 'level_order']);

        $stats = [
            'total_in_session' => $enrollments->count(),
            'active_count' => $enrollments->where('status', EnrollmentStatus::ACTIVE)->count(),
            'promoted_count' => $enrollments->where('status', EnrollmentStatus::PROMOTED)->count(),
            'repeated_count' => $enrollments->where('status', EnrollmentStatus::REPEATED)->count(),
            'graduated_count' => $enrollments->where('status', EnrollmentStatus::GRADUATED)->count(),
        ];

        return Inertia::render('academic/promotions/Index', [
            'sessions' => $sessions,
            'selectedSessionId' => $selectedSessionId,
            'classes' => $classes,
            'enrollments' => $enrollments,
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
