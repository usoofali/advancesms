<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BookOpen,
    Building2,
    Edit2,
    GraduationCap,
    Library,
    Plus,
    Sparkles,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index as academicClassesIndex } from '@/routes/academic/classes';
import { index as academicDepartmentsIndex } from '@/routes/academic/departments';
import type { Department, DepartmentStats } from '@/types/academic';

interface Props {
    departments: Department[];
    stats: DepartmentStats;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Departments',
                href: academicDepartmentsIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateDepartment = computed(() => isSuperAdmin.value || userPermissions.value.includes('departments.create'));
const canUpdateDepartment = computed(() => isSuperAdmin.value || userPermissions.value.includes('departments.update'));
const canDeleteDepartment = computed(() => isSuperAdmin.value || userPermissions.value.includes('departments.delete'));

// =========================================================================
// DEPARTMENT MODAL STATES
// =========================================================================
const isDeptModalOpen = ref(false);
const editingDepartment = ref<Department | null>(null);

const deptForm = useForm({
    name: '',
    description: '',
});

const openCreateDeptModal = () => {
    editingDepartment.value = null;
    deptForm.reset();
    deptForm.clearErrors();
    deptForm.name = '';
    deptForm.description = '';
    isDeptModalOpen.value = true;
};

const openEditDeptModal = (dept: Department) => {
    editingDepartment.value = dept;
    deptForm.reset();
    deptForm.clearErrors();
    deptForm.name = dept.name;
    deptForm.description = dept.description || '';
    isDeptModalOpen.value = true;
};

const submitDeptForm = () => {
    if (editingDepartment.value) {
        deptForm.put(`/academic/departments/${editingDepartment.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isDeptModalOpen.value = false;
                toast.success('Department updated successfully.');
            },
        });
    } else {
        deptForm.post('/academic/departments', {
            preserveScroll: true,
            onSuccess: () => {
                isDeptModalOpen.value = false;
                toast.success('Department created successfully.');
            },
        });
    }
};

// =========================================================================
// DELETE ACTIONS
// =========================================================================
const deptToDelete = ref<Department | null>(null);
const isDeleteModalOpen = ref(false);
const deleteForm = useForm({});

const confirmDeleteDept = (dept: Department) => {
    deptToDelete.value = dept;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!deptToDelete.value) return;

    deleteForm.delete(`/academic/departments/${deptToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            toast.success('Department deleted successfully.');
        },
        onError: (errs) => {
            toast.error(errs.error || 'Failed to delete department.');
        },
    });
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <Head title="Academic Structure - Departments" />

        <!-- Header & Action Row (Mobile-First Stacked) -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Academic Departments
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Manage academic departments and their subjects.
                </p>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <Button
                    v-if="canCreateDepartment"
                    @click="openCreateDeptModal"
                    class="w-full sm:w-auto gap-2 shadow-sm"
                >
                    <Plus class="size-4" />
                    <span>Add Department</span>
                </Button>
            </div>
        </div>

        <!-- Metric KPI Cards (1 Col Mobile, 3 Col Desktop) -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Total Departments
                    </CardTitle>
                    <div class="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <Library class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-xl sm:text-2xl font-bold text-foreground">
                        {{ stats.total_departments }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Active academic departments
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Assigned Teachers
                    </CardTitle>
                    <div class="flex size-7 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Users class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-xl sm:text-2xl font-bold text-foreground">
                        {{ stats.total_teachers_assigned }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Teachers in departments
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Discipline Subjects
                    </CardTitle>
                    <div class="flex size-7 items-center justify-center rounded-md bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                        <BookOpen class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-xl sm:text-2xl font-bold text-foreground">
                        {{ stats.total_subjects_assigned }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Subjects mapped
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Departments Grid (1 Col Mobile, 2 Col Tablet, 3 Col Desktop) -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="dept in departments"
                :key="dept.id"
                class="border-border/80 bg-card hover:border-border transition-all duration-200 shadow-xs flex flex-col justify-between"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Library class="size-4.5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-bold text-foreground">
                                    {{ dept.name }}
                                </CardTitle>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <Button
                                v-if="canUpdateDepartment"
                                variant="ghost"
                                size="sm"
                                class="size-7 p-0"
                                @click="openEditDeptModal(dept)"
                                title="Edit Department"
                            >
                                <Edit2 class="size-3.5" />
                            </Button>

                            <Button
                                v-if="canDeleteDepartment && (dept.teachers_count ?? 0) === 0 && (dept.subjects_count ?? 0) === 0"
                                variant="ghost"
                                size="sm"
                                class="size-7 p-0 text-destructive hover:bg-destructive/10"
                                @click="confirmDeleteDept(dept)"
                                title="Delete Department"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <CardDescription class="text-xs line-clamp-2 mt-2">
                        {{ dept.description || 'No specific description provided for this department.' }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="pt-0 border-t border-border/50 mt-3 pt-3">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span class="flex items-center gap-1">
                            <Users class="size-3.5 text-primary/70" />
                            {{ dept.teachers_count ?? 0 }} Teachers
                        </span>
                        <span class="flex items-center gap-1">
                            <BookOpen class="size-3.5 text-primary/70" />
                            {{ dept.subjects_count ?? 0 }} Subjects
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <div
                v-if="departments.length === 0"
                class="col-span-full flex flex-col items-center justify-center rounded-xl border border-dashed border-border/80 p-8 sm:p-12 text-center bg-card/40"
            >
                <div class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
                    <Library class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-foreground">
                    No departments created
                </h3>
                <p class="text-xs sm:text-sm text-muted-foreground max-w-sm mt-1">
                    Organize your academic staff and subject curricula by setting up departments.
                </p>
                <Button
                    v-if="canCreateDepartment"
                    @click="openCreateDeptModal"
                    class="mt-4 gap-1.5"
                    size="sm"
                >
                    <Plus class="size-4" />
                    <span>Create Department</span>
                </Button>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 1: Create / Edit Department (Mobile Scroll Friendly)                -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isDeptModalOpen">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingDepartment ? `Edit Department: ${editingDepartment.name}` : 'New Academic Department' }}
                    </DialogTitle>
                    <DialogDescription>
                        Define department identifier and academic focus description.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitDeptForm" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label for="dept_name">
                            Department Name <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="dept_name"
                            v-model="deptForm.name"
                            type="text"
                            placeholder="e.g. Sciences, Arts & Humanities"
                            required
                        />
                        <InputError :message="deptForm.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="dept_description">Description</Label>
                        <Input
                            id="dept_description"
                            v-model="deptForm.description"
                            type="text"
                            placeholder="e.g. Pure sciences, biology, chemistry, and mathematics"
                        />
                        <InputError :message="deptForm.errors.description" />
                    </div>

                    <DialogFooter class="flex flex-col-reverse sm:flex-row gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isDeptModalOpen = false"
                            class="w-full sm:w-auto"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="deptForm.processing"
                            class="w-full sm:w-auto"
                        >
                            <Spinner v-if="deptForm.processing" class="size-4 mr-1.5" />
                            <span>{{ editingDepartment ? 'Update Department' : 'Create Department' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ========================================================================= -->
        <!-- MODAL 2: Confirm Delete                                                   -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-5" />
                        <span>Confirm Deletion</span>
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete <strong class="text-foreground">{{ deptToDelete?.name }}</strong>?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex flex-col-reverse sm:flex-row gap-2 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteModalOpen = false"
                        class="w-full sm:w-auto"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        class="w-full sm:w-auto gap-1.5"
                        :disabled="deleteForm.processing"
                        @click="executeDelete"
                    >
                        <Spinner v-if="deleteForm.processing" class="size-4" />
                        <span>Delete Department</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
