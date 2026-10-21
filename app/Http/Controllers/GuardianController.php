<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Http\Requests\StoreGuardianRequest;
use App\Http\Requests\UpdateGuardianRequest;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    /**
     * Display a listing of guardians.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('guardians.view')) {
            abort(403, 'Unauthorized to view guardians directory.');
        }

        $guardians = Guardian::query()
            ->with([
                'user:id,name,username,email,phone,is_active',
                'students' => fn ($query) => $query->select('id', 'user_id', 'guardian_id', 'admission_number')
                    ->with([
                        'user:id,name',
                        'currentEnrollment.schoolClass:id,name',
                        'currentEnrollment.section:id,name',
                    ]),
            ])
            ->withCount('students')
            ->orderBy('name')
            ->get();

        $stats = [
            'total_guardians' => $guardians->count(),
            'total_linked_students' => $guardians->sum('students_count'),
            'portal_active_guardians' => $guardians->filter(fn ($g) => $g->user_id !== null)->count(),
        ];

        return Inertia::render('people/guardians/Index', [
            'guardians' => $guardians,
            'stats' => $stats,
        ]);
    }

    /**
     * Generate a unique username for a guardian portal user account.
     */
    private function generateUniqueUsername(string $name, ?string $requestedUsername = null): string
    {
        if (! empty($requestedUsername)) {
            return $requestedUsername;
        }

        $base = strtolower(Str::slug($name, '_'));
        if (empty($base)) {
            $base = 'guardian';
        }

        $username = $base.'_'.strtolower(Str::random(4));
        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.strtolower(Str::random(4));
        }

        return $username;
    }

    /**
     * Store a newly created guardian in storage.
     */
    public function store(StoreGuardianRequest $request): RedirectResponse
    {
        /** @var array{name: string, relationship: string, phone: string, email?: string|null, address?: string|null, occupation?: string|null, create_user_account?: bool|null, username?: string|null, password?: string|null} $validated */
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $userId = null;

            if (! empty($validated['create_user_account'])) {
                $username = $this->generateUniqueUsername($validated['name'], $validated['username'] ?? null);
                $password = $validated['password'] ?? 'password';

                $nameParts = explode(' ', trim($validated['name']), 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? $firstName;

                $user = User::create([
                    'username' => $username,
                    'name' => $validated['name'],
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'],
                    'gender' => Gender::MALE,
                    'role' => UserRole::GUARDIAN,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $user->assignRole(UserRole::GUARDIAN->value);
                $userId = $user->id;
            }

            Guardian::create([
                'user_id' => $userId,
                'name' => $validated['name'],
                'relationship' => $validated['relationship'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', "Guardian '{$validated['name']}' registered successfully.");
    }

    /**
     * Update the specified guardian in storage.
     */
    public function update(UpdateGuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        /** @var array{name: string, relationship: string, phone: string, email?: string|null, address?: string|null, occupation?: string|null, create_user_account?: bool|null, username?: string|null, password?: string|null} $validated */
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $guardian): void {
            $userId = $guardian->user_id;

            // Enable portal account for existing guardian if not done initially
            if ($guardian->user === null && ! empty($validated['create_user_account'])) {
                $username = $this->generateUniqueUsername($validated['name'], $validated['username'] ?? null);
                $password = $validated['password'] ?? 'password';

                $nameParts = explode(' ', trim($validated['name']), 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? $firstName;

                $user = User::create([
                    'username' => $username,
                    'name' => $validated['name'],
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'],
                    'gender' => Gender::MALE,
                    'role' => UserRole::GUARDIAN,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $user->assignRole(UserRole::GUARDIAN->value);
                $userId = $user->id;
            } elseif ($guardian->user !== null) {
                $nameParts = explode(' ', trim($validated['name']), 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? $firstName;

                $userUpdates = [
                    'name' => $validated['name'],
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                ];

                if (! empty($validated['password'])) {
                    $userUpdates['password'] = Hash::make($validated['password']);
                }

                $guardian->user->update($userUpdates);
            }

            $guardian->update([
                'user_id' => $userId,
                'name' => $validated['name'],
                'relationship' => $validated['relationship'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', "Guardian profile '{$validated['name']}' updated successfully.");
    }

    /**
     * Remove the specified guardian from storage.
     */
    public function destroy(Request $request, Guardian $guardian): RedirectResponse
    {
        if (! $request->user()?->can('guardians.delete')) {
            abort(403, 'Unauthorized to delete guardians.');
        }

        if (! $guardian->canBeDeleted()) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete guardian '{$guardian->name}' because they have {$guardian->students()->count()} registered student(s) linked.",
            ]);
        }

        $name = $guardian->name;
        $user = $guardian->user;

        DB::transaction(function () use ($guardian, $user) {
            $guardian->delete();
            $user?->delete();
        });

        return redirect()->back()->with('success', "Guardian '{$name}' deleted successfully.");
    }
}
