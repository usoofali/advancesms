<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\AcademicSession;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentMark;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    /**
     * Display a listing of students.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        if (! $request->user()?->can('students.view')) {
            abort(403, 'Unauthorized to view student directory.');
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($request);
        }

        $sessions = AcademicSession::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_current']);

        $activeSession = $sessions->firstWhere('is_current', true) ?? $sessions->first();

        $students = Student::query()
            ->with([
                'user:id,name,username,email,first_name,last_name,phone,gender,is_active',
                'guardian:id,name,phone,relationship',
                'currentEnrollment' => fn ($query) => $query->with([
                    'schoolClass:id,name,stage',
                    'section:id,name,capacity',
                    'academicSession:id,name,is_current',
                ]),
                'enrollments' => fn ($query) => $query->with([
                    'schoolClass:id,name,stage',
                    'section:id,name,capacity',
                    'academicSession:id,name,is_current',
                ]),
            ])
            ->withCount(['studentMarks', 'attendances', 'enrollments'])
            ->orderBy('admission_number')
            ->get();

        $classes = SchoolClass::query()
            ->with(['sections:id,class_id,name,capacity'])
            ->orderBy('level_order')
            ->get(['id', 'name', 'stage']);

        $guardians = Guardian::query()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'relationship']);

        $stats = [
            'total_students' => $students->count(),
            'active_students' => $students->filter(fn ($s) => $s->user->is_active)->count(),
            'male_students' => $students->filter(fn ($s) => $s->user->gender === Gender::MALE)->count(),
            'female_students' => $students->filter(fn ($s) => $s->user->gender === Gender::FEMALE)->count(),
            'enrolled_students' => $students->filter(fn ($s) => $s->currentEnrollment !== null)->count(),
        ];

        return Inertia::render('people/students/Index', [
            'students' => $students,
            'classes' => $classes,
            'guardians' => $guardians,
            'sessions' => $sessions,
            'activeSession' => $activeSession,
            'nextAdmissionNumber' => Student::generateNextAdmissionNumber(),
            'defaultStudentPassword' => '12345678',
            'stats' => $stats,
        ]);
    }

    /**
     * Display the specified student profile with comprehensive academic and bio records.
     */
    public function show(Request $request, Student $student): Response
    {
        if (! $request->user()?->can('students.view')) {
            abort(403, 'Unauthorized to view student profile.');
        }

        $student->load([
            'user:id,name,username,email,first_name,middle_name,last_name,phone,gender,blood_group,address,avatar,is_active,created_at',
            'guardian:id,name,phone,relationship,email,occupation,address',
            'enrollments' => fn ($q) => $q->with([
                'academicSession:id,name,is_current',
                'schoolClass:id,name,stage',
                'section:id,name,capacity',
                'previousEnrollment.schoolClass:id,name',
                'previousEnrollment.section:id,name',
                'previousEnrollment.academicSession:id,name',
            ])->orderByDesc('id'),
            'currentEnrollment' => fn ($q) => $q->with([
                'academicSession:id,name,is_current',
                'schoolClass:id,name,stage',
                'section:id,name,capacity',
                'previousEnrollment.schoolClass:id,name',
                'previousEnrollment.section:id,name',
                'previousEnrollment.academicSession:id,name',
            ]),
        ]);

        $enrollmentIds = $student->enrollments->pluck('id');

        // Load marks across student enrollments with subjects and grades
        $marks = StudentMark::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->with([
                'subject:id,name,code',
                'grade:id,name,grade_point,remark',
                'enrollment' => fn ($q) => $q->with(['academicSession:id,name', 'schoolClass:id,name', 'section:id,name']),
            ])
            ->latest('id')
            ->get();

        // Load attendance summary and recent records
        $attendances = DailyAttendance::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->with('enrollment.academicSession:id,name')
            ->orderByDesc('attendance_date')
            ->get();

        $totalAttendanceCount = $attendances->count();
        $presentCount = $attendances->filter(fn ($a) => $a->status === AttendanceStatus::PRESENT)->count();
        $lateCount = $attendances->filter(fn ($a) => $a->status === AttendanceStatus::LATE)->count();
        $absentCount = $attendances->filter(fn ($a) => $a->status === AttendanceStatus::ABSENT)->count();
        $attendanceRate = $totalAttendanceCount > 0
            ? round((($presentCount + ($lateCount * 0.5)) / $totalAttendanceCount) * 100, 1)
            : 100.0;

        $attendanceStats = [
            'total' => $totalAttendanceCount,
            'present' => $presentCount,
            'late' => $lateCount,
            'absent' => $absentCount,
            'rate' => $attendanceRate,
        ];

        return Inertia::render('people/students/Show', [
            'student' => $student,
            'marks' => $marks,
            'attendances' => $attendances->take(30)->values(),
            'attendanceStats' => $attendanceStats,
        ]);
    }

    /**
     * Store a newly created student in storage.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        /** @var array{first_name: string, last_name: string, gender: string, email?: string|null, phone?: string|null, username?: string|null, password?: string|null, admission_number?: string|null, admission_date: string, date_of_birth?: string|null, state_of_origin?: string|null, lga?: string|null, guardian_id?: int|null, class_id?: int|null, section_id?: int|null, roll_number?: string|null} $validated */
        $validated = $request->validated();

        $admissionNumber = ! empty($validated['admission_number'])
            ? trim($validated['admission_number'])
            : Student::generateNextAdmissionNumber();

        $fullName = trim("{$validated['first_name']} {$validated['last_name']}");
        $username = ! empty($validated['username'])
            ? trim($validated['username'])
            : Student::generateStudentUsername($admissionNumber);
        $email = ! empty($validated['email'])
            ? trim($validated['email'])
            : Student::generateStudentEmail($admissionNumber);
        $password = ! empty($validated['password'])
            ? $validated['password']
            : '12345678';

        $activeSession = AcademicSession::where('is_current', true)->first();

        // Enforce section capacity check if class & section provided
        if (! empty($validated['section_id']) && $activeSession !== null) {
            $section = Section::findOrFail($validated['section_id']);
            if ($section->isFullForSession($activeSession->id)) {
                return redirect()->back()->withErrors([
                    'section_id' => "Section '{$section->name}' has reached its maximum capacity of {$section->capacity} students for this academic session.",
                ]);
            }
        }

        DB::transaction(function () use ($validated, $fullName, $username, $email, $password, $admissionNumber, $activeSession) {
            $user = User::create([
                'username' => $username,
                'name' => $fullName,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'gender' => Gender::from($validated['gender']),
                'role' => UserRole::STUDENT,
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole(UserRole::STUDENT->value);

            $student = Student::create([
                'user_id' => $user->id,
                'guardian_id' => $validated['guardian_id'] ?? null,
                'admission_number' => $admissionNumber,
                'admission_date' => $validated['admission_date'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'state_of_origin' => $validated['state_of_origin'] ?? null,
                'lga' => $validated['lga'] ?? null,
            ]);

            // Automatic immediate session enrollment if class and section provided
            if (! empty($validated['class_id']) && ! empty($validated['section_id']) && $activeSession !== null) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'academic_session_id' => $activeSession->id,
                    'class_id' => $validated['class_id'],
                    'section_id' => $validated['section_id'],
                    'roll_number' => $validated['roll_number'] ?? $admissionNumber,
                    'status' => EnrollmentStatus::ACTIVE,
                ]);
            }
        });

        return redirect()->back()->with('success', "Student '{$fullName}' ({$admissionNumber}) admitted and registered successfully.");
    }

    /**
     * Update the specified student in storage.
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        /** @var array{first_name: string, last_name: string, gender: string, email?: string|null, phone?: string|null, username?: string|null, is_active?: bool|null, password?: string|null, admission_number: string, admission_date: string, date_of_birth?: string|null, state_of_origin?: string|null, lga?: string|null, guardian_id?: int|null, class_id?: int|null, section_id?: int|null} $validated */
        $validated = $request->validated();

        $fullName = trim("{$validated['first_name']} {$validated['last_name']}");

        DB::transaction(function () use ($validated, $fullName, $student) {
            $user = $student->user;

            $userUpdates = [
                'name' => $fullName,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'gender' => Gender::from($validated['gender']),
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ];

            if (! empty($validated['username'])) {
                $userUpdates['username'] = trim($validated['username']);
            }

            if (isset($validated['is_active'])) {
                $userUpdates['is_active'] = (bool) $validated['is_active'];
            }

            if (! empty($validated['password'])) {
                $userUpdates['password'] = Hash::make($validated['password']);
            }

            $user->update($userUpdates);

            $student->update([
                'admission_number' => $validated['admission_number'],
                'admission_date' => $validated['admission_date'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'state_of_origin' => $validated['state_of_origin'] ?? null,
                'lga' => $validated['lga'] ?? null,
                'guardian_id' => $validated['guardian_id'] ?? null,
            ]);

            // Synchronize active session enrollment if class and section provided
            if (! empty($validated['class_id']) && ! empty($validated['section_id'])) {
                $activeSession = AcademicSession::where('is_current', true)->first();

                if ($activeSession !== null) {
                    $enrollment = Enrollment::where('student_id', $student->id)
                        ->where('academic_session_id', $activeSession->id)
                        ->first();

                    if ($enrollment) {
                        $enrollment->update([
                            'class_id' => $validated['class_id'],
                            'section_id' => $validated['section_id'],
                            'roll_number' => $validated['admission_number'],
                        ]);
                    } else {
                        Enrollment::create([
                            'student_id' => $student->id,
                            'academic_session_id' => $activeSession->id,
                            'class_id' => $validated['class_id'],
                            'section_id' => $validated['section_id'],
                            'roll_number' => $validated['admission_number'],
                            'status' => EnrollmentStatus::ACTIVE,
                        ]);
                    }
                }
            }
        });

        return redirect()->back()->with('success', "Student profile for '{$fullName}' updated successfully.");
    }

    /**
     * Remove the specified student from storage.
     */
    public function destroy(Request $request, Student $student): RedirectResponse
    {
        if (! $request->user()?->can('students.delete')) {
            abort(403, 'Unauthorized to delete students.');
        }

        if (! $student->canBeDeleted()) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete student '{$student->user->name}' because active continuous assessment scores or attendance registers are tied to this profile.",
            ]);
        }

        $name = $student->user->name;
        $user = $student->user;

        DB::transaction(function () use ($student, $user) {
            $student->enrollments()->delete();
            $student->delete();
            $user->delete();
        });

        return redirect()->back()->with('success', "Student '{$name}' deleted successfully.");
    }

    /**
     * Export student directory roster as CSV with UTF-8 BOM.
     */
    protected function exportCsv(?Request $request = null): StreamedResponse
    {
        $fileName = 'students_directory_'.now()->format('Y-m-d_His').'.csv';

        $query = Student::query()
            ->with([
                'user:id,name,username,email,phone,gender,is_active',
                'guardian:id,name,phone,relationship',
                'currentEnrollment.schoolClass:id,name',
                'currentEnrollment.section:id,name',
                'currentEnrollment.academicSession:id,name',
            ])
            ->orderBy('admission_number');

        if ($request?->filled('session_id') && $request->session_id !== 'all') {
            $query->whereHas('enrollments', fn ($q) => $q->where('academic_session_id', $request->session_id));
        }

        if ($request?->filled('class_id') && $request->class_id !== 'all') {
            $query->whereHas('enrollments', fn ($q) => $q->where('class_id', $request->class_id));
        }

        if ($request?->filled('gender') && $request->gender !== 'all') {
            $query->whereHas('user', fn ($q) => $q->where('gender', $request->gender));
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // School & Metadata Header
            $schoolName = config('app.name', 'AdvanceSMS');
            fputcsv($handle, [$schoolName.' - Students Directory']);
            fputcsv($handle, ['Generated: '.now()->toDayDateTimeString()]);
            fputcsv($handle, []);

            // Column Headers
            fputcsv($handle, [
                'Admission Number',
                'Full Name',
                'Gender',
                'Current Class',
                'Current Section',
                'Current Session',
                'Guardian Name',
                'Guardian Phone',
                'Guardian Relationship',
                'Date of Birth',
                'State of Origin',
                'Status',
                'Admission Date',
            ]);

            $query->chunk(100, function ($students) use ($handle) {
                foreach ($students as $student) {
                    $gender = $student->user?->gender;
                    $genderVal = is_object($gender) && isset($gender->value) ? $gender->value : (string) $gender;

                    fputcsv($handle, [
                        $student->admission_number,
                        $student->user?->name ?? '—',
                        ucfirst($genderVal ?: '—'),
                        $student->currentEnrollment?->schoolClass?->name ?? 'Unassigned',
                        $student->currentEnrollment?->section?->name ?? '—',
                        $student->currentEnrollment?->academicSession?->name ?? '—',
                        $student->guardian?->name ?? '—',
                        $student->guardian?->phone ?? '—',
                        $student->guardian?->relationship ? ucfirst($student->guardian->relationship) : '—',
                        $student->date_of_birth?->format('Y-m-d') ?? '—',
                        $student->state_of_origin ?? '—',
                        $student->user?->is_active ? 'Active' : 'Inactive',
                        $student->admission_date?->format('Y-m-d') ?? '—',
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
