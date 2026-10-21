<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Filter,
    Lock,
    Pencil,
    Plus,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { destroy, store, update } from '@/actions/App/Http/Controllers/RolePermissionController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

interface PermissionItem {
    id: number;
    name: string;
    display_name: string;
    group: string;
    description: string;
}

interface RoleItem {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
    permissions: string[];
    created_at: string | null;
}

interface Stats {
    total_roles: number;
    system_roles: number;
    custom_roles: number;
    total_permissions: number;
}

const props = defineProps<{
    roles: RoleItem[];
    groupedPermissions: Record<string, PermissionItem[]>;
    stats: Stats;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Roles & Permissions',
                href: '#',
            },
        ],
    },
});

// Search filter for roles
const roleSearchQuery = ref('');

const filteredRoles = computed(() => {
    const query = roleSearchQuery.value.toLowerCase().trim();
    if (!query) {
        return props.roles;
    }
    return props.roles.filter(
        (role) =>
            role.name.toLowerCase().includes(query) ||
            role.display_name.toLowerCase().includes(query) ||
            (role.description && role.description.toLowerCase().includes(query)),
    );
});

// Modals State
const isRoleModalOpen = ref(false);
const isPermissionMatrixOpen = ref(false);
const isDeleteModalOpen = ref(false);
const roleToDelete = ref<RoleItem | null>(null);
const editingRole = ref<RoleItem | null>(null);

// Forms
const roleForm = useForm({
    name: '',
    display_name: '',
    description: '',
});

const matrixForm = useForm({
    permissions: [] as string[],
});

// Permission Matrix Search and Filter
const matrixSearchQuery = ref('');
const selectedGroupFilter = ref('all');

const availableGroups = computed(() => Object.keys(props.groupedPermissions));

const filteredGroupedPermissions = computed(() => {
    const query = matrixSearchQuery.value.toLowerCase().trim();
    const result: Record<string, PermissionItem[]> = {};

    for (const [groupName, permissions] of Object.entries(props.groupedPermissions)) {
        if (selectedGroupFilter.value !== 'all' && selectedGroupFilter.value !== groupName) {
            continue;
        }

        const matching = permissions.filter((perm) => {
            if (!query) return true;
            return (
                perm.name.toLowerCase().includes(query) ||
                perm.display_name.toLowerCase().includes(query) ||
                perm.description.toLowerCase().includes(query)
            );
        });

        if (matching.length > 0) {
            result[groupName] = matching;
        }
    }

    return result;
});

// Open Create Role Modal
function openCreateRoleModal() {
    editingRole.value = null;
    roleForm.reset();
    roleForm.clearErrors();
    isRoleModalOpen.value = true;
}

// Open Edit Role Details Modal
function openEditRoleModal(role: RoleItem) {
    editingRole.value = role;
    roleForm.clearErrors();
    roleForm.name = role.name;
    roleForm.display_name = role.display_name;
    roleForm.description = role.description || '';
    isRoleModalOpen.value = true;
}

// Submit Create/Edit Role
function submitRoleForm() {
    if (editingRole.value) {
        roleForm.put(update.url(editingRole.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                isRoleModalOpen.value = false;
                toast.success(`Role '${roleForm.display_name}' updated successfully.`);
            },
        });
    } else {
        roleForm.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                isRoleModalOpen.value = false;
                toast.success(`Role '${roleForm.display_name}' created successfully.`);
            },
        });
    }
}

// Open Permission Matrix Modal
function openPermissionMatrix(role: RoleItem) {
    editingRole.value = role;
    matrixForm.clearErrors();
    matrixForm.permissions = [...role.permissions];
    matrixSearchQuery.value = '';
    selectedGroupFilter.value = 'all';
    isPermissionMatrixOpen.value = true;
}

// Toggle a specific permission
function togglePermission(permissionName: string) {
    const index = matrixForm.permissions.indexOf(permissionName);
    if (index > -1) {
        matrixForm.permissions.splice(index, 1);
    } else {
        matrixForm.permissions.push(permissionName);
    }
}

// Check if all permissions in a group are selected
function isGroupAllSelected(groupName: string): boolean {
    const perms = props.groupedPermissions[groupName] || [];
    if (perms.length === 0) return false;
    return perms.every((p) => matrixForm.permissions.includes(p.name));
}

// Count selected permissions in a group
function getGroupSelectedCount(groupName: string): number {
    const perms = props.groupedPermissions[groupName] || [];
    return perms.filter((p) => matrixForm.permissions.includes(p.name)).length;
}

// Toggle all permissions in a group
function toggleGroupPermissions(groupName: string) {
    const perms = props.groupedPermissions[groupName] || [];
    const allSelected = isGroupAllSelected(groupName);

    if (allSelected) {
        const namesToRemove = new Set(perms.map((p) => p.name));
        matrixForm.permissions = matrixForm.permissions.filter((name) => !namesToRemove.has(name));
    } else {
        const namesToAdd = perms.map((p) => p.name).filter((name) => !matrixForm.permissions.includes(name));
        matrixForm.permissions.push(...namesToAdd);
    }
}

// Select All / Clear All global toggle
function selectAllPermissions() {
    const allNames: string[] = [];
    for (const perms of Object.values(props.groupedPermissions)) {
        allNames.push(...perms.map((p) => p.name));
    }
    matrixForm.permissions = Array.from(new Set(allNames));
}

function clearAllPermissions() {
    matrixForm.permissions = [];
}

// Save Permission Matrix
function submitPermissionMatrix() {
    if (!editingRole.value) return;

    matrixForm.put(update.url(editingRole.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isPermissionMatrixOpen.value = false;
            toast.success(`Permissions for '${editingRole.value?.display_name}' updated successfully.`);
        },
    });
}

// Delete Role Modal
function confirmDeleteRole(role: RoleItem) {
    roleToDelete.value = role;
    isDeleteModalOpen.value = true;
}

const deleteForm = useForm({});

function submitDeleteRole() {
    if (!roleToDelete.value) return;

    deleteForm.delete(destroy.url(roleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            roleToDelete.value = null;
            toast.success('Role deleted successfully.');
        },
        onError: (errors) => {
            toast.error(errors.error || 'Failed to delete role.');
        },
    });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <Head title="Roles & Permissions Management" />
        <!-- Top Banner Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Roles & Permissions
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Manage dynamic institutional staff roles and calibrate fine-grained functional capabilities across all school models.
                </p>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <Button @click="openCreateRoleModal" class="w-full sm:w-auto gap-1.5 shadow-sm">
                    <Plus class="size-4" />
                    <span>Add Role</span>
                </Button>
            </div>
        </div>

        <!-- Metric KPI Summary Cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Total Roles
                    </CardTitle>
                    <Shield class="size-4 text-primary" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-foreground">
                        {{ stats.total_roles }}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Active roles in registry
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        System Defaults
                    </CardTitle>
                    <Lock class="size-4 text-indigo-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-foreground">
                        {{ stats.system_roles }}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Protected core structures
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Custom Roles
                    </CardTitle>
                    <Users class="size-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-foreground">
                        {{ stats.custom_roles }}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Institutionally defined
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        System Capabilities
                    </CardTitle>
                    <ShieldCheck class="size-4 text-amber-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-foreground">
                        {{ stats.total_permissions }}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Granular model actions
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Search & Filter Controls -->
        <div class="flex items-center gap-3">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="roleSearchQuery"
                    type="text"
                    placeholder="Search roles by title, key, or functional scope..."
                    class="pl-9"
                />
            </div>
        </div>

        <!-- Roles Grid -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="role in filteredRoles"
                :key="role.id"
                class="flex flex-col justify-between border-border/70 transition-all hover:border-primary/40 hover:shadow-md"
            >
                <CardHeader class="space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-1">
                            <CardTitle class="text-lg font-bold text-foreground">
                                {{ role.display_name }}
                            </CardTitle>
                            <span class="inline-block rounded bg-muted/80 px-2 py-0.5 font-mono text-xs text-muted-foreground">
                                {{ role.name }}
                            </span>
                        </div>

                        <Badge
                            v-if="role.is_system"
                            variant="secondary"
                            class="gap-1 border-indigo-200 bg-indigo-50 font-medium text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-950/60 dark:text-indigo-300"
                        >
                            <Lock class="size-3" />
                            <span>System Default</span>
                        </Badge>
                        <Badge
                            v-else
                            variant="outline"
                            class="gap-1 border-emerald-300 bg-emerald-50 font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                        >
                            <Users class="size-3" />
                            <span>Custom Role</span>
                        </Badge>
                    </div>

                    <CardDescription class="line-clamp-2 min-h-[2.5rem] text-xs leading-relaxed text-muted-foreground">
                        {{ role.description || 'No detailed scope description provided for this role.' }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="py-2">
                    <!-- Progress / Stats -->
                    <div class="flex flex-col gap-2 rounded-lg bg-muted/30 p-3 text-xs">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Assigned Permissions:</span>
                            <span class="font-semibold text-foreground">
                                {{ role.permissions_count }} / {{ stats.total_permissions }}
                            </span>
                        </div>

                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-300"
                                :style="{
                                    width: `${Math.round((role.permissions_count / (stats.total_permissions || 1)) * 100)}%`,
                                }"
                            />
                        </div>

                        <div class="flex items-center justify-between pt-1 text-muted-foreground">
                            <span>Active Users Assigned:</span>
                            <span class="font-medium text-foreground">
                                {{ role.users_count }} users
                            </span>
                        </div>
                    </div>
                </CardContent>

                <CardFooter class="flex items-center justify-between border-t border-border/50 pt-4">
                    <Button
                        size="sm"
                        variant="default"
                        @click="openPermissionMatrix(role)"
                        class="gap-1.5"
                    >
                        <ShieldCheck class="size-4" />
                        <span>Permissions</span>
                    </Button>

                    <div class="flex items-center gap-1">
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            @click="openEditRoleModal(role)"
                            title="Edit Role Details"
                        >
                            <Pencil class="size-3.5 text-muted-foreground" />
                        </Button>

                        <Button
                            v-if="!role.is_system"
                            size="icon-sm"
                            variant="ghost"
                            @click="confirmDeleteRole(role)"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            title="Delete Role"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>

        <!-- Empty State -->
        <div
            v-if="filteredRoles.length === 0"
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/70 p-12 text-center"
        >
            <ShieldAlert class="size-10 text-muted-foreground" />
            <h3 class="mt-3 text-base font-semibold text-foreground">
                No roles found
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
                No roles matched your search criteria "{{ roleSearchQuery }}".
            </p>
            <Button
                variant="outline"
                size="sm"
                @click="roleSearchQuery = ''"
                class="mt-4"
            >
                Clear Search Filter
            </Button>
        </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: Create / Edit Role Details                                       -->
    <!-- ========================================================================= -->
    <Dialog v-model:open="isRoleModalOpen">
        <DialogScrollContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ editingRole ? `Edit Role: ${editingRole.display_name}` : 'Create Custom Role' }}
                </DialogTitle>
                <DialogDescription>
                    Define role identifiers and functional scope for this school role.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitRoleForm" class="space-y-4 py-2">
                <div class="space-y-1.5">
                    <Label for="display_name">Role Title / Display Name</Label>
                    <Input
                        id="display_name"
                        v-model="roleForm.display_name"
                        type="text"
                        placeholder="e.g. Vice Principal (Academic)"
                        required
                    />
                    <InputError :message="roleForm.errors.display_name" />
                </div>

                <div class="space-y-1.5">
                    <Label for="name">
                        Role Code (Slug Identifier)
                        <span v-if="editingRole?.is_system" class="text-xs text-muted-foreground">
                            (System roles cannot be renamed)
                        </span>
                    </Label>
                    <Input
                        id="name"
                        v-model="roleForm.name"
                        type="text"
                        placeholder="e.g. vice_principal"
                        :disabled="editingRole?.is_system"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        Unique lowercase alphanumeric slug (e.g. vice_principal, discipline_master).
                    </p>
                    <InputError :message="roleForm.errors.name" />
                </div>

                <div class="space-y-1.5">
                    <Label for="description">Functional Scope & Description</Label>
                    <textarea
                        id="description"
                        v-model="roleForm.description"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Explain what responsibilities and scope this role covers..."
                    />
                    <InputError :message="roleForm.errors.description" />
                </div>

                <DialogFooter class="pt-2">
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="roleForm.processing">
                        {{ roleForm.processing ? 'Saving...' : editingRole ? 'Update Role' : 'Create Role' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogScrollContent>
    </Dialog>

    <!-- ========================================================================= -->
    <!-- MODAL 2: Granular Permission Matrix with Functional Descriptions         -->
    <!-- ========================================================================= -->
    <Dialog v-model:open="isPermissionMatrixOpen">
        <DialogContent
            class="w-[96vw] max-w-[96vw] sm:max-w-4xl lg:max-w-5xl xl:max-w-6xl h-[92vh] max-h-[92vh] flex flex-col p-0 gap-0 overflow-hidden rounded-xl border border-border bg-background shadow-2xl"
        >
            <!-- Pinned Header -->
            <div class="shrink-0 border-b border-border/70 bg-card/95 px-4 sm:px-6 py-4 pr-12 sm:pr-14 backdrop-blur-xs">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                    <div class="space-y-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary shrink-0">
                                <ShieldCheck class="size-4 sm:size-5" />
                            </div>
                            <DialogTitle class="text-base sm:text-lg font-bold tracking-tight text-foreground truncate">
                                Permissions: {{ editingRole?.display_name }}
                            </DialogTitle>
                            <Badge
                                v-if="editingRole?.is_system"
                                variant="secondary"
                                class="text-[10px] uppercase font-semibold tracking-wide"
                            >
                                System Role
                            </Badge>
                        </div>
                        <DialogDescription class="text-xs text-muted-foreground">
                            Configure granular capabilities and model-level access controls for this role.
                        </DialogDescription>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <Badge
                            variant="outline"
                            class="font-mono text-xs px-2.5 py-1 bg-primary/5 border-primary/30 text-primary font-semibold"
                        >
                            {{ matrixForm.permissions.length }} of {{ stats.total_permissions }} Active
                        </Badge>
                    </div>
                </div>

                <!-- Matrix Quick Search and Group Filter Bar -->
                <div class="mt-3.5 flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                    <div class="relative flex-1 min-w-0">
                        <Search class="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                        <Input
                            v-model="matrixSearchQuery"
                            type="text"
                            placeholder="Search permission by action or description (e.g. marks, classes, delete)..."
                            class="h-8.5 pl-8.5 pr-8 text-xs bg-background"
                        />
                        <button
                            v-if="matrixSearchQuery"
                            type="button"
                            @click="matrixSearchQuery = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5 rounded transition-colors"
                            title="Clear search"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <select
                            v-model="selectedGroupFilter"
                            class="h-8.5 rounded-md border border-input bg-background px-2.5 text-xs text-foreground shadow-xs outline-none focus:border-ring focus:ring-1 focus:ring-ring"
                        >
                            <option value="all">All Modules ({{ availableGroups.length }})</option>
                            <option v-for="grp in availableGroups" :key="grp" :value="grp">
                                {{ grp }}
                            </option>
                        </select>

                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="selectAllPermissions"
                            class="h-8.5 text-xs px-2.5 sm:px-3"
                        >
                            Select All
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="clearAllPermissions"
                            class="h-8.5 text-xs px-2 text-muted-foreground hover:text-foreground"
                        >
                            Clear
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Scrollable Content & Pinned Footer Form -->
            <form @submit.prevent="submitPermissionMatrix" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                <!-- Grouped Permissions Scrollable Body -->
                <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-5 space-y-5 overscroll-contain bg-muted/10">
                    <div
                        v-for="(permissions, groupName) in filteredGroupedPermissions"
                        :key="groupName"
                        class="space-y-3 rounded-xl border border-border/70 bg-card p-3.5 sm:p-4.5 shadow-2xs"
                    >
                        <!-- Module Group Header -->
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/50 pb-2.5">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-xs sm:text-sm font-bold tracking-tight text-foreground truncate">
                                    {{ groupName }}
                                </span>
                                <Badge variant="secondary" class="text-[10px] font-medium">
                                    {{ permissions.length }} capabilities
                                </Badge>
                                <Badge
                                    v-if="getGroupSelectedCount(groupName) > 0"
                                    :variant="isGroupAllSelected(groupName) ? 'default' : 'outline'"
                                    class="text-[10px] font-mono"
                                >
                                    {{ getGroupSelectedCount(groupName) }} active
                                </Badge>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="toggleGroupPermissions(groupName)"
                                class="h-6.5 text-xs text-primary hover:bg-primary/10 hover:text-primary px-2"
                            >
                                {{ isGroupAllSelected(groupName) ? 'Deselect Group' : 'Select All in Group' }}
                            </Button>
                        </div>

                        <!-- Permissions Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                            <div
                                v-for="perm in permissions"
                                :key="perm.id"
                                @click="togglePermission(perm.name)"
                                class="group relative flex cursor-pointer items-start gap-2.5 sm:gap-3 rounded-lg border p-3 transition-colors select-none"
                                :class="
                                    matrixForm.permissions.includes(perm.name)
                                        ? 'border-primary/50 bg-primary/5 dark:bg-primary/10 shadow-2xs'
                                        : 'border-border/60 bg-background/60 hover:border-border hover:bg-accent/40'
                                "
                            >
                                <Checkbox
                                    :model-value="matrixForm.permissions.includes(perm.name)"
                                    @update:model-value="togglePermission(perm.name)"
                                    class="mt-0.5 shrink-0"
                                />

                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex flex-wrap items-start justify-between gap-1.5 min-w-0">
                                        <span class="text-xs sm:text-sm font-semibold text-foreground leading-snug break-words">
                                            {{ perm.display_name }}
                                        </span>
                                        <span
                                            class="inline-block rounded bg-muted/80 px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground break-all shrink-0 max-w-full"
                                        >
                                            {{ perm.name }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] leading-relaxed text-muted-foreground break-words">
                                        {{ perm.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="Object.keys(filteredGroupedPermissions).length === 0"
                        class="flex flex-col items-center justify-center py-12 text-center"
                    >
                        <Filter class="size-8 text-muted-foreground/60 mb-2" />
                        <h4 class="text-sm font-semibold text-foreground">No matching permissions</h4>
                        <p class="text-xs text-muted-foreground mt-1 max-w-md">
                            No capabilities match "{{ matrixSearchQuery }}". Try refining your search query or switching module filters.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="matrixSearchQuery = ''; selectedGroupFilter = 'all'"
                            class="mt-3 text-xs"
                        >
                            Reset Filters
                        </Button>
                    </div>
                </div>

                <!-- Pinned Footer -->
                <div class="shrink-0 border-t border-border/70 bg-card/95 px-4 sm:px-6 py-3 backdrop-blur-xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs text-muted-foreground w-full sm:w-auto justify-between sm:justify-start">
                        <div class="flex items-center gap-1.5">
                            <ShieldCheck class="size-4 text-primary" />
                            <span class="font-medium text-foreground">
                                {{ matrixForm.permissions.length }} of {{ stats.total_permissions }} active
                            </span>
                        </div>
                        <button
                            v-if="matrixForm.permissions.length > 0"
                            type="button"
                            @click="clearAllPermissions"
                            class="text-[11px] text-muted-foreground hover:text-destructive underline underline-offset-2 transition-colors ml-2"
                        >
                            Clear all
                        </button>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <DialogClose as-child>
                            <Button type="button" variant="outline" size="sm" class="text-xs">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="matrixForm.processing"
                            class="gap-1.5 text-xs"
                        >
                            <CheckCircle2 class="size-3.5" />
                            <span>{{ matrixForm.processing ? 'Saving Permissions...' : 'Save Permissions' }}</span>
                        </Button>
                    </div>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ========================================================================= -->
    <!-- MODAL 3: Delete Role Confirmation                                         -->
    <!-- ========================================================================= -->
    <Dialog v-model:open="isDeleteModalOpen">
        <DialogScrollContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-destructive">
                    Confirm Role Deletion
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete the role
                    <strong class="text-foreground">{{ roleToDelete?.display_name }}</strong>?
                    This action cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="pt-2">
                <DialogClose as-child>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="deleteForm.processing"
                    @click="submitDeleteRole"
                >
                    {{ deleteForm.processing ? 'Deleting...' : 'Delete Role' }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
    </div>
</template>
