<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Http\Requests\StoreTeacherRequest;
use App\Http\Requests\UpdateTeacherRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherController extends Controller
{
    /**
     * Display a listing of teachers and staff members.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        if (! $request->user()?->can('teachers.view')) {
            abort(403, 'Unauthorized to view academic staff directory.');
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv();
        }

        $teachers = Teacher::query()
            ->with([
                'user:id,name,username,email,first_name,last_name,phone,gender,role,is_active',
                'user.roles:id,name,display_name',
                'department:id,name',
            ])
            ->withCount('subjectAssignments')
            ->orderBy('staff_id')
            ->get();

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $roles = Role::whereNotIn('name', ['student', 'guardian'])
            ->orderBy('name')
            ->get(['id', 'name', 'display_name']);

        $totalStaff = $teachers->count();
        $totalTeachers = $teachers->filter(function ($t) {
            $userRole = $t->user?->role instanceof UserRole ? $t->user->role->value : (string) $t->user?->role;

            return $t->user?->roles?->contains('name', 'teacher') || $userRole === 'teacher';
        })->count();

        $stats = [
            'total_staff' => $totalStaff,
            'total_teachers' => $totalTeachers,
            'total_other_staff' => $totalStaff - $totalTeachers,
            'active_staff' => $teachers->filter(fn ($t) => $t->user?->is_active)->count(),
            'total_departments' => $departments->count(),
            'total_assignments' => TeacherSubjectAssignment::count(),
        ];

        return Inertia::render('people/teachers/Index', [
            'teachers' => $teachers,
            'departments' => $departments,
            'roles' => $roles,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly registered teacher in storage.
     */
    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        /** @var array{first_name: string, last_name: string, email?: string|null, phone?: string|null, gender: string, role?: string|null, staff_id: string, department_id?: int|null, designation?: string|null, qualification?: string|null, joining_date?: string|null, password?: string|null, username?: string|null} $validated */
        $validated = $request->validated();

        $fullName = trim("{$validated['first_name']} {$validated['last_name']}");
        $username = $validated['username'] ?? strtolower(Str::slug($validated['staff_id'], '_'));
        $password = $validated['password'] ?? '12345678';
        $roleName = $validated['role'] ?? UserRole::TEACHER->value;

        DB::transaction(function () use ($validated, $fullName, $username, $password, $roleName) {
            $user = User::create([
                'username' => $username,
                'name' => $fullName,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'gender' => Gender::from($validated['gender']),
                'role' => $roleName,
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$roleName]);

            Teacher::create([
                'user_id' => $user->id,
                'staff_id' => $validated['staff_id'],
                'department_id' => $validated['department_id'] ?? null,
                'designation' => $validated['designation'] ?? 'Subject Teacher',
                'qualification' => $validated['qualification'] ?? null,
                'joining_date' => $validated['joining_date'] ?? now()->toDateString(),
            ]);
        });

        return redirect()->back()->with('success', "Staff member '{$fullName}' ({$validated['staff_id']}) registered successfully.");
    }

    /**
     * Update the specified teacher's profile.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        /** @var array{first_name: string, last_name: string, email?: string|null, phone?: string|null, gender: string, role?: string|null, staff_id: string, department_id?: int|null, designation?: string|null, qualification?: string|null, joining_date?: string|null, is_active?: bool, password?: string|null} $validated */
        $validated = $request->validated();

        $fullName = trim("{$validated['first_name']} {$validated['last_name']}");

        DB::transaction(function () use ($validated, $fullName, $teacher) {
            $user = $teacher->user;

            $userUpdates = [
                'name' => $fullName,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'gender' => Gender::from($validated['gender']),
            ];

            if (! empty($validated['role'])) {
                $userUpdates['role'] = $validated['role'];
                $user->syncRoles([$validated['role']]);
            }

            if (isset($validated['is_active'])) {
                $userUpdates['is_active'] = $validated['is_active'];
            }

            if (! empty($validated['password'])) {
                $userUpdates['password'] = Hash::make($validated['password']);
            }

            $user->update($userUpdates);

            $teacher->update([
                'staff_id' => $validated['staff_id'],
                'department_id' => $validated['department_id'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'qualification' => $validated['qualification'] ?? null,
                'joining_date' => $validated['joining_date'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', "Staff profile for '{$fullName}' updated successfully.");
    }

    /**
     * Directly reset a staff member's password.
     */
    public function resetPassword(Request $request, Teacher $teacher): RedirectResponse
    {
        if (! $request->user()?->can('teachers.update')) {
            abort(403, 'Unauthorized to reset staff passwords.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ]);

        $teacher->user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', "Password for '{$teacher->user->name}' ({$teacher->staff_id}) reset successfully.");
    }

    /**
     * Remove the specified teacher from storage.
     */
    public function destroy(Request $request, Teacher $teacher): RedirectResponse
    {
        if (! $request->user()?->can('teachers.delete')) {
            abort(403, 'Unauthorized to delete teachers.');
        }

        if (! $teacher->canBeDeleted()) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete staff member '{$teacher->user->name}' because they have {$teacher->subjectAssignments()->count()} active subject teaching allocation(s).",
            ]);
        }

        $name = $teacher->user->name;
        $user = $teacher->user;

        DB::transaction(function () use ($teacher, $user) {
            $teacher->delete();
            $user->delete();
        });

        return redirect()->back()->with('success', "Staff member '{$name}' deleted successfully.");
    }

    /**
     * Export teaching and administrative staff roster as CSV with UTF-8 BOM.
     */
    protected function exportCsv(): StreamedResponse
    {
        $fileName = 'staff_directory_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // School & Metadata Header
            $schoolName = config('app.name', 'AdvanceSMS');
            fputcsv($handle, [$schoolName.' - Teachers & Staff Directory']);
            fputcsv($handle, ['Generated: '.now()->toDayDateTimeString()]);
            fputcsv($handle, []);

            // Column Headers
            fputcsv($handle, [
                'Staff ID',
                'Full Name',
                'Username',
                'Email',
                'Phone',
                'Gender',
                'Primary Role',
                'Department',
                'Designation',
                'Qualification',
                'Active Status',
                'Joining Date',
            ]);

            Teacher::query()
                ->with([
                    'user:id,name,username,email,phone,gender,role,is_active',
                    'user.roles:id,name,display_name',
                    'department:id,name',
                ])
                ->orderBy('staff_id')
                ->chunk(100, function ($teachers) use ($handle) {
                    foreach ($teachers as $teacher) {
                        $roleName = $teacher->user?->roles?->first()?->display_name
                            ?? (is_string($teacher->user?->role) ? ucfirst($teacher->user->role) : 'Teacher');

                        $gender = $teacher->user?->gender;
                        $genderVal = is_object($gender) && isset($gender->value) ? $gender->value : (string) $gender;

                        fputcsv($handle, [
                            $teacher->staff_id,
                            $teacher->user?->name ?? '—',
                            $teacher->user?->username ?? '—',
                            $teacher->user?->email ?? '—',
                            $teacher->user?->phone ?? '—',
                            ucfirst($genderVal ?: '—'),
                            $roleName,
                            $teacher->department?->name ?? 'None',
                            $teacher->designation ?? '—',
                            $teacher->qualification ?? '—',
                            $teacher->user?->is_active ? 'Active' : 'Inactive',
                            $teacher->joining_date?->format('Y-m-d') ?? '—',
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
