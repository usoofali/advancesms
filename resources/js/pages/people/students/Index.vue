<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Award,
    Calendar,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Download,
    Edit2,
    Eye,
    GraduationCap,
    HeartHandshake,
    IdCard,
    KeyRound,
    Layers,
    Lock,
    Mail,
    MapPin,
    Phone,
    Plus,
    Printer,
    RefreshCw,
    Search,
    ShieldCheck,
    Sparkles,
    Trash2,
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
import { index as peopleAlumniIndex } from '@/routes/people/alumni';
import {
    destroy as destroyStudent,
    index as peopleStudentsIndex,
    show as showStudent,
    store as storeStudent,
    update as updateStudent,
} from '@/routes/people/students';
import type { AcademicSession, SchoolClass, Section } from '@/types/academic';
import type { GuardianSummary, Student, StudentStats } from '@/types/people';

interface Props {
    students: Student[];
    classes: (SchoolClass & { sections?: Section[] })[];
    guardians: GuardianSummary[];
    sessions?: AcademicSession[];
    activeSession: { id: number; name: string } | null;
    nextAdmissionNumber?: string;
    defaultStudentPassword?: string;
    stats: StudentStats;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Students & Enrollment',
                href: peopleStudentsIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateStudent = computed(() => isSuperAdmin.value || userPermissions.value.includes('students.create'));
const canUpdateStudent = computed(() => isSuperAdmin.value || userPermissions.value.includes('students.update'));
const canDeleteStudent = computed(() => isSuperAdmin.value || userPermissions.value.includes('students.delete'));

const printRoster = () => {
    window.print();
};

const exportCsvUrl = computed(() => {
    const params = new URLSearchParams();
    params.set('export', 'csv');
    if (selectedSessionFilter.value !== 'all') params.set('session_id', selectedSessionFilter.value);
    if (selectedClassFilter.value !== 'all') params.set('class_id', selectedClassFilter.value);
    if (selectedGenderFilter.value !== 'all') params.set('gender', selectedGenderFilter.value);
    return `${peopleStudentsIndex().url}?${params.toString()}`;
});

// =========================================================================
// SEARCH & FILTERS
// =========================================================================
const searchQuery = ref('');
const selectedSessionFilter = ref<string>('all');
const selectedClassFilter = ref<string>('all');
const selectedSectionFilter = ref<string>('all');
const selectedGenderFilter = ref<string>('all');
const selectedEnrollmentFilter = ref<string>('all');

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value !== '' ||
        selectedSessionFilter.value !== 'all' ||
        selectedClassFilter.value !== 'all' ||
        selectedSectionFilter.value !== 'all' ||
        selectedGenderFilter.value !== 'all' ||
        selectedEnrollmentFilter.value !== 'all'
    );
});

const resetFilters = () => {
    searchQuery.value = '';
    selectedSessionFilter.value = 'all';
    selectedClassFilter.value = 'all';
    selectedSectionFilter.value = 'all';
    selectedGenderFilter.value = 'all';
    selectedEnrollmentFilter.value = 'all';
    currentPage.value = 1;
};

const availableSectionsForFilter = computed(() => {
    if (selectedClassFilter.value === 'all') {
        const uniqueNames = new Set<string>();
        props.classes.forEach((c) => {
            c.sections?.forEach((s) => uniqueNames.add(s.name));
        });
        return Array.from(uniqueNames).sort().map((name) => ({
            id: name,
            name: `Section ${name}`,
            isNameOnly: true,
        }));
    }
    const cls = props.classes.find((c) => String(c.id) === selectedClassFilter.value);
    return (cls?.sections ?? []).map((s) => ({
        id: String(s.id),
        name: `Section ${s.name}`,
        isNameOnly: false,
    }));
});

watch(selectedClassFilter, () => {
    selectedSectionFilter.value = 'all';
});

const filteredStudents = computed(() => {
    return props.students.filter((student) => {
        const query = searchQuery.value.toLowerCase().trim();
        const matchesSearch =
            !query ||
            student.user.name.toLowerCase().includes(query) ||
            student.admission_number.toLowerCase().includes(query) ||
            (student.user.email && student.user.email.toLowerCase().includes(query)) ||
            (student.user.phone && student.user.phone.toLowerCase().includes(query)) ||
            (student.guardian && student.guardian.name.toLowerCase().includes(query));

        const matchesSession =
            selectedSessionFilter.value === 'all' ||
            String(student.current_enrollment?.academic_session_id) === selectedSessionFilter.value ||
            Boolean(student.enrollments?.some((e) => String(e.academic_session_id) === selectedSessionFilter.value));

        const sessionEnrollment =
            selectedSessionFilter.value === 'all'
                ? student.current_enrollment
                : (student.enrollments?.find((e) => String(e.academic_session_id) === selectedSessionFilter.value) ?? student.current_enrollment);

        const matchesClass =
            selectedClassFilter.value === 'all' ||
            sessionEnrollment?.class_id === Number(selectedClassFilter.value) ||
            student.current_enrollment?.class_id === Number(selectedClassFilter.value);

        const matchesSection =
            selectedSectionFilter.value === 'all' ||
            (selectedClassFilter.value === 'all'
                ? (sessionEnrollment?.section?.name === selectedSectionFilter.value || student.current_enrollment?.section?.name === selectedSectionFilter.value)
                : (String(sessionEnrollment?.section_id) === selectedSectionFilter.value || String(student.current_enrollment?.section_id) === selectedSectionFilter.value));

        const matchesGender =
            selectedGenderFilter.value === 'all' ||
            student.user.gender === selectedGenderFilter.value;

        const matchesEnrollment =
            selectedEnrollmentFilter.value === 'all' ||
            (selectedEnrollmentFilter.value === 'enrolled' && student.current_enrollment !== null && student.current_enrollment !== undefined && student.current_enrollment?.status !== 'graduated') ||
            (selectedEnrollmentFilter.value === 'graduated' && student.current_enrollment?.status === 'graduated') ||
            (selectedEnrollmentFilter.value === 'unenrolled' && (!student.current_enrollment));

        return matchesSearch && matchesSession && matchesClass && matchesSection && matchesGender && matchesEnrollment;
    });
});

// =========================================================================
// PAGINATION (15 items per page)
// =========================================================================
const currentPage = ref(1);
const itemsPerPage = ref(15);

watch([searchQuery, selectedSessionFilter, selectedClassFilter, selectedSectionFilter, selectedGenderFilter, selectedEnrollmentFilter], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => {
    return Math.ceil(filteredStudents.value.length / itemsPerPage.value) || 1;
});

const paginatedStudents = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredStudents.value.slice(start, start + itemsPerPage.value);
});

const paginationStart = computed(() => {
    if (filteredStudents.value.length === 0) return 0;
    return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const paginationEnd = computed(() => {
    return Math.min(currentPage.value * itemsPerPage.value, filteredStudents.value.length);
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
// STUDENT CREATE & EDIT MODAL
// =========================================================================
const isStudentModalOpen = ref(false);
const editingStudent = ref<Student | null>(null);

const studentForm = useForm({
    first_name: '',
    last_name: '',
    gender: 'male' as 'male' | 'female',
    email: '',
    phone: '',
    username: '',
    password: '',
    admission_number: '',
    admission_date: new Date().toISOString().split('T')[0],
    date_of_birth: '',
    state_of_origin: '',
    lga: '',
    guardian_id: '' as string | number,
    class_id: '' as string | number,
    section_id: '' as string | number,
    is_active: true,
});

// Available sections computed based on selected class in create form
const availableSectionsForSelectedClass = computed(() => {
    if (!studentForm.class_id) return [];
    const selectedClass = props.classes.find((c) => c.id === Number(studentForm.class_id));
    return selectedClass?.sections ?? [];
});

watch(
    () => studentForm.class_id,
    () => {
        // If the current section does not belong to the selected class, reset it
        if (!editingStudent.value) {
            const hasSection = availableSectionsForSelectedClass.value.some(
                (s) => s.id === Number(studentForm.section_id),
            );
            if (!hasSection) {
                studentForm.section_id = '';
            }
        }
    },
);

const isCustomEmail = ref(false);
const isCustomUsername = ref(false);

const formatAdmissionForLogin = (adm: string): string => {
    if (!adm) return '';
    const noSlashesOrSpaces = adm.replace(/[/\\ ]/g, '');
    return noSlashesOrSpaces.toLowerCase().replace(/[^a-z0-9._-]/g, '') || 'student';
};

const getGeneratedStudentEmail = (adm: string): string => {
    const clean = formatAdmissionForLogin(adm);
    return clean ? `${clean}@sms.com` : '';
};

const getGeneratedStudentUsername = (adm: string): string => {
    return formatAdmissionForLogin(adm);
};

const setPasswordToDefault = () => {
    studentForm.password = props.defaultStudentPassword ?? '12345678';
    toast.info('Password set to default (12345678). Save changes to apply.');
};

watch(
    () => studentForm.admission_number,
    (newAdm) => {
        if (!editingStudent.value) {
            if (!isCustomEmail.value) {
                studentForm.email = getGeneratedStudentEmail(newAdm);
            }
            if (!isCustomUsername.value) {
                studentForm.username = getGeneratedStudentUsername(newAdm);
            }
        }
    },
);

const openCreateStudentModal = () => {
    editingStudent.value = null;
    studentForm.reset();
    studentForm.clearErrors();
    const nextAdm = props.nextAdmissionNumber ?? '';
    studentForm.admission_number = nextAdm;
    studentForm.email = getGeneratedStudentEmail(nextAdm);
    studentForm.username = getGeneratedStudentUsername(nextAdm);
    studentForm.password = props.defaultStudentPassword ?? '12345678';
    studentForm.admission_date = new Date().toISOString().split('T')[0];
    studentForm.gender = 'male';
    studentForm.guardian_id = '';
    studentForm.class_id = '';
    studentForm.section_id = '';
    studentForm.is_active = true;
    isCustomEmail.value = false;
    isCustomUsername.value = false;
    isStudentModalOpen.value = true;
};

const openEditStudentModal = (student: Student) => {
    editingStudent.value = student;
    studentForm.reset();
    studentForm.clearErrors();
    studentForm.first_name = student.user.first_name ?? '';
    studentForm.last_name = student.user.last_name ?? '';
    studentForm.gender = student.user.gender;
    studentForm.email = student.user.email ?? '';
    studentForm.phone = student.user.phone ?? '';
    studentForm.username = student.user.username ?? '';
    studentForm.password = '';
    studentForm.admission_number = student.admission_number;
    studentForm.admission_date = student.admission_date;
    studentForm.date_of_birth = student.date_of_birth ?? '';
    studentForm.state_of_origin = student.state_of_origin ?? '';
    studentForm.lga = student.lga ?? '';
    studentForm.guardian_id = student.guardian_id ?? '';
    studentForm.class_id = student.current_enrollment?.class_id ?? '';
    studentForm.section_id = student.current_enrollment?.section_id ?? '';
    studentForm.is_active = student.user.is_active;
    isStudentModalOpen.value = true;
};

const submitStudentForm = () => {
    if (editingStudent.value) {
        studentForm.put(updateStudent(editingStudent.value.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                isStudentModalOpen.value = false;
                toast.success('Student profile updated successfully.');
            },
            onError: () => {
                toast.error('Failed to update student profile. Please check the form.');
            },
        });
    } else {
        studentForm.post(storeStudent().url, {
            preserveScroll: true,
            onSuccess: () => {
                isStudentModalOpen.value = false;
                toast.success('New student registered successfully.');
            },
            onError: () => {
                toast.error('Failed to register student. Please check the form.');
            },
        });
    }
};

// =========================================================================
// DELETE CONFIRMATION
// =========================================================================
const isDeleteModalOpen = ref(false);
const studentToDelete = ref<Student | null>(null);
const deleteForm = useForm({});

const openDeleteModal = (student: Student) => {
    studentToDelete.value = student;
    isDeleteModalOpen.value = true;
};

const hasProtectedRecords = computed(() => {
    if (!studentToDelete.value) return false;
    return (
        (studentToDelete.value.student_marks_count ?? 0) > 0 ||
        (studentToDelete.value.attendances_count ?? 0) > 0
    );
});

const confirmDeleteStudent = () => {
    if (!studentToDelete.value) return;

    deleteForm.delete(destroyStudent(studentToDelete.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            studentToDelete.value = null;
            toast.success('Student deleted successfully.');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.error(errors.error);
            } else {
                toast.error('Failed to delete student.');
            }
        },
    });
};
</script>

<template>
    <Head title="Students & Enrollment" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8 print:p-0 print:space-y-3">
        <!-- Institutional Letterhead Print Header -->
        <PrintHeader
            title="Student Admission & Directory Roster"
            subtitle="Official Institutional Registry of Admitted Students"
            class-section="Students Directory"
        />

        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Students Enrollment
                </h1>
                <p class="text-sm text-muted-foreground">
                    Admit and enroll students, admission registers.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <Button
                    variant="outline"
                    class="h-10 gap-2 text-sm"
                    as-child
                >
                    <a :href="exportCsvUrl">
                        <Download class="h-4 w-4" />
                        <span>Export CSV</span>
                    </a>
                </Button>

                <Button
                    variant="outline"
                    class="h-10 gap-2 text-sm"
                    as-child
                >
                    <Link :href="peopleAlumniIndex().url">
                        <Award class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                        <span>Alumni Directory</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    class="h-10 gap-2 text-sm"
                    @click="printRoster"
                >
                    <Printer class="h-4 w-4" />
                    <span>Print Roster</span>
                </Button>

                <Button
                    v-if="canCreateStudent"
                    class="h-10 w-full sm:w-auto gap-2 shadow-sm"
                    @click="openCreateStudentModal"
                >
                    <Plus class="h-4 w-4" />
                    <span>Admit & Enroll Student</span>
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
                        {{ stats.total_students }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        All student admission records
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Active in Current Session
                    </CardTitle>
                    <GraduationCap class="h-4 w-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ stats.enrolled_students }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Assigned to class & section
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Male Students
                    </CardTitle>
                    <UserCheck class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                        {{ stats.male_students }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Boys enrolled
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Female Students
                    </CardTitle>
                    <HeartHandshake class="h-4 w-4 text-purple-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-purple-600 dark:text-purple-400">
                        {{ stats.female_students }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Girls enrolled
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter & Search Controls -->
        <Card class="border-border/60 shadow-xs print:hidden">
            <CardContent class="p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search by student name, admission number, guardian, phone..."
                            class="pl-9 h-10 w-full"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Academic Session Filter -->
                        <select
                            v-model="selectedSessionFilter"
                            class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="all">All Sessions</option>
                            <option
                                v-for="s in sessions"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.name }} Session {{ s.is_current ? '(Current)' : '' }}
                            </option>
                        </select>

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
                            :disabled="availableSectionsForFilter.length === 0"
                        >
                            <option value="all">All Sections</option>
                            <option v-for="sec in availableSectionsForFilter" :key="sec.id" :value="sec.id">
                                {{ sec.name }}
                            </option>
                        </select>

                        <!-- Gender Filter -->
                        <select
                            v-model="selectedGenderFilter"
                            class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="all">All Genders</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>

                        <!-- Enrollment Filter -->
                        <select
                            v-model="selectedEnrollmentFilter"
                            class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="all">All Statuses</option>
                            <option value="enrolled">Active Enrolled</option>
                            <option value="graduated">Alumni / Graduated</option>
                            <option value="unenrolled">Unallocated</option>
                        </select>

                        <!-- Reset Filters -->
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="h-10 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                            @click="resetFilters"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                            <span>Reset</span>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Students Catalog Table -->
        <Card class="border-border/60 shadow-xs print:hidden">
            <CardHeader class="px-6 py-4 border-b border-border/40">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-semibold">Registered Students</CardTitle>
                        <CardDescription>
                            Showing {{ paginationStart }} - {{ paginationEnd }} of {{ filteredStudents.length }} records
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
                            <th class="px-6 py-3.5">Gender & Origin</th>
                            <th class="px-6 py-3.5">Guardian</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr
                            v-for="student in paginatedStudents"
                            :key="student.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <Link
                                    :href="showStudent(student.id).url"
                                    class="font-mono font-semibold text-xs text-primary hover:underline inline-block"
                                    title="View Student Profile"
                                >
                                    {{ student.admission_number }}
                                </Link>
                            </td>

                            <td class="px-6 py-4">
                                <Link
                                    :href="showStudent(student.id).url"
                                    class="font-medium text-foreground hover:text-primary hover:underline transition-colors block"
                                    title="View Student Profile"
                                >
                                    {{ student.user.name }}
                                </Link>
                                <div v-if="student.user.email" class="text-xs text-muted-foreground">
                                    {{ student.user.email }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div v-if="student.current_enrollment?.school_class" class="flex flex-col gap-0.5">
                                    <span class="font-medium text-foreground">
                                        {{ student.current_enrollment.school_class.name }}
                                    </span>
                                    <div class="flex items-center gap-1.5">
                                        <Badge variant="secondary" class="text-[10px] font-semibold">
                                            Section {{ student.current_enrollment.section?.name ?? 'General' }}
                                        </Badge>
                                    </div>
                                </div>
                                <Badge v-else variant="outline" class="text-muted-foreground border-dashed text-xs">
                                    Unallocated
                                </Badge>
                            </td>

                            <td class="px-6 py-4">
                                <div class="space-y-0.5">
                                    <Badge
                                        variant="outline"
                                        class="capitalize text-xs font-normal"
                                        :class="{
                                            'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200': student.user.gender === 'male',
                                            'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-200': student.user.gender === 'female',
                                        }"
                                    >
                                        {{ student.user.gender }}
                                    </Badge>
                                    <div v-if="student.state_of_origin" class="text-[11px] text-muted-foreground truncate">
                                        {{ student.state_of_origin }}<template v-if="student.lga"> ({{ student.lga }})</template>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div v-if="student.guardian" class="space-y-0.5">
                                    <div class="text-xs font-medium text-foreground">
                                        {{ student.guardian.name }}
                                    </div>
                                    <div class="text-[11px] text-muted-foreground flex items-center gap-1">
                                        <span>{{ student.guardian.relationship }}</span>
                                        <span>•</span>
                                        <span>{{ student.guardian.phone }}</span>
                                    </div>
                                </div>
                                <span v-else class="text-xs text-muted-foreground italic">
                                    None linked
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <Badge
                                    v-if="student.current_enrollment?.status === 'graduated'"
                                    variant="outline"
                                    class="bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-200 text-xs gap-1 font-semibold"
                                >
                                    <GraduationCap class="h-3 w-3" />
                                    <span>Alumni</span>
                                </Badge>
                                <Badge
                                    v-else
                                    :variant="student.user.is_active ? 'secondary' : 'outline'"
                                    :class="student.user.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200' : 'text-muted-foreground'"
                                    class="text-xs gap-1"
                                >
                                    <CheckCircle2 v-if="student.user.is_active" class="h-3 w-3" />
                                    <XCircle v-else class="h-3 w-3" />
                                    <span>{{ student.user.is_active ? 'Active' : 'Inactive' }}</span>
                                </Badge>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="showStudent(student.id).url"
                                        class="inline-flex items-center justify-center h-8 w-8 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted/50 transition-colors"
                                        title="View Student Record"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Link>

                                    <Button
                                        v-if="canUpdateStudent"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                        title="Edit Student"
                                        @click="openEditStudentModal(student)"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </Button>

                                    <Button
                                        v-if="canDeleteStudent"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                        title="Delete Student"
                                        @click="openDeleteModal(student)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="paginatedStudents.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <Users class="h-8 w-8 text-muted-foreground/60" />
                                    <p class="font-medium">No students found matching your filters.</p>
                                    <p class="text-xs">Adjust your search query or click "Register Student" to admit one.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer (15 items per page) -->
            <div
                v-if="filteredStudents.length > 0"
                class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-border/40"
            >
                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-medium text-foreground">{{ paginationStart }}</span> to
                    <span class="font-medium text-foreground">{{ paginationEnd }}</span> of
                    <span class="font-medium text-foreground">{{ filteredStudents.length }}</span> entries
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
                        <th class="py-2 px-2 font-bold">Gender</th>
                        <th class="py-2 px-2 font-bold">Current Class & Section</th>
                        <th class="py-2 px-2 font-bold">Guardian Name & Phone</th>
                        <th class="py-2 px-2 font-bold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    <tr v-for="(student, index) in filteredStudents" :key="student.id" class="border-b border-gray-200">
                        <td class="py-1.5 px-1 text-center font-mono">{{ index + 1 }}</td>
                        <td class="py-1.5 px-2 font-mono font-medium">{{ student.admission_number }}</td>
                        <td class="py-1.5 px-2 font-medium">{{ student.user?.name ?? '—' }}</td>
                        <td class="py-1.5 px-2 capitalize">{{ student.user?.gender ?? '—' }}</td>
                        <td class="py-1.5 px-2">
                            {{ student.current_enrollment?.school_class?.name ?? 'Unassigned' }}
                            <span v-if="student.current_enrollment?.section"> ({{ student.current_enrollment.section.name }})</span>
                        </td>
                        <td class="py-1.5 px-2">
                            <span v-if="student.guardian?.name">{{ student.guardian.name }}</span>
                            <span v-if="student.guardian?.phone" class="font-mono text-[11px] text-gray-600"> ({{ student.guardian.phone }})</span>
                            <span v-if="!student.guardian?.name && !student.guardian?.phone">—</span>
                        </td>
                        <td class="py-1.5 px-2 text-center capitalize">{{ student.user?.is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                    <tr v-if="filteredStudents.length === 0">
                        <td colspan="7" class="py-6 text-center italic text-gray-500">
                            No students found matching the selected filters.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-8 pt-4 border-t border-gray-300 flex justify-between text-xs text-gray-600">
                <div>
                    <p class="font-medium">Total Students Listed: {{ filteredStudents.length }}</p>
                </div>
                <div class="text-right">
                    <p class="border-t border-black pt-1 w-48 text-center ml-auto font-medium">Registrar / Principal Signature</p>
                </div>
            </div>
        </div>

        <!-- Student Form Dialog (Create / Edit) -->
        <Dialog v-model:open="isStudentModalOpen">
            <DialogScrollContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingStudent ? 'Edit Student Profile' : 'Admit & Register New Student' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            editingStudent
                                ? 'Update bio-data, guardian linkage, and student account details.'
                                : 'Enter bio-data and admission details. You may also assign their initial class and section.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitStudentForm" class="space-y-4 py-2">
                    <!-- Section 1: Bio-Data -->
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <IdCard class="h-4 w-4 text-primary" />
                            <span>1. Student Identity & Bio-Data</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <Label for="first_name">First Name <span class="text-destructive">*</span></Label>
                                <Input
                                    id="first_name"
                                    v-model="studentForm.first_name"
                                    placeholder="e.g. MARYAM"
                                    class="uppercase"
                                    required
                                />
                                <InputError :message="studentForm.errors.first_name" />
                            </div>

                            <div class="space-y-1">
                                <Label for="last_name">Last Name <span class="text-destructive">*</span></Label>
                                <Input
                                    id="last_name"
                                    v-model="studentForm.last_name"
                                    placeholder="e.g. ALIYU"
                                    class="uppercase"
                                    required
                                />
                                <InputError :message="studentForm.errors.last_name" />
                            </div>

                            <div class="space-y-1">
                                <Label for="gender">Gender <span class="text-destructive">*</span></Label>
                                <select
                                    id="gender"
                                    v-model="studentForm.gender"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                    required
                                >
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                                <InputError :message="studentForm.errors.gender" />
                            </div>

                            <div class="space-y-1">
                                <Label for="date_of_birth">Date of Birth</Label>
                                <Input
                                    id="date_of_birth"
                                    v-model="studentForm.date_of_birth"
                                    type="date"
                                />
                                <InputError :message="studentForm.errors.date_of_birth" />
                            </div>

                            <div class="space-y-1">
                                <Label for="state_of_origin">State of Origin</Label>
                                <Input
                                    id="state_of_origin"
                                    v-model="studentForm.state_of_origin"
                                    placeholder="e.g. Zamfara, Kano, Sokoto"
                                />
                                <InputError :message="studentForm.errors.state_of_origin" />
                            </div>

                            <div class="space-y-1">
                                <Label for="lga">L.G.A.</Label>
                                <Input
                                    id="lga"
                                    v-model="studentForm.lga"
                                    placeholder="e.g. Gusau, Bungudu, Maru"
                                />
                                <InputError :message="studentForm.errors.lga" />
                            </div>

                            <div class="space-y-1 sm:col-span-2">
                                <Label for="phone">Student Phone (Optional)</Label>
                                <Input
                                    id="phone"
                                    v-model="studentForm.phone"
                                    placeholder="e.g. 08012345678"
                                />
                                <InputError :message="studentForm.errors.phone" />
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Admission & Academic Enrollment -->
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <GraduationCap class="h-4 w-4 text-emerald-500" />
                                <span>2. Admission & Academic Enrollment</span>
                            </div>
                            <span v-if="activeSession" class="text-[11px] text-primary font-medium">
                                Active Session: {{ activeSession.name }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <Label for="admission_number">Admission Number <span class="text-destructive">*</span></Label>
                                <Input
                                    id="admission_number"
                                    v-model="studentForm.admission_number"
                                    placeholder="e.g. YUM-SA/00001"
                                    disabled
                                    readonly
                                    class="bg-muted/70 text-muted-foreground cursor-not-allowed font-mono select-none"
                                />
                                <InputError :message="studentForm.errors.admission_number" />
                            </div>

                            <div class="space-y-1">
                                <Label for="admission_date">Admission Date <span class="text-destructive">*</span></Label>
                                <Input
                                    id="admission_date"
                                    v-model="studentForm.admission_date"
                                    type="date"
                                    required
                                />
                                <InputError :message="studentForm.errors.admission_date" />
                            </div>

                            <div class="space-y-1">
                                <Label for="class_id">Class <span class="text-destructive">*</span></Label>
                                <select
                                    id="class_id"
                                    v-model="studentForm.class_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                    required
                                >
                                    <option value="">Select Class...</option>
                                    <option v-for="c in classes" :key="c.id" :value="c.id">
                                        {{ c.name }}
                                    </option>
                                </select>
                                <InputError :message="studentForm.errors.class_id" />
                            </div>

                            <div class="space-y-1">
                                <Label for="section_id">Section <span class="text-destructive">*</span></Label>
                                <select
                                    id="section_id"
                                    v-model="studentForm.section_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                    :disabled="!studentForm.class_id"
                                    required
                                >
                                    <option value="">Select Section...</option>
                                    <option
                                        v-for="s in availableSectionsForSelectedClass"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        Section {{ s.name }} (Cap: {{ s.capacity ?? '∞' }})
                                    </option>
                                </select>
                                <InputError :message="studentForm.errors.section_id" />
                            </div>

                            <div class="space-y-1 sm:col-span-2">
                                <Label for="guardian_id">Parent / Legal Guardian</Label>
                                <select
                                    id="guardian_id"
                                    v-model="studentForm.guardian_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="">No Guardian Assigned</option>
                                    <option v-for="g in guardians" :key="g.id" :value="g.id">
                                        {{ g.name }} ({{ g.relationship }} - {{ g.phone }})
                                    </option>
                                </select>
                                <InputError :message="studentForm.errors.guardian_id" />
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Student Portal Login Credentials (Create Mode - Fully Automated) -->
                    <div v-if="!editingStudent" class="rounded-lg border border-purple-200/60 bg-purple-500/10 p-4 space-y-2.5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between items-start gap-2">
                            <div class="text-xs font-semibold uppercase tracking-wider text-purple-900 dark:text-purple-300 flex items-center gap-1.5">
                                <ShieldCheck class="h-4 w-4 text-purple-600 dark:text-purple-400 shrink-0" />
                                <span>3. Student Portal Login Credentials</span>
                            </div>
                            <Badge variant="secondary" class="text-[10px] bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-200 flex items-center gap-1 font-medium whitespace-nowrap">
                                <Sparkles class="h-3 w-3 shrink-0" />
                                <span>Automatic Credentials Generation</span>
                            </Badge>
                        </div>

                        <div class="text-xs text-purple-900/90 dark:text-purple-200/90 leading-relaxed flex items-start gap-2.5 pt-1">
                            <KeyRound class="h-4 w-4 shrink-0 mt-0.5 text-purple-600 dark:text-purple-400" />
                            <div>
                                Student portal login email and username are automatically provisioned from the admission number
                                (<span class="font-mono font-semibold">{{ formatAdmissionForLogin(studentForm.admission_number) || 'admission' }}@sms.com</span>)
                                with default initial password
                                <span class="font-mono font-semibold px-1 py-0.5 rounded bg-purple-200/70 dark:bg-purple-900/60 text-purple-950 dark:text-purple-100">12345678</span>.
                                No manual credential entry is required.
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Account & Status (Edit Mode) -->
                    <div v-else class="rounded-lg border border-border/60 bg-muted/20 p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <ShieldCheck class="h-4 w-4 text-purple-500" />
                                <span>3. Account & Status</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <Label for="email">Student Portal Email</Label>
                                <Input
                                    id="email"
                                    v-model="studentForm.email"
                                    type="email"
                                    placeholder="e.g. gsss00001@sms.com"
                                />
                                <InputError :message="studentForm.errors.email" />
                            </div>

                            <div class="space-y-1">
                                <Label for="username">Username</Label>
                                <Input
                                    id="username"
                                    v-model="studentForm.username"
                                    placeholder="Current username"
                                />
                                <InputError :message="studentForm.errors.username" />
                            </div>

                            <div class="space-y-1 sm:col-span-2">
                                <div class="flex items-center justify-between">
                                    <Label for="password">Reset Password (Optional)</Label>
                                    <button
                                        type="button"
                                        class="text-[11px] text-primary hover:underline font-medium flex items-center gap-1"
                                        @click="setPasswordToDefault"
                                    >
                                        <RefreshCw class="h-3 w-3" />
                                        <span>Reset to 12345678</span>
                                    </button>
                                </div>
                                <Input
                                    id="password"
                                    v-model="studentForm.password"
                                    type="text"
                                    placeholder="Leave blank to retain current password"
                                />
                                <p class="text-[11px] text-muted-foreground">
                                    Leave blank to retain current. Students can also reset their password in portal settings.
                                </p>
                                <InputError :message="studentForm.errors.password" />
                            </div>

                            <div class="flex items-center space-x-2 pt-2 sm:col-span-2">
                                <Checkbox
                                    id="is_active"
                                    :checked="studentForm.is_active"
                                    @update:checked="(val: boolean) => (studentForm.is_active = val)"
                                />
                                <Label for="is_active" class="text-sm font-medium leading-none cursor-pointer">
                                    Student Active Status (Permits portal login & assessment entry)
                                </Label>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isStudentModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="studentForm.processing"
                        >
                            <Spinner v-if="studentForm.processing" class="mr-2 h-4 w-4" />
                            <span>{{ editingStudent ? 'Save Changes' : 'Register Student' }}</span>
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
                        <span>Delete Student Record</span>
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to permanently remove
                        <span class="font-semibold text-foreground">{{ studentToDelete?.user.name }}</span>
                        ({{ studentToDelete?.admission_number }})?
                    </DialogDescription>
                </DialogHeader>

                <div v-if="hasProtectedRecords" class="rounded-md bg-destructive/10 p-3 text-xs text-destructive flex items-start gap-2">
                    <AlertTriangle class="h-4 w-4 shrink-0 mt-0.5" />
                    <div>
                        <strong>Academic Records Protected:</strong> This student has continuous assessment scores
                        ({{ studentToDelete?.student_marks_count }}) or attendance registers ({{ studentToDelete?.attendances_count }}) linked.
                        Student records with examination data cannot be deleted to maintain institutional audit integrity.
                    </div>
                </div>

                <div v-else class="text-xs text-muted-foreground">
                    This will permanently delete the student profile, session enrollments, and portal user account. This action cannot be reversed.
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
                        @click="confirmDeleteStudent"
                    >
                        <Spinner v-if="deleteForm.processing" class="mr-2 h-4 w-4" />
                        <span>Confirm Delete</span>
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
