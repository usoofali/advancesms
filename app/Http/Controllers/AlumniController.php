<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlumniController extends Controller
{
    /**
     * Display a listing of graduated alumni students.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        if (! $request->user()?->can('students.view')) {
            abort(403, 'Unauthorized to view alumni directory.');
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($request);
        }

        $sessions = AcademicSession::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_current']);

        $classes = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order')
            ->get(['id', 'name', 'stage']);

        $alumniQuery = Student::query()
            ->whereHas('enrollments', function ($query) {
                $query->where('status', EnrollmentStatus::GRADUATED);
            })
            ->with([
                'user:id,name,username,email,first_name,last_name,phone,gender,is_active,avatar',
                'guardian:id,name,phone,relationship',
                'graduatingEnrollment' => fn ($query) => $query->with([
                    'schoolClass:id,name,stage',
                    'section:id,name,capacity',
                    'academicSession:id,name,is_current',
                ]),
                'currentEnrollment' => fn ($query) => $query->with([
                    'schoolClass:id,name,stage',
                    'section:id,name,capacity',
                    'academicSession:id,name,is_current',
                ]),
            ])
            ->withCount(['studentMarks', 'attendances', 'enrollments'])
            ->orderBy('admission_number');

        if ($request->filled('session_id') && $request->session_id !== 'all') {
            $alumniQuery->whereHas('enrollments', function ($query) use ($request) {
                $query->where('status', EnrollmentStatus::GRADUATED)
                    ->where('academic_session_id', $request->session_id);
            });
        }

        if ($request->filled('class_id') && $request->class_id !== 'all') {
            $alumniQuery->whereHas('enrollments', function ($query) use ($request) {
                $query->where('status', EnrollmentStatus::GRADUATED)
                    ->where('class_id', $request->class_id);
            });
        }

        if ($request->filled('gender') && $request->gender !== 'all') {
            $alumniQuery->whereHas('user', function ($query) use ($request) {
                $query->where('gender', $request->gender);
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $alumniQuery->where(function ($q) use ($search) {
                $q->where('admission_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        $alumni = $alumniQuery->get();

        // Overall stats across all graduated alumni
        $allGraduatedEnrollments = Enrollment::query()
            ->where('status', EnrollmentStatus::GRADUATED)
            ->get(['id', 'student_id', 'academic_session_id']);

        $allAlumni = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->where('status', EnrollmentStatus::GRADUATED))
            ->with('user:id,gender')
            ->get();

        $stats = [
            'total_alumni' => $allAlumni->count(),
            'male_alumni' => $allAlumni->filter(fn ($s) => $s->user?->gender === Gender::MALE)->count(),
            'female_alumni' => $allAlumni->filter(fn ($s) => $s->user?->gender === Gender::FEMALE)->count(),
            'graduating_batches' => $allGraduatedEnrollments->pluck('academic_session_id')->unique()->count(),
        ];

        return Inertia::render('people/alumni/Index', [
            'alumni' => $alumni,
            'sessions' => $sessions,
            'classes' => $classes,
            'stats' => $stats,
            'filters' => [
                'session_id' => $request->query('session_id', 'all'),
                'class_id' => $request->query('class_id', 'all'),
                'gender' => $request->query('gender', 'all'),
                'search' => $request->query('search', ''),
            ],
        ]);
    }

    /**
     * Export graduated alumni list as CSV with UTF-8 BOM.
     */
    protected function exportCsv(Request $request): StreamedResponse
    {
        $fileName = 'alumni_directory_'.now()->format('Y-m-d_His').'.csv';

        $query = Student::query()
            ->whereHas('enrollments', function ($q) {
                $q->where('status', EnrollmentStatus::GRADUATED);
            })
            ->with([
                'user:id,name,username,email,phone,gender,is_active',
                'guardian:id,name,phone,relationship',
                'graduatingEnrollment' => fn ($q) => $q->with([
                    'schoolClass:id,name',
                    'section:id,name',
                    'academicSession:id,name',
                ]),
            ])
            ->orderBy('admission_number');

        if ($request->filled('session_id') && $request->session_id !== 'all') {
            $query->whereHas('enrollments', function ($q) use ($request) {
                $q->where('status', EnrollmentStatus::GRADUATED)
                    ->where('academic_session_id', $request->session_id);
            });
        }

        if ($request->filled('class_id') && $request->class_id !== 'all') {
            $query->whereHas('enrollments', function ($q) use ($request) {
                $q->where('status', EnrollmentStatus::GRADUATED)
                    ->where('class_id', $request->class_id);
            });
        }

        if ($request->filled('gender') && $request->gender !== 'all') {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('gender', $request->gender);
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('admission_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // School & Metadata Header
            $schoolName = config('app.name', 'AdvanceSMS');
            fputcsv($handle, [$schoolName.' - Alumni Directory & Graduated Register']);
            fputcsv($handle, ['Generated: '.now()->toDayDateTimeString()]);
            fputcsv($handle, []);

            // Column Headers
            fputcsv($handle, [
                'Admission Number',
                'Full Name',
                'Gender',
                'Graduation Class',
                'Graduation Section',
                'Graduation Session / Batch',
                'Guardian Name',
                'Guardian Phone',
                'Guardian Relationship',
                'Student Phone',
                'Student Email',
                'State of Origin',
                'Admission Date',
            ]);

            $query->chunk(100, function ($alumniList) use ($handle) {
                foreach ($alumniList as $alumnus) {
                    $gender = $alumnus->user?->gender;
                    $genderVal = is_object($gender) && isset($gender->value) ? $gender->value : (string) $gender;
                    $gradEnrollment = $alumnus->graduatingEnrollment ?? $alumnus->currentEnrollment;

                    fputcsv($handle, [
                        $alumnus->admission_number,
                        $alumnus->user?->name ?? '—',
                        ucfirst($genderVal ?: '—'),
                        $gradEnrollment?->schoolClass?->name ?? '—',
                        $gradEnrollment?->section?->name ?? '—',
                        $gradEnrollment?->academicSession?->name ?? '—',
                        $alumnus->guardian?->name ?? '—',
                        $alumnus->guardian?->phone ?? '—',
                        $alumnus->guardian?->relationship ? ucfirst($alumnus->guardian->relationship) : '—',
                        $alumnus->user?->phone ?? '—',
                        $alumnus->user?->email ?? '—',
                        $alumnus->state_of_origin ?? '—',
                        $alumnus->admission_date?->format('Y-m-d') ?? '—',
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
