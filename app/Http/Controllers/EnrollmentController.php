<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\PromoteStudentsRequest;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentController extends Controller
{
    /**
     * Display a listing of student enrollments and promotion manager.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('enrollments.view')) {
            abort(403, 'Unauthorized to view student enrollments.');
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
                'student:id,user_id,admission_number,guardian_id',
                'student.user:id,name,gender,email,phone,is_active',
                'student.guardian:id,name,phone,relationship',
                'schoolClass:id,name,stage',
                'section:id,name,capacity',
                'academicSession:id,name,is_current',
                'previousEnrollment.schoolClass:id,name',
                'previousEnrollment.section:id,name',
                'previousEnrollment.academicSession:id,name',
            ])
            ->withCount(['marks', 'attendances'])
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->orderBy('roll_number')
            ->get();

        $classes = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order')
            ->get(['id', 'name', 'stage']);

        // Students not yet enrolled in the selected session
        $unenrolledStudents = Student::query()
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('academic_session_id', $selectedSessionId))
            ->with(['user:id,name,gender'])
            ->orderBy('admission_number')
            ->get(['id', 'user_id', 'admission_number']);

        $stats = [
            'total_enrolled' => $enrollments->count(),
            'active_count' => $enrollments->where('status', EnrollmentStatus::ACTIVE)->count(),
            'promoted_count' => $enrollments->where('status', EnrollmentStatus::PROMOTED)->count(),
            'repeated_count' => $enrollments->where('status', EnrollmentStatus::REPEATED)->count(),
            'graduated_count' => $enrollments->where('status', EnrollmentStatus::GRADUATED)->count(),
        ];

        if ($request->query('export') === 'csv') {
            $selectedSession = $sessions->firstWhere('id', $selectedSessionId);

            return $this->exportCsv($enrollments, $selectedSession?->name ?? 'Session');
        }

        return Inertia::render('academic/enrollments/Index', [
            'enrollments' => $enrollments,
            'sessions' => $sessions,
            'selectedSessionId' => $selectedSessionId,
            'classes' => $classes,
            'unenrolledStudents' => $unenrolledStudents,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created enrollment in storage.
     */
    public function store(StoreEnrollmentRequest $request): RedirectResponse
    {
        /** @var array{student_id: int, academic_session_id: int, class_id: int, section_id: int, roll_number?: string|null} $validated */
        $validated = $request->validated();

        $section = Section::findOrFail($validated['section_id']);
        if ($section->isFullForSession($validated['academic_session_id'])) {
            return redirect()->back()->withErrors([
                'section_id' => "Section '{$section->name}' has reached its maximum capacity of {$section->capacity} students for this academic session.",
            ]);
        }

        $previousEnrollment = Enrollment::query()
            ->where('student_id', $validated['student_id'])
            ->where('academic_session_id', '!=', $validated['academic_session_id'])
            ->latest('id')
            ->first();

        $enrollment = Enrollment::create([
            'student_id' => $validated['student_id'],
            'previous_enrollment_id' => $previousEnrollment?->id,
            'academic_session_id' => $validated['academic_session_id'],
            'class_id' => $validated['class_id'],
            'section_id' => $validated['section_id'],
            'roll_number' => $validated['roll_number'] ?? null,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $studentName = $enrollment->student->user->name;
        $className = $enrollment->schoolClass->name;
        $sectionName = $enrollment->section->name;

        return redirect()->back()->with('success', "Student '{$studentName}' successfully enrolled into {$className} - Section {$sectionName}.");
    }

    /**
     * Update the specified enrollment in storage (e.g., section transfer or status change).
     */
    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment): RedirectResponse
    {
        /** @var array{class_id: int, section_id: int, roll_number?: string|null, status: string} $validated */
        $validated = $request->validated();

        $enrollment->update([
            'class_id' => $validated['class_id'],
            'section_id' => $validated['section_id'],
            'roll_number' => $validated['roll_number'] ?? null,
            'status' => EnrollmentStatus::from($validated['status']),
        ]);

        $studentName = $enrollment->student->user->name;

        return redirect()->back()->with('success', "Enrollment record for '{$studentName}' updated successfully.");
    }

    /**
     * Remove the specified enrollment from storage.
     */
    public function destroy(Request $request, Enrollment $enrollment): RedirectResponse
    {
        if (! $request->user()?->can('enrollments.delete')) {
            abort(403, 'Unauthorized to delete enrollment record.');
        }

        if (! $enrollment->canBeDeleted()) {
            return redirect()->back()->withErrors([
                'error' => "Cannot remove enrollment for '{$enrollment->student->user->name}' because examination marks or attendance entries are recorded for this session.",
            ]);
        }

        $studentName = $enrollment->student->user->name;
        $enrollment->delete();

        return redirect()->back()->with('success', "Enrollment record for '{$studentName}' deleted successfully.");
    }

    /**
     * Execute an administrative, management-initiated batch progression / promotion.
     */
    public function promote(PromoteStudentsRequest $request, PromotionController $promotionController): RedirectResponse
    {
        return $promotionController->store($request);
    }

    /**
     * Stream CSV export of student enrollment roster.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     */
    protected function exportCsv($enrollments, string $sessionName): StreamedResponse
    {
        $filename = sprintf('student-enrollment-roster-%s.csv', str($sessionName)->slug());

        return response()->stream(function () use ($enrollments, $sessionName) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Microsoft Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Student Enrollment Roster'], escape: '\\');
            fputcsv($handle, ['Session', $sessionName, 'Total Enrolled', $enrollments->count(), 'Exported At', now()->toDateTimeString()], escape: '\\');
            fputcsv($handle, [], escape: '\\');

            fputcsv($handle, [
                '#',
                'Admission No',
                'Student Name',
                'Gender',
                'Class',
                'Section',
                'Roll Number',
                'Status',
                'Guardian Name',
                'Guardian Phone',
                'Guardian Relationship',
            ], escape: '\\');

            $idx = 1;
            foreach ($enrollments as $enrollment) {
                $statusLabel = $enrollment->status instanceof EnrollmentStatus
                    ? $enrollment->status->label()
                    : ucfirst((string) $enrollment->status);

                fputcsv($handle, [
                    $idx++,
                    $enrollment->student->admission_number ?? 'N/A',
                    $enrollment->student->user->name ?? 'N/A',
                    ucfirst((string) ($enrollment->student->user->gender?->value ?? $enrollment->student->user->gender ?? 'N/A')),
                    $enrollment->schoolClass->name ?? 'N/A',
                    $enrollment->section->name ?? 'N/A',
                    $enrollment->roll_number ?? 'N/A',
                    $statusLabel,
                    $enrollment->student->guardian?->name ?? 'N/A',
                    $enrollment->student->guardian?->phone ?? 'N/A',
                    $enrollment->student->guardian?->relationship ?? 'N/A',
                ], escape: '\\');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
