<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BookOpen,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    ClipboardList,
    Edit2,
    Filter,
    GraduationCap,
    Layers,
    PenTool,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    UserCheck,
    Users,
    XCircle,
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
import {
    destroy as destroyAllocation,
    index as academicAllocationsIndex,
    store as storeAllocation,
    update as updateAllocation,
} from '@/routes/academic/allocations';
import type { AcademicSession, AcademicTerm, Department, SchoolClass, Section, Subject } from '@/types/academic';
import type { Teacher } from '@/types/people';

interface Props {
    assignments: {
        id: number;
        academic_session_id: number;
        academic_term_id: number;
        teacher_id: number;
        class_id: number;
        section_id: number;
        subject_id: number;
        can_input_marks: boolean;
        can_take_attendance: boolean;
        teacher?: Teacher;
        school_class?: SchoolClass;
        section?: Section;
        subject?: Subject & { department?: Department };
        academic_session?: AcademicSession;
        academic_term?: AcademicTerm;
    }[];
    sessions: (AcademicSession & { terms?: AcademicTerm[] })[];
    selectedSessionId: number;
    selectedTermId: number;
    classes: (SchoolClass & { sections?: Section[] })[];
    teachers: Teacher[];
    subjects: (Subject & { department?: Department })[];
    classSubjects: {
        id: number;
        class_id: number;
        subject_id: number;
        is_compulsory: boolean;
    }[];
    stats: {
        total_allocations: number;
        assigned_teachers_count: number;
        can_input_marks_count: number;
        can_take_attendance_count: number;
    };
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Teacher Allocations',
                href: academicAllocationsIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateAllocation = computed(() => isSuperAdmin.value || userPermissions.value.includes('teacher_subject_assignments.create'));
const canUpdateAllocation = computed(() => isSuperAdmin.value || userPermissions.value.includes('teacher_subject_assignments.update'));
const canDeleteAllocation = computed(() => isSuperAdmin.value || userPermissions.value.includes('teacher_subject_assignments.delete'));

// Session & Term Switchers
const currentSession = computed(() => props.sessions.find((s) => s.id === props.selectedSessionId));
const availableTerms = computed(() => currentSession.value?.terms ?? []);
const currentTerm = computed(() => availableTerms.value.find((t) => t.id === props.selectedTermId));

const onSessionChange = (sessionId: number | string) => {
    router.get(
        academicAllocationsIndex().url,
        { session_id: sessionId },
        { preserveState: true, preserveScroll: true },
    );
};

const onTermChange = (termId: number | string) => {
    router.get(
        academicAllocationsIndex().url,
        { session_id: props.selectedSessionId, term_id: termId },
        { preserveState: true, preserveScroll: true },
    );
};

// =========================================================================
// SEARCH & FILTERS
// =========================================================================
const searchQuery = ref('');
const selectedClassFilter = ref<string>('all');
const selectedSectionFilter = ref<string>('all');
const selectedTeacherFilter = ref<string>('all');

const availableSectionsForFilter = computed(() => {
    if (selectedClassFilter.value === 'all') return [];
    const cls = props.classes.find((c) => c.id === Number(selectedClassFilter.value));
    return cls?.sections ?? [];
});

watch(selectedClassFilter, () => {
    selectedSectionFilter.value = 'all';
});

const filteredAssignments = computed(() => {
    return props.assignments.filter((a) => {
        const query = searchQuery.value.toLowerCase().trim();
        const teacherName = a.teacher?.user?.name?.toLowerCase() ?? '';
        const staffId = a.teacher?.staff_id?.toLowerCase() ?? '';
        const subjectName = a.subject?.name?.toLowerCase() ?? '';
        const subjectCode = a.subject?.code?.toLowerCase() ?? '';

        const matchesSearch =
            !query ||
            teacherName.includes(query) ||
            staffId.includes(query) ||
            subjectName.includes(query) ||
            subjectCode.includes(query);

        const matchesClass =
            selectedClassFilter.value === 'all' ||
            a.class_id === Number(selectedClassFilter.value);

        const matchesSection =
            selectedSectionFilter.value === 'all' ||
            a.section_id === Number(selectedSectionFilter.value);

        const matchesTeacher =
            selectedTeacherFilter.value === 'all' ||
            a.teacher_id === Number(selectedTeacherFilter.value);

        return matchesSearch && matchesClass && matchesSection && matchesTeacher;
    });
});

// =========================================================================
// PAGINATION (15 items per page)
// =========================================================================
const currentPage = ref(1);
const itemsPerPage = ref(15);

watch([searchQuery, selectedClassFilter, selectedSectionFilter, selectedTeacherFilter], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => {
    return Math.ceil(filteredAssignments.value.length / itemsPerPage.value) || 1;
});

const paginatedAssignments = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredAssignments.value.slice(start, start + itemsPerPage.value);
});

const paginationStart = computed(() => {
    if (filteredAssignments.value.length === 0) return 0;
    return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const paginationEnd = computed(() => {
    return Math.min(currentPage.value * itemsPerPage.value, filteredAssignments.value.length);
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
// CREATE ALLOCATION MODAL
// =========================================================================
const isCreateModalOpen = ref(false);
const createForm = useForm({
    academic_session_id: props.selectedSessionId,
    academic_term_id: props.selectedTermId,
    class_id: '' as string | number,
    section_id: '' as string | number,
    subject_id: '' as string | number,
    teacher_id: '' as string | number,
    can_input_marks: true,
    can_take_attendance: false,
});

const availableSectionsForCreate = computed(() => {
    if (!createForm.class_id) return [];
    const cls = props.classes.find((c) => c.id === Number(createForm.class_id));
    return cls?.sections ?? [];
});

// Subjects allocated to selected class via curriculum or all subjects
const availableSubjectsForCreate = computed(() => {
    if (!createForm.class_id) return props.subjects;

    const assignedSubjectIds = props.classSubjects
        .filter((cs) => cs.class_id === Number(createForm.class_id))
        .map((cs) => cs.subject_id);

    if (assignedSubjectIds.length > 0) {
        return props.subjects.filter((s) => assignedSubjectIds.includes(s.id));
    }
    return props.subjects;
});

watch(
    () => createForm.class_id,
    () => {
        createForm.section_id = '';
        createForm.subject_id = '';
    },
);

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.academic_session_id = props.selectedSessionId;
    createForm.academic_term_id = props.selectedTermId;
    createForm.class_id = '';
    createForm.section_id = '';
    createForm.subject_id = '';
    createForm.teacher_id = '';
    createForm.can_input_marks = true;
    createForm.can_take_attendance = false;
    isCreateModalOpen.value = true;
};

const submitCreateForm = () => {
    createForm.post(storeAllocation().url, {
        preserveScroll: true,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            toast.success('Teacher assigned to subject successfully.');
        },
        onError: () => {
            toast.error('Failed to create allocation. Check required selections or duplicates.');
        },
    });
};

// =========================================================================
// EDIT ALLOCATION MODAL
// =========================================================================
const isEditModalOpen = ref(false);
const editingAssignment = ref<any>(null);

const editForm = useForm({
    teacher_id: '' as string | number,
    can_input_marks: true,
    can_take_attendance: false,
});

const openEditModal = (assignment: any) => {
    editingAssignment.value = assignment;
    editForm.reset();
    editForm.clearErrors();
    editForm.teacher_id = assignment.teacher_id;
    editForm.can_input_marks = assignment.can_input_marks;
    editForm.can_take_attendance = assignment.can_take_attendance;
    isEditModalOpen.value = true;
};

const submitEditForm = () => {
    if (!editingAssignment.value) return;

    editForm.put(updateAllocation(editingAssignment.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
            toast.success('Teaching assignment updated.');
        },
        onError: () => {
            toast.error('Failed to update allocation.');
        },
    });
};

// =========================================================================
// DELETE ALLOCATION MODAL
// =========================================================================
const isDeleteModalOpen = ref(false);
const assignmentToDelete = ref<any>(null);
const deleteForm = useForm({});

const openDeleteModal = (assignment: any) => {
    assignmentToDelete.value = assignment;
    isDeleteModalOpen.value = true;
};

const confirmDeleteAssignment = () => {
    if (!assignmentToDelete.value) return;

    deleteForm.delete(destroyAllocation(assignmentToDelete.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            assignmentToDelete.value = null;
            toast.success('Teaching allocation removed.');
        },
        onError: () => {
            toast.error('Failed to remove allocation.');
        },
    });
};
</script>

<template>
    <Head title="Teacher Subject Allocations" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Teacher Subject Allocations
                </h1>
                <p class="text-sm text-muted-foreground">
                    Assign teachers to specific subjects and class sections per session.
                </p>
            </div>

            <!-- Session & Term Selectors + Action Button -->
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <div class="flex items-center gap-1.5 bg-background border border-border/60 rounded-md px-3 py-1.5 shadow-xs">
                    <span class="text-xs text-muted-foreground font-medium">Session:</span>
                    <select
                        :value="selectedSessionId"
                        class="bg-transparent text-sm font-semibold focus:outline-hidden cursor-pointer"
                        @change="(e) => onSessionChange((e.target as HTMLSelectElement).value)"
                    >
                        <option v-for="s in sessions" :key="s.id" :value="s.id">
                            {{ s.name }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-1.5 bg-background border border-border/60 rounded-md px-3 py-1.5 shadow-xs">
                    <span class="text-xs text-muted-foreground font-medium">Term:</span>
                    <select
                        :value="selectedTermId"
                        class="bg-transparent text-sm font-semibold focus:outline-hidden cursor-pointer"
                        @change="(e) => onTermChange((e.target as HTMLSelectElement).value)"
                    >
                        <option v-for="t in availableTerms" :key="t.id" :value="t.id">
                            {{ t.name }} {{ t.is_current ? '(Active)' : '' }}
                        </option>
                    </select>
                </div>

                <Button
                    v-if="canCreateAllocation"
                    class="h-10 w-full sm:w-auto gap-2 shadow-sm"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" />
                    <span>Assign Teacher</span>
                </Button>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Total Allocations
                    </CardTitle>
                    <ClipboardList class="h-4 w-4 text-primary" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight">
                        {{ stats.total_allocations }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ currentTerm?.name ?? 'Current Term' }}
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Assigned Teachers
                    </CardTitle>
                    <Users class="h-4 w-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ stats.assigned_teachers_count }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Active staff taking classes
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Grade Input Access
                    </CardTitle>
                    <PenTool class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                        {{ stats.can_input_marks_count }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Authorized to submit CA & exam
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Attendance Access
                    </CardTitle>
                    <ClipboardCheck class="h-4 w-4 text-purple-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-purple-600 dark:text-purple-400">
                        {{ stats.can_take_attendance_count }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Authorized for register rolls
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter & Search Controls -->
        <Card class="border-border/60 shadow-xs">
            <CardContent class="p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search by teacher name, staff ID, subject name, or code..."
                            class="pl-9 h-10 w-full"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Class Filter -->
                        <select
                            v-model="selectedClassFilter"
                            class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="all">All Classes</option>
                            <option v-for="c in classes" :key="c.id" :value="String(c.id)">
                                {{ c.name }}
                            </option>
                        </select>

                        <!-- Section Filter -->
                        <select
                            v-model="selectedSectionFilter"
                            class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            :disabled="selectedClassFilter === 'all'"
                        >
                            <option value="all">All Sections</option>
                            <option v-for="s in availableSectionsForFilter" :key="s.id" :value="String(s.id)">
                                Section {{ s.name }}
                            </option>
                        </select>

                        <!-- Teacher Filter -->
                        <select
                            v-model="selectedTeacherFilter"
                            class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="all">All Teachers</option>
                            <option v-for="t in teachers" :key="t.id" :value="String(t.id)">
                                {{ t.user?.name }} ({{ t.staff_id }})
                            </option>
                        </select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Allocations Table -->
        <Card class="border-border/60 shadow-xs">
            <CardHeader class="px-6 py-4 border-b border-border/40">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-semibold">Teacher Teaching Allocations</CardTitle>
                        <CardDescription>
                            Showing {{ paginationStart }} - {{ paginationEnd }} of {{ filteredAssignments.length }} allocations for {{ currentTerm?.name }}
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/40 border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3.5">Assigned Teacher</th>
                            <th class="px-6 py-3.5">Subject</th>
                            <th class="px-6 py-3.5">Class & Section</th>
                            <th class="px-6 py-3.5">Permissions</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr
                            v-for="assignment in paginatedAssignments"
                            :key="assignment.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium text-foreground">
                                    {{ assignment.teacher?.user?.name }}
                                </div>
                                <div class="text-xs text-muted-foreground font-mono mt-0.5">
                                    {{ assignment.teacher?.staff_id }}
                                    <span v-if="assignment.teacher?.department" class="text-muted-foreground/80 font-sans">
                                        • {{ assignment.teacher.department.name }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="font-medium text-foreground">
                                    {{ assignment.subject?.name }}
                                </div>
                                <div class="text-xs text-muted-foreground font-mono">
                                    {{ assignment.subject?.code }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-medium text-foreground">
                                        {{ assignment.school_class?.name }}
                                    </span>
                                    <Badge variant="secondary" class="text-[10px] font-semibold">
                                        Section {{ assignment.section?.name }}
                                    </Badge>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <Badge
                                        :variant="assignment.can_input_marks ? 'secondary' : 'outline'"
                                        :class="assignment.can_input_marks ? 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200' : 'text-muted-foreground line-through'"
                                        class="text-xs gap-1"
                                    >
                                        <CheckCircle2 v-if="assignment.can_input_marks" class="h-3 w-3" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <span>Marks Entry</span>
                                    </Badge>

                                    <Badge
                                        :variant="assignment.can_take_attendance ? 'secondary' : 'outline'"
                                        :class="assignment.can_take_attendance ? 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-200' : 'text-muted-foreground line-through'"
                                        class="text-xs gap-1"
                                    >
                                        <CheckCircle2 v-if="assignment.can_take_attendance" class="h-3 w-3" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <span>Attendance</span>
                                    </Badge>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Button
                                        v-if="canUpdateAllocation"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                        title="Edit Allocation / Permissions"
                                        @click="openEditModal(assignment)"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </Button>

                                    <Button
                                        v-if="canDeleteAllocation"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                        title="Delete Allocation"
                                        @click="openDeleteModal(assignment)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="paginatedAssignments.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <ClipboardList class="h-8 w-8 text-muted-foreground/60" />
                                    <p class="font-medium">No teaching allocations found for this session & term.</p>
                                    <p class="text-xs">Select another term or click "Assign Teacher" to create an allocation.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer (15 items per page) -->
            <div
                v-if="filteredAssignments.length > 0"
                class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-border/40"
            >
                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-medium text-foreground">{{ paginationStart }}</span> to
                    <span class="font-medium text-foreground">{{ paginationEnd }}</span> of
                    <span class="font-medium text-foreground">{{ filteredAssignments.length }}</span> entries
                </div>

                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="currentPage === 1"
                        @click="currentPage = Math.max(1, currentPage - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>

                    <div class="flex items-center gap-1 px-1">
                        <Button
                            v-for="pg in displayedPageNumbers"
                            :key="pg"
                            variant="outline"
                            size="sm"
                            class="h-8 min-w-[2rem] px-2 text-xs"
                            :class="{
                                'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground':
                                    currentPage === pg,
                            }"
                            @click="currentPage = pg"
                        >
                            {{ pg }}
                        </Button>
                    </div>

                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage = Math.min(totalPages, currentPage + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </Card>

        <!-- Create Allocation Dialog -->
        <Dialog v-model:open="isCreateModalOpen">
            <DialogScrollContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Assign Teacher to Subject</DialogTitle>
                    <DialogDescription>
                        Allocate a subject teacher to a class section for {{ currentTerm?.name }} ({{ currentSession?.name }}).
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCreateForm" class="space-y-4 py-2">
                    <div class="space-y-1">
                        <Label for="class_id">Class <span class="text-destructive">*</span></Label>
                        <select
                            id="class_id"
                            v-model="createForm.class_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            required
                        >
                            <option value="">Select class...</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                        <InputError :message="createForm.errors.class_id" />
                    </div>

                    <div class="space-y-1">
                        <Label for="section_id">Section <span class="text-destructive">*</span></Label>
                        <select
                            id="section_id"
                            v-model="createForm.section_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            :disabled="!createForm.class_id"
                            required
                        >
                            <option value="">Select section...</option>
                            <option v-for="s in availableSectionsForCreate" :key="s.id" :value="s.id">
                                Section {{ s.name }} (Capacity: {{ s.capacity }})
                            </option>
                        </select>
                        <InputError :message="createForm.errors.section_id" />
                    </div>

                    <div class="space-y-1">
                        <Label for="subject_id">Subject <span class="text-destructive">*</span></Label>
                        <select
                            id="subject_id"
                            v-model="createForm.subject_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            :disabled="!createForm.class_id"
                            required
                        >
                            <option value="">Select subject...</option>
                            <option v-for="sub in availableSubjectsForCreate" :key="sub.id" :value="sub.id">
                                {{ sub.name }} ({{ sub.code }})
                            </option>
                        </select>
                        <InputError :message="createForm.errors.subject_id" />
                    </div>

                    <div class="space-y-1">
                        <Label for="teacher_id">Assigned Teacher <span class="text-destructive">*</span></Label>
                        <select
                            id="teacher_id"
                            v-model="createForm.teacher_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            required
                        >
                            <option value="">Select teacher...</option>
                            <option v-for="t in teachers" :key="t.id" :value="t.id">
                                {{ t.user?.name }} ({{ t.staff_id }})
                            </option>
                        </select>
                        <InputError :message="createForm.errors.teacher_id" />
                    </div>

                    <div class="pt-3 border-t border-border/40 space-y-3">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Teacher Permissions for this Allocation
                        </Label>

                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="can_input_marks"
                                :checked="createForm.can_input_marks"
                                @update:checked="(val: boolean) => (createForm.can_input_marks = val)"
                            />
                            <Label for="can_input_marks" class="text-sm font-medium leading-none cursor-pointer">
                                Authorize continuous assessment & exam marks entry
                            </Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="can_take_attendance"
                                :checked="createForm.can_take_attendance"
                                @update:checked="(val: boolean) => (createForm.can_take_attendance = val)"
                            />
                            <Label for="can_take_attendance" class="text-sm font-medium leading-none cursor-pointer">
                                Authorize daily attendance register submission
                            </Label>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCreateModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="createForm.processing"
                        >
                            <Spinner v-if="createForm.processing" class="mr-2 h-4 w-4" />
                            <span>Save Allocation</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- Edit Allocation Dialog -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Teaching Allocation</DialogTitle>
                    <DialogDescription>
                        Reassign teacher or update assessment permissions for
                        <span class="font-semibold text-foreground">{{ editingAssignment?.subject?.name }}</span>
                        ({{ editingAssignment?.school_class?.name }} - Section {{ editingAssignment?.section?.name }}).
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEditForm" class="space-y-4 py-2">
                    <div class="space-y-1">
                        <Label for="edit_teacher_id">Assigned Teacher <span class="text-destructive">*</span></Label>
                        <select
                            id="edit_teacher_id"
                            v-model="editForm.teacher_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            required
                        >
                            <option v-for="t in teachers" :key="t.id" :value="t.id">
                                {{ t.user?.name }} ({{ t.staff_id }})
                            </option>
                        </select>
                        <InputError :message="editForm.errors.teacher_id" />
                    </div>

                    <div class="pt-3 border-t border-border/40 space-y-3">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Teacher Permissions
                        </Label>

                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="edit_can_input_marks"
                                :checked="editForm.can_input_marks"
                                @update:checked="(val: boolean) => (editForm.can_input_marks = val)"
                            />
                            <Label for="edit_can_input_marks" class="text-sm font-medium leading-none cursor-pointer">
                                Authorize marks entry
                            </Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="edit_can_take_attendance"
                                :checked="editForm.can_take_attendance"
                                @update:checked="(val: boolean) => (editForm.can_take_attendance = val)"
                            />
                            <Label for="edit_can_take_attendance" class="text-sm font-medium leading-none cursor-pointer">
                                Authorize attendance register submission
                            </Label>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isEditModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="editForm.processing"
                        >
                            <Spinner v-if="editForm.processing" class="mr-2 h-4 w-4" />
                            <span>Update Allocation</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="h-5 w-5" />
                        <span>Remove Teaching Allocation</span>
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove
                        <span class="font-semibold text-foreground">{{ assignmentToDelete?.teacher?.user?.name }}</span>'s
                        assignment to {{ assignmentToDelete?.subject?.name }} ({{ assignmentToDelete?.school_class?.name }} - Section {{ assignmentToDelete?.section?.name }})?
                    </DialogDescription>
                </DialogHeader>

                <div class="text-xs text-muted-foreground">
                    This will revoke the teacher's access to marks entry and attendance for this class section. Any existing recorded marks will remain in the system.
                </div>

                <DialogFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="confirmDeleteAssignment"
                    >
                        <Spinner v-if="deleteForm.processing" class="mr-2 h-4 w-4" />
                        <span>Confirm Remove</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
