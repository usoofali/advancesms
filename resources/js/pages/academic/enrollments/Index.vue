<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Award,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Download,
    Edit2,
    Filter,
    GraduationCap,
    Layers,
    Plus,
    Printer,
    RotateCcw,
    Search,
    ShieldCheck,
    Trash2,
    TrendingUp,
    UserCheck,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import PrintHeader from '@/components/PrintHeader.vue';
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
    destroy as destroyEnrollment,
    index as academicEnrollmentsIndex,
    store as storeEnrollment,
    update as updateEnrollment,
} from '@/routes/academic/enrollments';
import type { AcademicSession, SchoolClass, Section } from '@/types/academic';
import type { EnrollmentSummary, StudentSummary } from '@/types/people';

interface Props {
    enrollments: (EnrollmentSummary & {
        student: StudentSummary & {
            user?: {
                id: number;
                name: string;
                gender: string;
                email: string | null;
                phone: string | null;
                is_active: boolean;
            };
            guardian?: {
                id: number;
                name: string;
                phone: string;
            } | null;
        };
        marks_count?: number;
        attendances_count?: number;
    })[];
    sessions: AcademicSession[];
    selectedSessionId: number;
    classes: (SchoolClass & { sections?: Section[] })[];
    unenrolledStudents: {
        id: number;
        user_id: number;
        admission_number: string;
        user?: {
            id: number;
            name: string;
            gender: string;
        };
    }[];
    stats: {
        total_enrolled: number;
        active_count: number;
        promoted_count: number;
        repeated_count: number;
        graduated_count: number;
    };
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Student Enrollments',
                href: academicEnrollmentsIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateEnrollment = computed(() => isSuperAdmin.value || userPermissions.value.includes('enrollments.create'));
const canUpdateEnrollment = computed(() => isSuperAdmin.value || userPermissions.value.includes('enrollments.update'));
const canDeleteEnrollment = computed(() => isSuperAdmin.value || userPermissions.value.includes('enrollments.delete'));

// Session Switcher
const currentSession = computed(() => props.sessions.find((s) => s.id === props.selectedSessionId));

const onSessionChange = (sessionId: number | string) => {
    router.get(
        academicEnrollmentsIndex().url,
        { session_id: sessionId },
        { preserveState: true, preserveScroll: true },
    );
};

// =========================================================================
// TAB 1: ENROLLMENT ROSTER (SEARCH, FILTERS, 15-ITEM PAGINATION)
// =========================================================================
const searchQuery = ref('');
const selectedClassFilter = ref<string>('all');
const selectedSectionFilter = ref<string>('all');
const selectedStatusFilter = ref<string>('all');

const availableSectionsForFilter = computed(() => {
    if (selectedClassFilter.value === 'all') return [];
    const cls = props.classes.find((c) => c.id === Number(selectedClassFilter.value));
    return cls?.sections ?? [];
});

watch(selectedClassFilter, () => {
    selectedSectionFilter.value = 'all';
});

const filteredEnrollments = computed(() => {
    return props.enrollments.filter((enrollment) => {
        const query = searchQuery.value.toLowerCase().trim();
        const studentName = enrollment.student?.user?.name?.toLowerCase() ?? '';
        const admissionNum = enrollment.student?.admission_number?.toLowerCase() ?? '';
        const guardianName = enrollment.student?.guardian?.name?.toLowerCase() ?? '';

        const matchesSearch =
            !query ||
            studentName.includes(query) ||
            admissionNum.includes(query) ||
            guardianName.includes(query);

        const matchesClass =
            selectedClassFilter.value === 'all' ||
            enrollment.class_id === Number(selectedClassFilter.value);

        const matchesSection =
            selectedSectionFilter.value === 'all' ||
            enrollment.section_id === Number(selectedSectionFilter.value);

        const matchesStatus =
            selectedStatusFilter.value === 'all' ||
            enrollment.status === selectedStatusFilter.value;

        return matchesSearch && matchesClass && matchesSection && matchesStatus;
    });
});

const currentPage = ref(1);
const itemsPerPage = ref(15);

watch([searchQuery, selectedClassFilter, selectedSectionFilter, selectedStatusFilter], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => {
    return Math.ceil(filteredEnrollments.value.length / itemsPerPage.value) || 1;
});

const paginatedEnrollments = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredEnrollments.value.slice(start, start + itemsPerPage.value);
});

const paginationStart = computed(() => {
    if (filteredEnrollments.value.length === 0) return 0;
    return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const paginationEnd = computed(() => {
    return Math.min(currentPage.value * itemsPerPage.value, filteredEnrollments.value.length);
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

const selectedSessionObj = computed(() => props.sessions.find((s) => s.id === props.selectedSessionId));
const selectedSessionName = computed(() => selectedSessionObj.value ? `${selectedSessionObj.value.name} Academic Session` : '');

const selectedClassName = computed(() => {
    if (selectedClassFilter.value === 'all') return 'All Classes';
    const foundCls = props.classes.find((c) => String(c.id) === selectedClassFilter.value);
    return foundCls ? foundCls.name : 'All Classes';
});

const exportCsvUrl = computed(() => {
    return `${academicEnrollmentsIndex().url}?session_id=${props.selectedSessionId}&export=csv`;
});

const printRoster = () => {
    window.print();
};

// =========================================================================
// ENROLL STUDENT MODAL
// =========================================================================
const isEnrollModalOpen = ref(false);
const enrollForm = useForm({
    academic_session_id: props.selectedSessionId,
    student_id: '' as string | number,
    class_id: '' as string | number,
    section_id: '' as string | number,
    roll_number: '',
});

const availableSectionsForEnroll = computed(() => {
    if (!enrollForm.class_id) return [];
    const cls = props.classes.find((c) => c.id === Number(enrollForm.class_id));
    return cls?.sections ?? [];
});

watch(
    () => enrollForm.class_id,
    () => {
        enrollForm.section_id = '';
    },
);

const openEnrollModal = () => {
    enrollForm.reset();
    enrollForm.clearErrors();
    enrollForm.academic_session_id = props.selectedSessionId;
    enrollForm.student_id = '';
    enrollForm.class_id = '';
    enrollForm.section_id = '';
    enrollForm.roll_number = '';
    isEnrollModalOpen.value = true;
};

const submitEnrollForm = () => {
    enrollForm.post(storeEnrollment().url, {
        preserveScroll: true,
        onSuccess: () => {
            isEnrollModalOpen.value = false;
            toast.success('Student enrolled successfully.');
        },
        onError: () => {
            toast.error('Failed to enroll student. Please check input requirements.');
        },
    });
};

// =========================================================================
// EDIT / TRANSFER SECTION MODAL
// =========================================================================
const isEditModalOpen = ref(false);
const editingEnrollment = ref<any>(null);

const editForm = useForm({
    class_id: '' as string | number,
    section_id: '' as string | number,
    roll_number: '',
    status: 'active',
});

const availableSectionsForEdit = computed(() => {
    if (!editForm.class_id) return [];
    const cls = props.classes.find((c) => c.id === Number(editForm.class_id));
    return cls?.sections ?? [];
});

watch(
    () => editForm.class_id,
    (newVal, oldVal) => {
        if (oldVal && newVal !== oldVal) {
            editForm.section_id = '';
        }
    },
);

const openEditModal = (enrollment: any) => {
    editingEnrollment.value = enrollment;
    editForm.reset();
    editForm.clearErrors();
    editForm.class_id = enrollment.class_id;
    editForm.section_id = enrollment.section_id;
    editForm.roll_number = enrollment.roll_number ?? '';
    editForm.status = enrollment.status;
    isEditModalOpen.value = true;
};

const submitEditForm = () => {
    if (!editingEnrollment.value) return;

    editForm.put(updateEnrollment(editingEnrollment.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
            toast.success('Enrollment updated successfully.');
        },
        onError: () => {
            toast.error('Failed to update enrollment record.');
        },
    });
};

// =========================================================================
// DELETE ENROLLMENT
// =========================================================================
const isDeleteModalOpen = ref(false);
const enrollmentToDelete = ref<any>(null);
const deleteForm = useForm({});

const openDeleteModal = (enrollment: any) => {
    enrollmentToDelete.value = enrollment;
    isDeleteModalOpen.value = true;
};

const hasProtectedRecords = computed(() => {
    if (!enrollmentToDelete.value) return false;
    return (
        (enrollmentToDelete.value.marks_count ?? 0) > 0 ||
        (enrollmentToDelete.value.attendances_count ?? 0) > 0
    );
});

const confirmDeleteEnrollment = () => {
    if (!enrollmentToDelete.value) return;

    deleteForm.delete(destroyEnrollment(enrollmentToDelete.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            enrollmentToDelete.value = null;
            toast.success('Enrollment record removed.');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.error(errors.error);
            } else {
                toast.error('Failed to remove enrollment.');
            }
        },
    });
};

</script>

<template>
    <Head title="Student Enrollments" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Printable Official Institutional Letterhead (Hidden on screen, active on print) -->
        <PrintHeader
            title="Official Student Enrollment Roster"
            subtitle="Verified Session-Based Student Allocation Register"
            :session="selectedSessionName"
            :className="selectedClassName"
        />

        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Student Enrollments
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Manage session-based student class allocations, section transfers, roll numbers, and active rosters.
                </p>
            </div>

            <!-- Session Selector & Action Buttons -->
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <div class="flex items-center gap-1.5 bg-background border border-border/60 rounded-md px-3 py-1.5 shadow-xs">
                    <span class="text-xs text-muted-foreground font-medium">Session:</span>
                    <select
                        :value="selectedSessionId"
                        class="bg-transparent text-sm font-semibold focus:outline-hidden cursor-pointer"
                        @change="(e) => onSessionChange((e.target as HTMLSelectElement).value)"
                    >
                        <option
                            v-for="s in sessions"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.name }} {{ s.is_current ? '(Current Active)' : '' }}
                        </option>
                    </select>
                </div>

                <!-- Export CSV Button -->
                <a
                    :href="exportCsvUrl"
                    class="inline-flex items-center gap-1.5 h-10 px-3.5 rounded-md border border-input bg-background hover:bg-muted/60 text-foreground text-xs font-semibold shadow-xs transition-colors"
                    title="Export filtered roster to CSV"
                >
                    <Download class="h-4 w-4 text-muted-foreground" />
                    <span>Export CSV</span>
                </a>

                <!-- Print Roster Button -->
                <Button
                    variant="outline"
                    class="h-10 gap-1.5 text-xs font-semibold shadow-xs"
                    @click="printRoster"
                    title="Print Student Enrollment Roster"
                >
                    <Printer class="h-4 w-4 text-muted-foreground" />
                    <span>Print Roster</span>
                </Button>

                <Button
                    v-if="canCreateEnrollment"
                    class="h-10 w-full sm:w-auto gap-2 shadow-sm"
                    @click="openEnrollModal"
                >
                    <Plus class="h-4 w-4" />
                    <span>Enroll Student</span>
                </Button>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 print:hidden">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Total Enrolled
                    </CardTitle>
                    <Users class="h-4 w-4 text-primary" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight">
                        {{ stats.total_enrolled }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ currentSession?.name }} Session
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Active In Class
                    </CardTitle>
                    <UserCheck class="h-4 w-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ stats.active_count }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Currently attending
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Promoted Students
                    </CardTitle>
                    <TrendingUp class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                        {{ stats.promoted_count }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Advanced to next grade
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Repeated / Graduated
                    </CardTitle>
                    <Award class="h-4 w-4 text-purple-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-purple-600 dark:text-purple-400">
                        {{ stats.repeated_count }} / {{ stats.graduated_count }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Retained vs Alumni
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- SESSION ROSTER VIEW -->
        <div class="space-y-4">
            <!-- Filter & Search Controls -->
            <Card class="border-border/60 shadow-xs print:hidden">
                <CardContent class="p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Search by student name, admission number, or guardian..."
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

                            <!-- Section Filter (Dynamic based on selected class) -->
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

                            <!-- Status Filter -->
                            <select
                                v-model="selectedStatusFilter"
                                class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="all">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="promoted">Promoted</option>
                                <option value="repeated">Repeated</option>
                                <option value="withdrawn">Withdrawn</option>
                                <option value="graduated">Graduated</option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Enrollment Table -->
            <Card class="border-border/60 shadow-xs print:hidden">
                <CardHeader class="px-6 py-4 border-b border-border/40">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base font-semibold">Enrolled Students Roster</CardTitle>
                            <CardDescription>
                                Showing {{ paginationStart }} - {{ paginationEnd }} of {{ filteredEnrollments.length }} students in {{ currentSession?.name }}
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/40 border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3.5">Admission #</th>
                                <th class="px-6 py-3.5">Student Name</th>
                                <th class="px-6 py-3.5">Class & Section</th>
                                <th class="px-6 py-3.5">Previous Class</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5">Guardian Contact</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="enrollment in paginatedEnrollments"
                                :key="enrollment.id"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td class="px-6 py-4">
                                    <span class="font-mono font-semibold text-xs text-primary">
                                        {{ enrollment.student?.admission_number }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="font-medium text-foreground">
                                        {{ enrollment.student?.user?.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground capitalize">
                                        {{ enrollment.student?.user?.gender }}
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-medium text-foreground">
                                            {{ enrollment.school_class?.name }}
                                        </span>
                                        <Badge variant="secondary" class="text-[10px] font-semibold">
                                            Section {{ enrollment.section?.name }}
                                        </Badge>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div v-if="enrollment.previous_enrollment" class="text-xs">
                                        <div class="font-medium text-foreground flex items-center gap-1.5">
                                            <span>{{ enrollment.previous_enrollment.school_class?.name }}</span>
                                            <Badge variant="outline" class="text-[10px] py-0 px-1 font-normal">
                                                Sec {{ enrollment.previous_enrollment.section?.name }}
                                            </Badge>
                                        </div>
                                        <div class="text-[11px] text-muted-foreground mt-0.5">
                                            Session: {{ enrollment.previous_enrollment.academic_session?.name }}
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground italic">
                                        — (Initial Entry)
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <Badge
                                        variant="outline"
                                        class="capitalize text-xs font-medium"
                                        :class="{
                                            'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200': enrollment.status === 'active',
                                            'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200': enrollment.status === 'promoted',
                                            'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200': enrollment.status === 'repeated',
                                            'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-200': enrollment.status === 'graduated',
                                            'bg-destructive/10 text-destructive border-destructive/20': enrollment.status === 'withdrawn',
                                        }"
                                    >
                                        {{ enrollment.status }}
                                    </Badge>
                                </td>

                                <td class="px-6 py-4">
                                    <div v-if="enrollment.student?.guardian" class="text-xs">
                                        <div class="font-medium text-foreground">
                                            {{ enrollment.student.guardian.name }}
                                        </div>
                                        <div class="text-muted-foreground text-[11px]">
                                            {{ enrollment.student.guardian.phone }}
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground italic">
                                        None linked
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            v-if="canUpdateEnrollment"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                            title="Transfer Section / Edit Status"
                                            @click="openEditModal(enrollment)"
                                        >
                                            <Edit2 class="h-4 w-4" />
                                        </Button>

                                        <Button
                                            v-if="canDeleteEnrollment"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                            title="Remove Enrollment"
                                            @click="openDeleteModal(enrollment)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="paginatedEnrollments.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <Users class="h-8 w-8 text-muted-foreground/60" />
                                        <p class="font-medium">No student enrollments found matching your query.</p>
                                        <p class="text-xs">Change your filters or click "Enroll Student" to register an active student.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer (15 items per page) -->
                <div
                    v-if="filteredEnrollments.length > 0"
                    class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-border/40"
                >
                    <div class="text-xs text-muted-foreground">
                        Showing <span class="font-medium text-foreground">{{ paginationStart }}</span> to
                        <span class="font-medium text-foreground">{{ paginationEnd }}</span> of
                        <span class="font-medium text-foreground">{{ filteredEnrollments.length }}</span> entries
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

            <!-- Printable Roster Table (Hidden on Screen, Shown in Print) -->
            <div class="hidden print:block w-full mt-2">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b-2 border-black">
                            <th class="py-2 px-1 text-center font-bold w-10">#</th>
                            <th class="py-2 px-2 font-bold">Admission #</th>
                            <th class="py-2 px-2 font-bold">Student Name</th>
                            <th class="py-2 px-2 font-bold">Class & Section</th>
                            <th class="py-2 px-2 font-bold">Gender</th>
                            <th class="py-2 px-2 font-bold">Guardian Contact</th>
                            <th class="py-2 px-2 font-bold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-300">
                        <tr v-for="(enrollment, index) in filteredEnrollments" :key="enrollment.id" class="border-b border-gray-200">
                            <td class="py-1.5 px-1 text-center font-mono">{{ index + 1 }}</td>
                            <td class="py-1.5 px-2 font-mono font-medium">{{ enrollment.student?.admission_number ?? '—' }}</td>
                            <td class="py-1.5 px-2 font-medium">{{ enrollment.student?.user?.name ?? '—' }}</td>
                            <td class="py-1.5 px-2">
                                {{ enrollment.school_class?.name ?? '—' }}
                                <span v-if="enrollment.section"> ({{ enrollment.section.name }})</span>
                            </td>
                            <td class="py-1.5 px-2 capitalize">{{ enrollment.student?.user?.gender ?? '—' }}</td>
                            <td class="py-1.5 px-2">
                                <span v-if="enrollment.student?.guardian?.phone">{{ enrollment.student.guardian.phone }}</span>
                                <span v-else-if="enrollment.student?.guardian?.name">{{ enrollment.student.guardian.name }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="py-1.5 px-2 text-center capitalize">{{ enrollment.status }}</td>
                        </tr>
                        <tr v-if="filteredEnrollments.length === 0">
                            <td colspan="7" class="py-6 text-center italic text-gray-500">
                                No student enrollments found matching the selected criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-8 pt-4 border-t border-gray-300 flex justify-between text-xs text-gray-600">
                    <div>
                        <p class="font-medium">Total Enrolled Listed: {{ filteredEnrollments.length }}</p>
                    </div>
                    <div class="text-right">
                        <p class="border-t border-black pt-1 w-48 text-center ml-auto font-medium">Class Teacher / Principal Signature</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- ENROLL STUDENT DIALOG -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isEnrollModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Enroll Student into Session</DialogTitle>
                    <DialogDescription>
                        Assign an unallocated student to a class and section in {{ currentSession?.name }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEnrollForm" class="space-y-4 py-2">
                    <div class="space-y-1">
                        <Label for="student_id">Select Student <span class="text-destructive">*</span></Label>
                        <select
                            id="student_id"
                            v-model="enrollForm.student_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            required
                        >
                            <option value="">Select unallocated student...</option>
                            <option
                                v-for="st in unenrolledStudents"
                                :key="st.id"
                                :value="st.id"
                            >
                                {{ st.user?.name }} ({{ st.admission_number }})
                            </option>
                        </select>
                        <InputError :message="enrollForm.errors.student_id" />
                    </div>

                    <div class="space-y-1">
                        <Label for="class_id">Class <span class="text-destructive">*</span></Label>
                        <select
                            id="class_id"
                            v-model="enrollForm.class_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            required
                        >
                            <option value="">Select class...</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                        <InputError :message="enrollForm.errors.class_id" />
                    </div>

                    <div class="space-y-1">
                        <Label for="section_id">Section <span class="text-destructive">*</span></Label>
                        <select
                            id="section_id"
                            v-model="enrollForm.section_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            :disabled="!enrollForm.class_id"
                            required
                        >
                            <option value="">Select section...</option>
                            <option v-for="s in availableSectionsForEnroll" :key="s.id" :value="s.id">
                                Section {{ s.name }} (Cap: {{ s.capacity ?? '∞' }})
                            </option>
                        </select>
                        <InputError :message="enrollForm.errors.section_id" />
                    </div>

                    <div class="space-y-1">
                        <Label for="roll_number">Roll / Desk #</Label>
                        <Input
                            id="roll_number"
                            v-model="enrollForm.roll_number"
                            placeholder="e.g. 12"
                        />
                        <InputError :message="enrollForm.errors.roll_number" />
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isEnrollModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="enrollForm.processing"
                        >
                            <Spinner v-if="enrollForm.processing" class="mr-2 h-4 w-4" />
                            <span>Enroll Student</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ========================================================================= -->
        <!-- EDIT / TRANSFER SECTION DIALOG -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Transfer Section / Update Record</DialogTitle>
                    <DialogDescription>
                        Update the class section allocation or progression status for
                        <span class="font-semibold text-foreground">{{ editingEnrollment?.student?.user?.name }}</span>.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEditForm" class="space-y-4 py-2">
                    <div class="space-y-1">
                        <Label for="edit_class_id">Class <span class="text-destructive">*</span></Label>
                        <select
                            id="edit_class_id"
                            v-model="editForm.class_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            required
                        >
                            <option v-for="c in classes" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                        <InputError :message="editForm.errors.class_id" />
                    </div>

                    <div class="space-y-1">
                        <Label for="edit_section_id">Section <span class="text-destructive">*</span></Label>
                        <select
                            id="edit_section_id"
                            v-model="editForm.section_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            required
                        >
                            <option v-for="s in availableSectionsForEdit" :key="s.id" :value="s.id">
                                Section {{ s.name }}
                            </option>
                        </select>
                        <InputError :message="editForm.errors.section_id" />
                    </div>

                    <div class="space-y-1">
                        <Label for="edit_roll_number">Roll / Desk #</Label>
                        <Input
                            id="edit_roll_number"
                            v-model="editForm.roll_number"
                            placeholder="e.g. 15"
                        />
                        <InputError :message="editForm.errors.roll_number" />
                    </div>

                    <div class="space-y-1">
                        <Label for="edit_status">Enrollment Status <span class="text-destructive">*</span></Label>
                        <select
                            id="edit_status"
                            v-model="editForm.status"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs capitalize"
                            required
                        >
                            <option value="active">Active</option>
                            <option value="promoted">Promoted</option>
                            <option value="repeated">Repeated</option>
                            <option value="withdrawn">Withdrawn</option>
                            <option value="graduated">Graduated</option>
                        </select>
                        <InputError :message="editForm.errors.status" />
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
                            <span>Save Changes</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ========================================================================= -->
        <!-- DELETE ENROLLMENT DIALOG -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="h-5 w-5" />
                        <span>Remove Enrollment Record</span>
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove the enrollment for
                        <span class="font-semibold text-foreground">{{ enrollmentToDelete?.student?.user?.name }}</span>
                        from {{ currentSession?.name }}?
                    </DialogDescription>
                </DialogHeader>

                <div v-if="hasProtectedRecords" class="rounded-md bg-destructive/10 p-3 text-xs text-destructive flex items-start gap-2">
                    <AlertTriangle class="h-4 w-4 shrink-0 mt-0.5" />
                    <div>
                        <strong>Academic Records Protected:</strong> This student has continuous assessment marks or attendance records recorded in this session. The enrollment cannot be removed to preserve academic audit integrity.
                    </div>
                </div>

                <div v-else class="text-xs text-muted-foreground">
                    This will unenroll the student from this academic session. The student profile will remain available in the general directory.
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
                        :disabled="deleteForm.processing || hasProtectedRecords"
                        @click="confirmDeleteEnrollment"
                    >
                        <Spinner v-if="deleteForm.processing" class="mr-2 h-4 w-4" />
                        <span>Confirm Unenroll</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>

<style scoped>
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
}
</style>
