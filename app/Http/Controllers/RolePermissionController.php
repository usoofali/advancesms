<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of system roles and the permissions directory.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('roles.view')) {
            abort(403, 'Unauthorized access to role management.');
        }

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->with(['permissions' => fn ($q) => $q->select('id', 'name')])
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? ucfirst($role->name),
                'description' => $role->description,
                'is_system' => (bool) $role->is_system,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'permissions' => $role->permissions->pluck('name'),
                'created_at' => $role->created_at?->toIso8601String(),
            ]);

        $groupedPermissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'group', 'description'])
            ->groupBy('group');

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'groupedPermissions' => $groupedPermissions,
            'stats' => [
                'total_roles' => Role::count(),
                'system_roles' => Role::where('is_system', true)->count(),
                'custom_roles' => Role::where('is_system', false)->count(),
                'total_permissions' => Permission::count(),
            ],
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        /** @var array{name: string, display_name: string, description: ?string, permissions?: array<string>} $validated */
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->back()->with('success', "Role '{$role->display_name}' created successfully.");
    }

    /**
     * Update the specified role and its assigned permissions.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        /** @var array{name?: string, display_name: string, description: ?string, permissions?: array<string>} $validated */
        $validated = $request->validated();

        $attributes = [
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
        ];

        // Do not allow renaming system roles
        if (! $role->is_system && isset($validated['name'])) {
            $attributes['name'] = $validated['name'];
        }

        $role->update($attributes);

        // Sync permissions
        $permissions = $validated['permissions'] ?? [];
        $role->syncPermissions($permissions);

        return redirect()->back()->with('success', "Role '{$role->display_name}' updated successfully.");
    }

    /**
     * Remove the specified custom role from storage.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if (! $request->user()?->can('roles.delete')) {
            abort(403, 'Unauthorized to delete system roles.');
        }

        if ($role->is_system) {
            return redirect()->back()->with('error', 'Default system roles cannot be deleted.');
        }

        if ($role->users()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete role with assigned active users. Reassign users first.');
        }

        $role->delete();

        return redirect()->back()->with('success', "Role '{$role->display_name}' deleted successfully.");
    }
}
