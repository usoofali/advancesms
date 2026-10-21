<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BookCheck,
    BookOpen,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Edit2,
    GraduationCap,
    Layers,
    Library,
    Plus,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Checkbox } from '@/components/ui/checkbox';
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
import { bulkAssign as bulkAssignClassSubject, destroy as destroyClassSubject, update as updateClassSubject } from '@/routes/academic/class-subjects';
import { index as academicClassesIndex } from '@/routes/academic/classes';
import { index as academicDepartmentsIndex } from '@/routes/academic/departments';
import { destroy as destroySubject, index as academicSubjectsIndex, store as storeSubject, update as updateSubject } from '@/routes/academic/subjects';
import type { Department, SchoolClass, Subject, SubjectStats } from '@/types/academic';

interface Props {
    subjects: Subject[];
    departments: Department[];
    classes: SchoolClass[];
    stats: SubjectStats;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Subjects',
                href: academicSubjectsIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateSubject = computed(() => isSuperAdmin.value || userPermissions.value.includes('subjects.create'));
const canUpdateSubject = computed(() => isSuperAdmin.value || userPermissions.value.includes('subjects.update'));
const canDeleteSubject = computed(() => isSuperAdmin.value || userPermissions.value.includes('subjects.delete'));

const canAllocateClassSubject = computed(() => isSuperAdmin.value || userPermissions.value.includes('class_subjects.create'));
const canUpdateClassSubject = computed(() => isSuperAdmin.value || userPermissions.value.includes('class_subjects.update'));
const canDeleteClassSubject = computed(() => isSuperAdmin.value || userPermissions.value.includes('class_subjects.delete'));

// Active Tab: 'catalog' | 'curriculum'
const activeMainTab = ref<'catalog' | 'curriculum'>('catalog');

// =========================================================================
// MASTER CATALOG FILTERING & SEARCH
// =========================================================================
const searchQuery = ref('');
const selectedDepartmentFilter = ref<string>('all');

const filteredSubjects = computed(() => {
    return props.subjects.filter((sub) => {
        const matchesSearch =
            !searchQuery.value ||
            sub.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            sub.code.toLowerCase().includes(searchQuery.value.toLowerCase());

        const matchesDept =
            selectedDepartmentFilter.value === 'all' ||
            (selectedDepartmentFilter.value === 'none' && !sub.department_id) ||
            sub.department_id === Number(selectedDepartmentFilter.value);

        return matchesSearch && matchesDept;
    });
});

// =========================================================================
// PAGINATION (15 items per page)
// =========================================================================
const currentPage = ref(1);
const itemsPerPage = ref(15);

// Reset to page 1 whenever search query or filters change
watch([searchQuery, selectedDepartmentFilter], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => {
    return Math.ceil(filteredSubjects.value.length / itemsPerPage.value) || 1;
});

const paginatedSubjects = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredSubjects.value.slice(start, start + itemsPerPage.value);
});

const paginationStart = computed(() => {
    if (filteredSubjects.value.length === 0) return 0;
    return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const paginationEnd = computed(() => {
    return Math.min(currentPage.value * itemsPerPage.value, filteredSubjects.value.length);
});

const displayedPageNumbers = computed(() => {
    const pages: number[] = [];
    const maxButtons = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxButtons / 2));
    let end = Math.min(totalPages.value, start + maxButtons - 1);

    if (end - start + 1 < maxButtons) {
        start = Math.max(1, end - maxButtons + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

// =========================================================================
// SUBJECT CREATE & EDIT MODAL
// =========================================================================
const isSubjectModalOpen = ref(false);
const editingSubject = ref<Subject | null>(null);

const subjectForm = useForm({
    code: '',
    name: '',
    department_id: '' as string | number,
});

const openCreateSubjectModal = () => {
    editingSubject.value = null;
    subjectForm.reset();
    subjectForm.clearErrors();
    subjectForm.code = '';
    subjectForm.name = '';
    subjectForm.department_id = '';
    isSubjectModalOpen.value = true;
};

const openEditSubjectModal = (subject: Subject) => {
    editingSubject.value = subject;
    subjectForm.clearErrors();
    subjectForm.code = subject.code;
    subjectForm.name = subject.name;
    subjectForm.department_id = subject.department_id ?? '';
    isSubjectModalOpen.value = true;
};

const submitSubjectForm = () => {
    if (editingSubject.value) {
        subjectForm.put(updateSubject.url({ subject: editingSubject.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                isSubjectModalOpen.value = false;
                toast.success(`Subject '${subjectForm.name}' updated successfully.`);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    } else {
        subjectForm.post(storeSubject.url(), {
            preserveScroll: true,
            onSuccess: () => {
                isSubjectModalOpen.value = false;
                toast.success(`Subject '${subjectForm.name}' added to catalog.`);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    }
};

// =========================================================================
// SUBJECT DELETE MODAL
// =========================================================================
const isDeleteSubjectModalOpen = ref(false);
const subjectToDelete = ref<Subject | null>(null);
const deleteForm = useForm({});

const openDeleteSubjectModal = (subject: Subject) => {
    subjectToDelete.value = subject;
    isDeleteSubjectModalOpen.value = true;
};

const confirmDeleteSubject = () => {
    if (!subjectToDelete.value) return;

    deleteForm.delete(destroySubject.url({ subject: subjectToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteSubjectModalOpen.value = false;
            toast.success(`Subject '${subjectToDelete.value?.name}' removed from catalog.`);
            subjectToDelete.value = null;
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            if (firstError) toast.error(firstError);
        },
    });
};

// =========================================================================
// CLASS CURRICULUM MATRIX TAB
// =========================================================================
const selectedClassId = ref<number>(props.classes.length > 0 ? props.classes[0].id : 0);

const selectedClass = computed(() => {
    return props.classes.find((c) => c.id === selectedClassId.value) ?? props.classes[0] ?? null;
});

const allocatedSubjects = computed(() => {
    return selectedClass.value?.subjects ?? [];
});

const allocatedSubjectIds = computed(() => {
    return new Set(allocatedSubjects.value.map((s) => s.id));
});

// Unallocated subjects that can be assigned to the selected class
const unallocatedSubjects = computed(() => {
    return props.subjects.filter((s) => !allocatedSubjectIds.value.has(s.id));
});

// Curriculum Allocation Modal
const isAllocateModalOpen = ref(false);
const bulkAssignForm = useForm({
    class_id: 0,
    subject_ids: [] as number[],
    is_compulsory: true,
});

const openAllocateModal = () => {
    if (!selectedClass.value) return;
    bulkAssignForm.reset();
    bulkAssignForm.clearErrors();
    bulkAssignForm.class_id = selectedClass.value.id;
    bulkAssignForm.subject_ids = [];
    bulkAssignForm.is_compulsory = true;
    isAllocateModalOpen.value = true;
};

const toggleSubjectSelection = (subjectId: number) => {
    const idx = bulkAssignForm.subject_ids.indexOf(subjectId);
    if (idx > -1) {
        bulkAssignForm.subject_ids.splice(idx, 1);
    } else {
        bulkAssignForm.subject_ids.push(subjectId);
    }
};

const selectAllUnallocated = () => {
    bulkAssignForm.subject_ids = unallocatedSubjects.value.map((s) => s.id);
};

const clearSelectedUnallocated = () => {
    bulkAssignForm.subject_ids = [];
};

const submitBulkAllocate = () => {
    if (bulkAssignForm.subject_ids.length === 0) {
        toast.error('Please select at least one subject to allocate.');
        return;
    }

    bulkAssignForm.post(bulkAssignClassSubject.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isAllocateModalOpen.value = false;
            toast.success('Selected subjects assigned to class.');
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            if (firstError) toast.error(firstError);
        },
    });
};

// Toggle Compulsory Status
const updatingClassSubjectId = ref<number | null>(null);

const toggleCompulsoryStatus = (subject: Subject) => {
    if (!subject.pivot) return;
    const pivotId = subject.pivot.id;
    const newStatus = !subject.pivot.is_compulsory;

    updatingClassSubjectId.value = pivotId;
    router.put(
        updateClassSubject.url({ classSubject: pivotId }),
        { is_compulsory: newStatus },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`'${subject.name}' set to ${newStatus ? 'Compulsory' : 'Elective'}.`);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
            onFinish: () => {
                updatingClassSubjectId.value = null;
            },
        }
    );
};

// Remove Subject from Class Modal
const isRemoveSubjectModalOpen = ref(false);
const subjectToRemoveFromClass = ref<Subject | null>(null);
const removeClassSubjectForm = useForm({});

const openRemoveSubjectFromClassModal = (subject: Subject) => {
    subjectToRemoveFromClass.value = subject;
    isRemoveSubjectModalOpen.value = true;
};

const confirmRemoveSubjectFromClass = () => {
    if (!subjectToRemoveFromClass.value?.pivot) return;

    removeClassSubjectForm.delete(
        destroyClassSubject.url({ classSubject: subjectToRemoveFromClass.value.pivot.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                isRemoveSubjectModalOpen.value = false;
                toast.success(`'${subjectToRemoveFromClass.value?.name}' removed from class.`);
                subjectToRemoveFromClass.value = null;
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        }
    );
};

// Ensure selectedClassId stays valid if classes prop changes
watch(
    () => props.classes,
    (newClasses) => {
        if (newClasses.length > 0 && !newClasses.some((c) => c.id === selectedClassId.value)) {
            selectedClassId.value = newClasses[0].id;
        }
    }
);
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <Head title="Subjects" />

        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Subjects
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Manage academic subjects and configure class subject allocations.
                </p>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <Button
                    v-if="canCreateSubject"
                    @click="openCreateSubjectModal"
                    class="gap-2 w-full sm:w-auto"
                >
                    <Plus class="size-4" />
                    <span>Add Subject</span>
                </Button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4 sm:gap-3">
            <Card class="border-border/60 shadow-2xs hover:border-border transition-colors">
                <CardContent class="p-3 sm:p-3.5 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Total Subjects</p>
                        <p class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-tight">
                            {{ stats.total_subjects }}
                        </p>
                        <p class="text-[11px] text-muted-foreground/80 truncate">In master catalog</p>
                    </div>
                    <div class="rounded-lg bg-primary/10 p-2 text-primary shrink-0">
                        <BookOpen class="size-4" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-2xs hover:border-border transition-colors">
                <CardContent class="p-3 sm:p-3.5 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Assigned to Classes</p>
                        <p class="text-lg sm:text-xl font-bold tracking-tight text-blue-600 dark:text-blue-400 leading-tight">
                            {{ stats.assigned_subjects ?? 0 }}
                        </p>
                        <p class="text-[11px] text-muted-foreground/80 truncate">Active in classes</p>
                    </div>
                    <div class="rounded-lg bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400 shrink-0">
                        <BookCheck class="size-4" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-2xs hover:border-border transition-colors">
                <CardContent class="p-3 sm:p-3.5 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Unassigned</p>
                        <p class="text-lg sm:text-xl font-bold tracking-tight text-amber-600 dark:text-amber-400 leading-tight">
                            {{ stats.unassigned_subjects ?? 0 }}
                        </p>
                        <p class="text-[11px] text-muted-foreground/80 truncate">Not yet allocated</p>
                    </div>
                    <div class="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400 shrink-0">
                        <AlertTriangle class="size-4" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-2xs hover:border-border transition-colors">
                <CardContent class="p-3 sm:p-3.5 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Class Allocations</p>
                        <p class="text-lg sm:text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-tight">
                            {{ stats.total_curriculum_allocations }}
                        </p>
                        <p class="text-[11px] text-muted-foreground/80 truncate">Class-subject links</p>
                    </div>
                    <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400 shrink-0">
                        <Layers class="size-4" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Main View Segment Switcher -->
        <div class="flex items-center gap-2 border-b border-border pb-2">
            <button
                type="button"
                @click="activeMainTab = 'catalog'"
                :class="[
                    'flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                    activeMainTab === 'catalog'
                        ? 'bg-secondary text-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
                ]"
            >
                <BookOpen class="size-4" />
                <span>All Subjects ({{ subjects.length }})</span>
            </button>

            <button
                type="button"
                @click="activeMainTab = 'curriculum'"
                :class="[
                    'flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                    activeMainTab === 'curriculum'
                        ? 'bg-secondary text-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
                ]"
            >
                <Layers class="size-4" />
                <span>Class Subject Allocations</span>
            </button>
        </div>

        <!-- ================================================================= -->
        <!-- TAB 1: MASTER SUBJECT CATALOG -->
        <!-- ================================================================= -->
        <div v-if="activeMainTab === 'catalog'" class="space-y-4">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-card border border-border/60 p-3 rounded-lg shadow-xs">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search subjects by code or name..."
                        class="pl-9 h-9"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Department Filter -->
                    <select
                        v-model="selectedDepartmentFilter"
                        class="h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    >
                        <option value="all">All Departments</option>
                        <option value="none">No Department</option>
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                            {{ dept.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Subject Table / Cards -->
            <Card class="border-border/60 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/40 border-b border-border text-xs uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Code</th>
                                <th class="px-4 py-3 font-semibold">Subject Name</th>
                                <th class="px-4 py-3 font-semibold">Department</th>
                                <th class="px-4 py-3 font-semibold text-center">Classes Assigned</th>
                                <th class="px-4 py-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="subject in paginatedSubjects"
                                :key="subject.id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <td class="px-4 py-3 font-mono font-bold text-foreground">
                                    <span class="inline-block px-2 py-0.5 rounded bg-muted text-xs border border-border">
                                        {{ subject.code }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-foreground">{{ subject.name }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="subject.department" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Library class="size-3.5 text-primary/70" />
                                        {{ subject.department.name }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground/60 italic">
                                        Unassigned
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                            (subject.classes_count ?? 0) > 0
                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                : 'bg-muted text-muted-foreground'
                                        ]"
                                    >
                                        {{ subject.classes_count ?? 0 }} class{{ (subject.classes_count ?? 0) === 1 ? '' : 'es' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            v-if="canUpdateSubject"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-muted-foreground hover:text-foreground"
                                            @click="openEditSubjectModal(subject)"
                                            title="Edit Subject"
                                        >
                                            <Edit2 class="size-3.5" />
                                        </Button>
                                        <Button
                                            v-if="canDeleteSubject"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-muted-foreground hover:text-destructive"
                                            @click="openDeleteSubjectModal(subject)"
                                            title="Delete Subject"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredSubjects.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                    No subjects match the selected filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer (15 items per page) -->
                <div v-if="filteredSubjects.length > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3.5 border-t border-border bg-card">
                    <div class="text-xs text-muted-foreground">
                        Showing <span class="font-medium text-foreground">{{ paginationStart }}</span> to
                        <span class="font-medium text-foreground">{{ paginationEnd }}</span> of
                        <span class="font-medium text-foreground">{{ filteredSubjects.length }}</span> subjects
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-2.5 text-xs gap-1"
                            :disabled="currentPage === 1"
                            @click="currentPage = Math.max(1, currentPage - 1)"
                        >
                            <ChevronLeft class="size-3.5" />
                            <span>Previous</span>
                        </Button>

                        <div class="flex items-center gap-1">
                            <Button
                                v-for="pg in displayedPageNumbers"
                                :key="pg"
                                variant="ghost"
                                size="sm"
                                :class="[
                                    'size-8 p-0 text-xs font-medium',
                                    currentPage === pg
                                        ? 'bg-primary text-primary-foreground font-semibold hover:bg-primary/90 hover:text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                ]"
                                @click="currentPage = pg"
                            >
                                {{ pg }}
                            </Button>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-2.5 text-xs gap-1"
                            :disabled="currentPage >= totalPages"
                            @click="currentPage = Math.min(totalPages, currentPage + 1)"
                        >
                            <span>Next</span>
                            <ChevronRight class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </Card>
        </div>

        <!-- ================================================================= -->
        <!-- TAB 2: CLASS CURRICULUM ALLOCATION MATRIX -->
        <!-- ================================================================= -->
        <div v-else class="space-y-6">
            <!-- Class Selector Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-border">
                <button
                    v-for="c in classes"
                    :key="c.id"
                    type="button"
                    @click="selectedClassId = c.id"
                    :class="[
                        'px-3.5 py-1.5 text-sm font-semibold rounded-lg shrink-0 transition-all border',
                        selectedClassId === c.id
                            ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                            : 'bg-card text-muted-foreground border-border hover:text-foreground hover:bg-muted/50'
                    ]"
                >
                    <span>{{ c.name }}</span>
                    <span class="ml-1.5 text-xs opacity-80">({{ c.subjects?.length ?? 0 }})</span>
                </button>
            </div>

            <!-- Selected Class Overview Banner -->
            <div
                v-if="selectedClass"
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl border border-border/70 bg-card shadow-xs"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-bold text-foreground">{{ selectedClass.name }} Subjects</h2>
                        <Badge variant="outline" class="text-xs capitalize">
                            {{ selectedClass.stage.replace('_', ' ') }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Allocated: {{ allocatedSubjects.length }} subjects
                        ({{ allocatedSubjects.filter(s => s.pivot?.is_compulsory).length }} compulsory,
                        {{ allocatedSubjects.filter(s => !s.pivot?.is_compulsory).length }} elective)
                    </p>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <Button
                        v-if="canAllocateClassSubject"
                        @click="openAllocateModal"
                        class="gap-2 w-full sm:w-auto"
                    >
                        <Plus class="size-4" />
                        <span>Allocate Subjects</span>
                    </Button>
                </div>
            </div>

            <!-- Allocated Subjects Table for Current Class -->
            <Card class="border-border/60 shadow-xs overflow-hidden">
                <CardHeader class="p-4 border-b border-border bg-muted/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base font-semibold">Allocated Class Subjects</CardTitle>
                            <CardDescription class="text-xs">
                                Subjects taught in {{ selectedClass?.name }}. Click the compulsory/elective badge to toggle subject status.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/40 border-b border-border text-xs uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Code</th>
                                <th class="px-4 py-3 font-semibold">Subject</th>
                                <th class="px-4 py-3 font-semibold">Department</th>
                                <th class="px-4 py-3 font-semibold">Status in this Class</th>
                                <th class="px-4 py-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="subject in allocatedSubjects"
                                :key="subject.id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <td class="px-4 py-3 font-mono font-bold text-foreground">
                                    <span class="inline-block px-2 py-0.5 rounded bg-muted text-xs border border-border">
                                        {{ subject.code }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-foreground">{{ subject.name }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="subject.department" class="text-xs text-muted-foreground">
                                        {{ subject.department.name }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground/60 italic">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        v-if="canUpdateClassSubject && subject.pivot"
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-xs gap-1.5 font-medium transition-all"
                                        :disabled="updatingClassSubjectId === subject.pivot.id"
                                        @click="toggleCompulsoryStatus(subject)"
                                        :title="'Click to toggle between Compulsory and Elective'"
                                    >
                                        <Spinner v-if="updatingClassSubjectId === subject.pivot.id" class="size-3" />
                                        <template v-else>
                                            <Check v-if="subject.pivot.is_compulsory" class="size-3.5 text-emerald-600" />
                                            <span v-if="subject.pivot.is_compulsory" class="text-emerald-700 dark:text-emerald-400">
                                                Compulsory (Core)
                                            </span>
                                            <span v-else class="text-amber-700 dark:text-amber-400">
                                                Elective (Optional)
                                            </span>
                                        </template>
                                    </Button>
                                    <Badge
                                        v-else-if="subject.pivot"
                                        :variant="subject.pivot.is_compulsory ? 'default' : 'outline'"
                                        class="text-xs"
                                    >
                                        {{ subject.pivot.is_compulsory ? 'Compulsory' : 'Elective' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button
                                        v-if="canDeleteClassSubject && subject.pivot"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-muted-foreground hover:text-destructive"
                                        @click="openRemoveSubjectFromClassModal(subject)"
                                        title="Remove from Class"
                                    >
                                        <X class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="allocatedSubjects.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                    No subjects currently allocated to {{ selectedClass?.name }}. Click "Allocate Subjects" to assign subjects.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- ================================================================= -->
        <!-- CREATE / EDIT SUBJECT MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isSubjectModalOpen" @update:open="(val) => isSubjectModalOpen = val">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingSubject ? 'Edit Subject' : 'Add New Subject' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingSubject ? 'Modify master subject details in the catalog.' : 'Register a new academic subject in the central catalog.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitSubjectForm" class="space-y-4 py-2">
                    <!-- Code -->
                    <div class="space-y-1.5">
                        <Label for="subject-code">Subject Code *</Label>
                        <Input
                            id="subject-code"
                            v-model="subjectForm.code"
                            placeholder="e.g. MTH, ENG, PHY, BIO, GEO"
                            class="uppercase font-mono"
                            maxlength="20"
                            required
                        />
                        <p class="text-xs text-muted-foreground">Short unique identifier for broadsheets and transcripts.</p>
                        <InputError :message="subjectForm.errors.code" />
                    </div>

                    <!-- Name -->
                    <div class="space-y-1.5">
                        <Label for="subject-name">Subject Name *</Label>
                        <Input
                            id="subject-name"
                            v-model="subjectForm.name"
                            placeholder="e.g. Mathematics, English Language, Physics"
                            required
                        />
                        <InputError :message="subjectForm.errors.name" />
                    </div>

                    <!-- Department -->
                    <div class="space-y-1.5">
                        <Label for="subject-department">Academic Department (Optional)</Label>
                        <select
                            id="subject-department"
                            v-model="subjectForm.department_id"
                            class="w-full h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">None / General</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                {{ dept.name }}
                            </option>
                        </select>
                        <InputError :message="subjectForm.errors.department_id" />
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSubjectModalOpen = false"
                            :disabled="subjectForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="subjectForm.processing"
                            class="gap-2"
                        >
                            <Spinner v-if="subjectForm.processing" class="size-4" />
                            <span>{{ editingSubject ? 'Update Subject' : 'Add Subject' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ================================================================= -->
        <!-- DELETE SUBJECT CONFIRMATION MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isDeleteSubjectModalOpen" @update:open="(val) => isDeleteSubjectModalOpen = val">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <div class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-5" />
                        <DialogTitle>Delete Subject</DialogTitle>
                    </div>
                    <DialogDescription>
                        Are you sure you want to delete <span class="font-semibold text-foreground">{{ subjectToDelete?.name }} ({{ subjectToDelete?.code }})</span>?
                    </DialogDescription>
                </DialogHeader>

                <div v-if="(subjectToDelete?.classes_count ?? 0) > 0" class="p-3 rounded-lg bg-destructive/10 border border-destructive/20 text-xs text-destructive flex items-start gap-2">
                    <AlertTriangle class="size-4 shrink-0 mt-0.5" />
                    <span>
                        This subject is currently allocated to {{ subjectToDelete?.classes_count }} class(es). You must remove it from all classes before deleting.
                    </span>
                </div>

                <DialogFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteSubjectModalOpen = false"
                        :disabled="deleteForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing || (subjectToDelete?.classes_count ?? 0) > 0"
                        @click="confirmDeleteSubject"
                        class="gap-2"
                    >
                        <Spinner v-if="deleteForm.processing" class="size-4" />
                        <span>Delete Subject</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- ================================================================= -->
        <!-- ALLOCATE SUBJECTS TO CLASS MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isAllocateModalOpen" @update:open="(val) => isAllocateModalOpen = val">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader>
                    <div class="flex items-center gap-2">
                        <BookCheck class="size-5 text-primary" />
                        <DialogTitle>Allocate Subjects to {{ selectedClass?.name }}</DialogTitle>
                    </div>
                    <DialogDescription>
                        Select subjects from the catalog to add to {{ selectedClass?.name }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitBulkAllocate" class="space-y-4 py-2">
                    <!-- Default status toggle -->
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-muted/40 border border-border">
                        <Checkbox
                            id="bulk-is-compulsory"
                            :checked="bulkAssignForm.is_compulsory"
                            @update:checked="(val: boolean) => bulkAssignForm.is_compulsory = Boolean(val)"
                        />
                        <div class="grid gap-0.5 leading-none">
                            <label for="bulk-is-compulsory" class="text-sm font-medium cursor-pointer">
                                Mark as Compulsory (Core)
                            </label>
                            <p class="text-xs text-muted-foreground">
                                If unchecked, allocated subjects will be marked as elective for this class.
                            </p>
                        </div>
                    </div>

                    <!-- Actions to Select All / Clear -->
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Available Subjects ({{ unallocatedSubjects.length }})</span>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="selectAllUnallocated"
                                class="hover:text-primary transition-colors font-medium"
                            >
                                Select All
                            </button>
                            <button
                                type="button"
                                @click="clearSelectedUnallocated"
                                class="hover:text-destructive transition-colors font-medium"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Subject selection list -->
                    <div class="max-h-60 overflow-y-auto space-y-1 border border-border rounded-lg p-2 divide-y divide-border/50">
                        <div
                            v-for="sub in unallocatedSubjects"
                            :key="sub.id"
                            @click="toggleSubjectSelection(sub.id)"
                            :class="[
                                'flex items-center justify-between p-2 rounded-md cursor-pointer transition-colors text-sm',
                                bulkAssignForm.subject_ids.includes(sub.id)
                                    ? 'bg-primary/10 text-foreground font-medium'
                                    : 'hover:bg-muted/50 text-muted-foreground'
                            ]"
                        >
                            <div class="flex items-center gap-2.5">
                                <Checkbox
                                    :checked="bulkAssignForm.subject_ids.includes(sub.id)"
                                    @click.stop="toggleSubjectSelection(sub.id)"
                                />
                                <div>
                                    <span class="font-mono text-xs font-semibold mr-1.5 px-1.5 py-0.5 rounded bg-muted">
                                        {{ sub.code }}
                                    </span>
                                    <span>{{ sub.name }}</span>
                                </div>
                            </div>
                            <span v-if="sub.department" class="text-xs text-muted-foreground/70">
                                {{ sub.department.name }}
                            </span>
                        </div>
                        <div v-if="unallocatedSubjects.length === 0" class="py-6 text-center text-xs text-muted-foreground">
                            All catalog subjects are already allocated to {{ selectedClass?.name }}.
                        </div>
                    </div>
                    <InputError :message="bulkAssignForm.errors.subject_ids" />

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isAllocateModalOpen = false"
                            :disabled="bulkAssignForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="bulkAssignForm.processing || bulkAssignForm.subject_ids.length === 0"
                            class="gap-2"
                        >
                            <Spinner v-if="bulkAssignForm.processing" class="size-4" />
                            <span>Allocate ({{ bulkAssignForm.subject_ids.length }}) Subjects</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ================================================================= -->
        <!-- REMOVE SUBJECT FROM CLASS CONFIRMATION MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isRemoveSubjectModalOpen" @update:open="(val) => isRemoveSubjectModalOpen = val">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <div class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-5" />
                        <DialogTitle>Remove Subject from {{ selectedClass?.name }}</DialogTitle>
                    </div>
                    <DialogDescription>
                        Are you sure you want to remove <span class="font-semibold text-foreground">{{ subjectToRemoveFromClass?.name }} ({{ subjectToRemoveFromClass?.code }})</span> from {{ selectedClass?.name }}?
                    </DialogDescription>
                </DialogHeader>

                <p class="text-xs text-muted-foreground">
                    This will detach the subject from {{ selectedClass?.name }}. The subject will remain available in the master catalog.
                </p>

                <DialogFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isRemoveSubjectModalOpen = false"
                        :disabled="removeClassSubjectForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="removeClassSubjectForm.processing"
                        @click="confirmRemoveSubjectFromClass"
                        class="gap-2"
                    >
                        <Spinner v-if="removeClassSubjectForm.processing" class="size-4" />
                        <span>Remove Subject</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
