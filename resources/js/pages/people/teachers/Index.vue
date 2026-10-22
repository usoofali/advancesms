<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Briefcase,
    Calendar,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Copy,
    Download,
    Edit2,
    Eye,
    EyeOff,
    GraduationCap,
    KeyRound,
    Library,
    Mail,
    Phone,
    Plus,
    Printer,
    RefreshCw,
    Search,
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
import PrintHeader from '@/components/PrintHeader.vue';
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
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy as destroyTeacher, index as peopleTeachersIndex, resetPassword as resetTeacherPassword, store as storeTeacher, update as updateTeacher } from '@/routes/people/teachers';
import type { Department } from '@/types/academic';
import type { RoleSummary, Teacher, TeacherStats } from '@/types/people';

interface Props {
    teachers: Teacher[];
    departments: Department[];
    roles?: RoleSummary[];
    stats: TeacherStats;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Teachers & Staff',
                href: peopleTeachersIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateTeacher = computed(() => isSuperAdmin.value || userPermissions.value.includes('teachers.create'));
const canUpdateTeacher = computed(() => isSuperAdmin.value || userPermissions.value.includes('teachers.update'));
const canDeleteTeacher = computed(() => isSuperAdmin.value || userPermissions.value.includes('teachers.delete'));

const printDirectory = () => {
    window.print();
};

const exportCsvUrl = computed(() => {
    return peopleTeachersIndex({ query: { export: 'csv' } }).url;
});

// Helper to determine primary role name and display
const getPrimaryRoleName = (teacher: Teacher): string => {
    return teacher.user.roles?.[0]?.name || (typeof teacher.user.role === 'string' ? teacher.user.role : 'teacher');
};

const getPrimaryRoleDisplayName = (teacher: Teacher): string => {
    const roleName = getPrimaryRoleName(teacher);
    const foundInUser = teacher.user.roles?.[0]?.display_name;
    if (foundInUser) return foundInUser;
    const foundInProps = props.roles?.find((r) => r.name === roleName);
    if (foundInProps?.display_name) return foundInProps.display_name;
    return roleName.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const getRoleBadgeClass = (roleName: string): string => {
    switch (roleName) {
        case 'teacher':
            return 'bg-blue-500/15 text-blue-700 dark:text-blue-400 border-blue-500/30';
        case 'exam_officer':
            return 'bg-purple-500/15 text-purple-700 dark:text-purple-400 border-purple-500/30';
        case 'admin':
        case 'superadmin':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30';
        default:
            return 'bg-muted text-foreground border-border';
    }
};

// =========================================================================
// SEARCH & FILTERS
// =========================================================================
const searchQuery = ref('');
const selectedRoleFilter = ref<string>('all');
const selectedDepartmentFilter = ref<string>('all');
const selectedStatusFilter = ref<string>('all');

const filteredTeachers = computed(() => {
    return props.teachers.filter((teacher) => {
        const query = searchQuery.value.toLowerCase().trim();
        const matchesSearch =
            !query ||
            teacher.user.name.toLowerCase().includes(query) ||
            teacher.staff_id.toLowerCase().includes(query) ||
            (teacher.user.email && teacher.user.email.toLowerCase().includes(query)) ||
            (teacher.user.phone && teacher.user.phone.toLowerCase().includes(query)) ||
            (teacher.designation && teacher.designation.toLowerCase().includes(query));

        const teacherRole = getPrimaryRoleName(teacher);
        const matchesRole =
            selectedRoleFilter.value === 'all' ||
            teacherRole === selectedRoleFilter.value ||
            teacher.user.roles?.some((r) => r.name === selectedRoleFilter.value);

        const matchesDept =
            selectedDepartmentFilter.value === 'all' ||
            (selectedDepartmentFilter.value === 'none' && !teacher.department_id) ||
            teacher.department_id === Number(selectedDepartmentFilter.value);

        const matchesStatus =
            selectedStatusFilter.value === 'all' ||
            (selectedStatusFilter.value === 'active' && teacher.user.is_active) ||
            (selectedStatusFilter.value === 'inactive' && !teacher.user.is_active);

        return matchesSearch && matchesRole && matchesDept && matchesStatus;
    });
});

// =========================================================================
// PAGINATION (15 items per page)
// =========================================================================
const currentPage = ref(1);
const itemsPerPage = ref(15);

watch([searchQuery, selectedRoleFilter, selectedDepartmentFilter, selectedStatusFilter], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => {
    return Math.ceil(filteredTeachers.value.length / itemsPerPage.value) || 1;
});

const paginatedTeachers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredTeachers.value.slice(start, start + itemsPerPage.value);
});

const paginationStart = computed(() => {
    if (filteredTeachers.value.length === 0) return 0;
    return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const paginationEnd = computed(() => {
    return Math.min(currentPage.value * itemsPerPage.value, filteredTeachers.value.length);
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
// TEACHER CREATE & EDIT MODAL
// =========================================================================
const isTeacherModalOpen = ref(false);
const editingTeacher = ref<Teacher | null>(null);

// Helper to generate staff ID based on role (TCH for teacher, STF for other staff)
const generateStaffId = (role: string): string => {
    const prefix = role === 'teacher' ? 'TCH' : 'STF';
    const year = new Date().getFullYear();
    const count = props.teachers.filter((t) => t.staff_id?.startsWith(prefix)).length;
    return `${prefix}/${year}/${String(count + 1).padStart(3, '0')}`;
};

const teacherForm = useForm({
    first_name: '',
    last_name: '',
    role: 'teacher',
    staff_id: '',
    gender: 'male' as 'male' | 'female',
    department_id: '' as string | number,
    designation: 'Subject Teacher',
    qualification: '',
    email: '',
    phone: '',
    joining_date: new Date().toISOString().substring(0, 10),
    is_active: true,
    password: '',
});

const onRoleChange = () => {
    if (!editingTeacher.value) {
        teacherForm.staff_id = generateStaffId(teacherForm.role);
        if (teacherForm.role === 'teacher' && (teacherForm.designation === 'Administrative Staff' || !teacherForm.designation)) {
            teacherForm.designation = 'Subject Teacher';
        } else if (teacherForm.role !== 'teacher' && teacherForm.designation === 'Subject Teacher') {
            const foundRole = props.roles?.find((r) => r.name === teacherForm.role);
            teacherForm.designation = foundRole?.display_name || 'Administrative Staff';
        }
    }
};

const openCreateTeacherModal = () => {
    editingTeacher.value = null;
    teacherForm.reset();
    teacherForm.clearErrors();
    teacherForm.role = 'teacher';
    teacherForm.first_name = '';
    teacherForm.last_name = '';
    teacherForm.staff_id = generateStaffId('teacher');
    teacherForm.gender = 'male';
    teacherForm.department_id = '';
    teacherForm.designation = 'Subject Teacher';
    teacherForm.qualification = '';
    teacherForm.email = '';
    teacherForm.phone = '';
    teacherForm.joining_date = new Date().toISOString().substring(0, 10);
    teacherForm.is_active = true;
    teacherForm.password = '';
    isTeacherModalOpen.value = true;
};

const openEditTeacherModal = (teacher: Teacher) => {
    editingTeacher.value = teacher;
    teacherForm.clearErrors();
    teacherForm.role = getPrimaryRoleName(teacher);

    // Ensure first_name and last_name are prefilled from user record or split from full name
    let firstName = teacher.user.first_name ?? '';
    let lastName = teacher.user.last_name ?? '';
    if ((!firstName || !lastName) && teacher.user.name) {
        const parts = teacher.user.name.trim().split(/\s+/);
        if (!firstName) firstName = parts[0] || '';
        if (!lastName) lastName = parts.slice(1).join(' ') || parts[0] || '';
    }

    teacherForm.first_name = firstName;
    teacherForm.last_name = lastName;
    teacherForm.staff_id = teacher.staff_id;
    teacherForm.gender = (teacher.user.gender as 'male' | 'female') ?? 'male';
    teacherForm.department_id = teacher.department_id ?? '';
    teacherForm.designation = teacher.designation ?? 'Subject Teacher';
    teacherForm.qualification = teacher.qualification ?? '';
    teacherForm.email = teacher.user.email ?? '';
    teacherForm.phone = teacher.user.phone ?? '';
    teacherForm.joining_date = teacher.joining_date ? teacher.joining_date.substring(0, 10) : '';
    teacherForm.is_active = Boolean(teacher.user.is_active);
    teacherForm.password = '';
    isTeacherModalOpen.value = true;
};

const submitTeacherForm = () => {
    if (editingTeacher.value) {
        teacherForm.put(updateTeacher.url({ teacher: editingTeacher.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                isTeacherModalOpen.value = false;
                toast.success('Staff profile updated successfully.');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    } else {
        teacherForm.post(storeTeacher.url(), {
            preserveScroll: true,
            onSuccess: () => {
                isTeacherModalOpen.value = false;
                toast.success('Staff member registered successfully.');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    }
};

// =========================================================================
// RESET PASSWORD MODAL
// =========================================================================
const isResetPasswordModalOpen = ref(false);
const resettingTeacher = ref<Teacher | null>(null);
const showResetPassword = ref(false);

const resetPasswordForm = useForm({
    password: '12345678',
});

const openResetPasswordModal = (teacher: Teacher) => {
    resettingTeacher.value = teacher;
    resetPasswordForm.reset();
    resetPasswordForm.clearErrors();
    resetPasswordForm.password = '12345678';
    showResetPassword.value = false;
    isResetPasswordModalOpen.value = true;
};

const generateRandomPassword = () => {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    resetPasswordForm.password = pass;
    showResetPassword.value = true;
};

const setPasswordDefault = () => {
    resetPasswordForm.password = '12345678';
};

const copyResetPassword = () => {
    if (!resetPasswordForm.password) return;
    navigator.clipboard.writeText(resetPasswordForm.password);
    toast.success('Password copied to clipboard.');
};

const submitResetPassword = () => {
    if (!resettingTeacher.value) return;

    resetPasswordForm.put(resetTeacherPassword.url({ teacher: resettingTeacher.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isResetPasswordModalOpen.value = false;
            toast.success(`Password for ${resettingTeacher.value?.user.name} (${resettingTeacher.value?.staff_id}) reset successfully.`);
            resettingTeacher.value = null;
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            if (firstError) toast.error(firstError);
        },
    });
};

// =========================================================================
// TEACHER DELETE MODAL
// =========================================================================
const isDeleteTeacherModalOpen = ref(false);
const teacherToDelete = ref<Teacher | null>(null);
const deleteForm = useForm({});

const openDeleteTeacherModal = (teacher: Teacher) => {
    teacherToDelete.value = teacher;
    isDeleteTeacherModalOpen.value = true;
};

const confirmDeleteTeacher = () => {
    if (!teacherToDelete.value) return;

    deleteForm.delete(destroyTeacher.url({ teacher: teacherToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteTeacherModalOpen.value = false;
            toast.success(`Staff member '${teacherToDelete.value?.user.name}' deleted.`);
            teacherToDelete.value = null;
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            if (firstError) toast.error(firstError);
        },
    });
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full print:p-0 print:space-y-3">
        <Head title="Teachers & Staff Directory" />

        <!-- Institutional Letterhead Print Header -->
        <PrintHeader
            title="Teachers & Staff Directory"
            subtitle="Official Institutional Personnel & Faculty Roster"
            class-section="Academic & Administrative Staff"
        />

        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Teachers & Staff Directory
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Manage teaching and administrative personnel, staff IDs, departmental allocations, and portal accounts.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <Button
                    variant="outline"
                    class="h-10 gap-2 text-sm"
                    as-child
                >
                    <a :href="exportCsvUrl">
                        <Download class="size-4" />
                        <span>Export CSV</span>
                    </a>
                </Button>

                <Button
                    variant="outline"
                    class="h-10 gap-2 text-sm"
                    @click="printDirectory"
                >
                    <Printer class="size-4" />
                    <span>Print Directory</span>
                </Button>

                <Button
                    v-if="canCreateTeacher"
                    @click="openCreateTeacherModal"
                    class="w-full sm:w-auto gap-2 shadow-sm"
                >
                    <Plus class="size-4" />
                    <span>Add Staff Member</span>
                </Button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4 print:hidden">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Total Staff</CardDescription>
                    <CardTitle class="text-2xl font-bold text-foreground">
                        {{ stats.total_staff ?? stats.total_teachers }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">Registered personnel</p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Teaching Staff</CardDescription>
                    <CardTitle class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ stats.total_teachers }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">Academic instructors (TCH)</p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Other Roles</CardDescription>
                    <CardTitle class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ stats.total_other_staff ?? 0 }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">Admin & other staff (STF)</p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Active Personnel</CardDescription>
                    <CardTitle class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ stats.active_staff }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">Active portal accounts</p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-card border border-border/60 p-3 rounded-lg shadow-xs print:hidden">
            <div class="relative flex-1 max-w-sm">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Search staff by name, ID, phone..."
                    class="pl-9 h-9"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Role Filter -->
                <select
                    v-model="selectedRoleFilter"
                    class="h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="all">All Staff Roles</option>
                    <option v-for="r in roles" :key="r.id" :value="r.name">
                        {{ r.display_name }}
                    </option>
                </select>

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

                <!-- Status Filter -->
                <select
                    v-model="selectedStatusFilter"
                    class="h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="all">All Status</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>
        </div>

        <!-- Teachers & Staff Table -->
        <Card class="border-border/60 shadow-xs overflow-hidden print:hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/40 border-b border-border text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Staff ID</th>
                            <th class="px-4 py-3 font-semibold">Staff Name</th>
                            <th class="px-4 py-3 font-semibold">Role</th>
                            <th class="px-4 py-3 font-semibold">Department</th>
                            <th class="px-4 py-3 font-semibold">Designation</th>
                            <th class="px-4 py-3 font-semibold">Contact Info</th>
                            <th class="px-4 py-3 font-semibold text-center">Status</th>
                            <th class="px-4 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="teacher in paginatedTeachers"
                            :key="teacher.id"
                            class="hover:bg-muted/20 transition-colors"
                        >
                            <!-- Staff ID -->
                            <td class="px-4 py-3 font-mono font-bold text-foreground">
                                <span class="inline-block px-2 py-0.5 rounded bg-muted text-xs border border-border">
                                    {{ teacher.staff_id }}
                                </span>
                            </td>

                            <!-- Name & Gender -->
                            <td class="px-4 py-3">
                                <div>
                                    <span class="font-semibold text-foreground">{{ teacher.user.name }}</span>
                                    <span class="ml-2 text-xs text-muted-foreground capitalize">({{ teacher.user.gender }})</span>
                                </div>
                                <div v-if="teacher.qualification" class="text-xs text-muted-foreground">
                                    {{ teacher.qualification }}
                                </div>
                            </td>

                            <!-- Role -->
                            <td class="px-4 py-3">
                                <Badge
                                    variant="outline"
                                    :class="getRoleBadgeClass(getPrimaryRoleName(teacher))"
                                >
                                    {{ getPrimaryRoleDisplayName(teacher) }}
                                </Badge>
                            </td>

                            <!-- Department -->
                            <td class="px-4 py-3">
                                <span v-if="teacher.department" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                                    <Library class="size-3.5 text-primary/70" />
                                    {{ teacher.department.name }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground/60 italic">
                                    General / Unassigned
                                </span>
                            </td>

                            <!-- Designation -->
                            <td class="px-4 py-3">
                                <Badge variant="secondary" class="text-xs font-normal">
                                    {{ teacher.designation || 'Staff' }}
                                </Badge>
                            </td>

                            <!-- Contact Info -->
                            <td class="px-4 py-3 text-xs text-muted-foreground space-y-0.5">
                                <div v-if="teacher.user.phone" class="flex items-center gap-1.5">
                                    <Phone class="size-3 text-muted-foreground/80" />
                                    <span>{{ teacher.user.phone }}</span>
                                </div>
                                <div v-if="teacher.user.email" class="flex items-center gap-1.5">
                                    <Mail class="size-3 text-muted-foreground/80" />
                                    <span>{{ teacher.user.email }}</span>
                                </div>
                                <div v-if="!teacher.user.phone && !teacher.user.email" class="italic text-muted-foreground/60">
                                    No contact recorded
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3 text-center">
                                <Badge
                                    v-if="teacher.user.is_active"
                                    variant="outline"
                                    class="text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800 text-xs font-normal"
                                >
                                    Active
                                </Badge>
                                <Badge
                                    v-else
                                    variant="secondary"
                                    class="bg-muted text-muted-foreground text-xs font-normal"
                                >
                                    Inactive
                                </Badge>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Button
                                        v-if="canUpdateTeacher"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-muted-foreground hover:text-foreground"
                                        @click="openEditTeacherModal(teacher)"
                                        title="Edit Profile"
                                    >
                                        <Edit2 class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canUpdateTeacher"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-muted-foreground hover:text-amber-600 dark:hover:text-amber-400"
                                        @click="openResetPasswordModal(teacher)"
                                        title="Reset Password Directly"
                                    >
                                        <KeyRound class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canDeleteTeacher"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-muted-foreground hover:text-destructive"
                                        @click="openDeleteTeacherModal(teacher)"
                                        title="Delete Staff Member"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredTeachers.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">
                                No staff members found matching the selected filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer (15 items per page) -->
            <div v-if="filteredTeachers.length > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3.5 border-t border-border bg-card">
                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-medium text-foreground">{{ paginationStart }}</span> to
                    <span class="font-medium text-foreground">{{ paginationEnd }}</span> of
                    <span class="font-medium text-foreground">{{ filteredTeachers.length }}</span> staff members
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

        <!-- Printable Roster Table (Hidden on Screen, Shown in Print) -->
        <div class="hidden print:block w-full mt-2">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b-2 border-black">
                        <th class="py-2 px-1 text-center font-bold w-10">#</th>
                        <th class="py-2 px-2 font-bold">Staff ID</th>
                        <th class="py-2 px-2 font-bold">Full Name</th>
                        <th class="py-2 px-2 font-bold">Role</th>
                        <th class="py-2 px-2 font-bold">Department</th>
                        <th class="py-2 px-2 font-bold">Designation</th>
                        <th class="py-2 px-2 font-bold">Phone</th>
                        <th class="py-2 px-2 font-bold">Email</th>
                        <th class="py-2 px-2 font-bold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    <tr v-for="(teacher, index) in filteredTeachers" :key="teacher.id" class="border-b border-gray-200">
                        <td class="py-1.5 px-1 text-center font-mono">{{ index + 1 }}</td>
                        <td class="py-1.5 px-2 font-mono font-medium">{{ teacher.staff_id }}</td>
                        <td class="py-1.5 px-2 font-medium">{{ teacher.user?.name ?? '—' }}</td>
                        <td class="py-1.5 px-2">{{ getPrimaryRoleDisplayName(teacher) }}</td>
                        <td class="py-1.5 px-2">{{ teacher.department?.name ?? 'None' }}</td>
                        <td class="py-1.5 px-2">{{ teacher.designation ?? '—' }}</td>
                        <td class="py-1.5 px-2 font-mono">{{ teacher.user?.phone ?? '—' }}</td>
                        <td class="py-1.5 px-2 font-mono text-[11px]">{{ teacher.user?.email ?? '—' }}</td>
                        <td class="py-1.5 px-2 text-center capitalize">{{ teacher.user?.is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                    <tr v-if="filteredTeachers.length === 0">
                        <td colspan="9" class="py-6 text-center italic text-gray-500">
                            No staff personnel found matching the selected filters.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-8 pt-4 border-t border-gray-300 flex justify-between text-xs text-gray-600">
                <div>
                    <p class="font-medium">Total Personnel Listed: {{ filteredTeachers.length }}</p>
                </div>
                <div class="text-right">
                    <p class="border-t border-black pt-1 w-48 text-center ml-auto font-medium">HR / Principal Signature</p>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- CREATE / EDIT TEACHER MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isTeacherModalOpen" @update:open="(val) => isTeacherModalOpen = val">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ editingTeacher ? 'Edit Staff Profile' : 'Register New Staff Member' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingTeacher ? 'Update employment and portal profile details.' : 'Register teacher or staff personnel and auto-provision portal account.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitTeacherForm" class="space-y-4 py-2">
                    <!-- Staff Role & Staff ID Row -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="teacher-role">Staff Role *</Label>
                            <select
                                id="teacher-role"
                                v-model="teacherForm.role"
                                @change="onRoleChange"
                                class="w-full h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                required
                            >
                                <option v-for="r in roles" :key="r.id" :value="r.name">
                                    {{ r.display_name }} ({{ r.name }})
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">Prefix adapts automatically: TCH for Teacher, STF for Staff.</p>
                            <InputError :message="teacherForm.errors.role" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="teacher-staff-id">Staff ID / Code *</Label>
                            <Input
                                id="teacher-staff-id"
                                v-model="teacherForm.staff_id"
                                placeholder="e.g. TCH/2026/001 or STF/2026/001"
                                class="uppercase font-mono"
                                required
                            />
                            <p class="text-xs text-muted-foreground">Unique identifier (TCH/.. for teachers, STF/.. for staff).</p>
                            <InputError :message="teacherForm.errors.staff_id" />
                        </div>
                    </div>

                    <!-- Names Row -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="teacher-first-name">First Name *</Label>
                            <Input
                                id="teacher-first-name"
                                v-model="teacherForm.first_name"
                                placeholder="e.g. ALIYU, FATIMA, JOHN"
                                class="uppercase"
                                required
                            />
                            <InputError :message="teacherForm.errors.first_name" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="teacher-last-name">Last Name *</Label>
                            <Input
                                id="teacher-last-name"
                                v-model="teacherForm.last_name"
                                placeholder="e.g. MUSA, BELLO, ADEYEMI"
                                class="uppercase"
                                required
                            />
                            <InputError :message="teacherForm.errors.last_name" />
                        </div>
                    </div>

                    <!-- Gender & Department Row -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="teacher-gender">Gender *</Label>
                            <select
                                id="teacher-gender"
                                v-model="teacherForm.gender"
                                class="w-full h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                            <InputError :message="teacherForm.errors.gender" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="teacher-dept">Academic Department</Label>
                            <select
                                id="teacher-dept"
                                v-model="teacherForm.department_id"
                                class="w-full h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="">General / Unassigned</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    {{ dept.name }}
                                </option>
                            </select>
                            <InputError :message="teacherForm.errors.department_id" />
                        </div>
                    </div>

                    <!-- Designation & Qualification Row -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="teacher-designation">Designation / Title</Label>
                            <Input
                                id="teacher-designation"
                                v-model="teacherForm.designation"
                                placeholder="e.g. Subject Teacher, Examination Officer, H.O.D"
                            />
                            <InputError :message="teacherForm.errors.designation" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="teacher-qualification">Highest Qualification</Label>
                            <Input
                                id="teacher-qualification"
                                v-model="teacherForm.qualification"
                                placeholder="e.g. B.Sc Ed, NCE, B.A, M.Ed"
                            />
                            <InputError :message="teacherForm.errors.qualification" />
                        </div>
                    </div>

                    <!-- Joining Date & Phone Row -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="teacher-joining-date">Joining Date</Label>
                            <Input
                                id="teacher-joining-date"
                                type="date"
                                v-model="teacherForm.joining_date"
                            />
                            <InputError :message="teacherForm.errors.joining_date" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="teacher-phone">Phone Number</Label>
                            <Input
                                id="teacher-phone"
                                type="tel"
                                v-model="teacherForm.phone"
                                placeholder="e.g. 08012345678"
                            />
                            <InputError :message="teacherForm.errors.phone" />
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="space-y-1.5">
                        <Label for="teacher-email">Email Address</Label>
                        <Input
                            id="teacher-email"
                            type="email"
                            v-model="teacherForm.email"
                            placeholder="e.g. staff@school.edu"
                        />
                        <InputError :message="teacherForm.errors.email" />
                    </div>

                    <!-- Status Active & Password Row (when editing or creating) -->
                    <div v-if="editingTeacher" class="flex items-center gap-2.5 pt-2">
                        <Checkbox
                            id="teacher-is-active"
                            :checked="teacherForm.is_active"
                            @update:checked="(val: boolean) => teacherForm.is_active = Boolean(val)"
                        />
                        <label for="teacher-is-active" class="text-sm font-medium cursor-pointer">
                            Active Account (Can login and access portal)
                        </label>
                    </div>

                    <div v-if="editingTeacher" class="space-y-1.5 pt-2 border-t border-border">
                        <Label for="teacher-reset-password">Change Password (leave empty to keep current)</Label>
                        <Input
                            id="teacher-reset-password"
                            type="password"
                            v-model="teacherForm.password"
                            placeholder="Enter new password if updating..."
                        />
                        <InputError :message="teacherForm.errors.password" />
                    </div>

                    <p v-if="!editingTeacher" class="text-xs text-muted-foreground bg-muted/40 p-2.5 rounded border border-border">
                        A user account with the selected role will be automatically created with password <code class="font-mono text-primary font-bold">12345678</code>.
                    </p>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isTeacherModalOpen = false"
                            :disabled="teacherForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="teacherForm.processing"
                            class="gap-2"
                        >
                            <Spinner v-if="teacherForm.processing" class="size-4" />
                            <span>{{ editingTeacher ? 'Save Changes' : 'Register Staff' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ================================================================= -->
        <!-- RESET STAFF PASSWORD MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isResetPasswordModalOpen" @update:open="(val) => isResetPasswordModalOpen = val">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submitResetPassword">
                    <DialogHeader>
                        <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                            <KeyRound class="size-5" />
                            <DialogTitle>Reset Staff Password</DialogTitle>
                        </div>
                        <DialogDescription>
                            Directly set a new login password for
                            <span class="font-semibold text-foreground">{{ resettingTeacher?.user.name }}</span>
                            ({{ resettingTeacher?.staff_id }}).
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="p-3 rounded-lg bg-muted/60 border border-border text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Portal Username:</span>
                                <span class="font-mono font-semibold text-foreground">{{ resettingTeacher?.user.username || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Role:</span>
                                <span class="font-medium text-foreground capitalize">{{ resettingTeacher ? getPrimaryRoleDisplayName(resettingTeacher) : '—' }}</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="direct-reset-password">New Password *</Label>
                            <div class="relative">
                                <Input
                                    id="direct-reset-password"
                                    :type="showResetPassword ? 'text' : 'password'"
                                    v-model="resetPasswordForm.password"
                                    placeholder="Enter new password (min. 6 characters)..."
                                    class="pr-10 font-mono"
                                    required
                                    minlength="6"
                                />
                                <button
                                    type="button"
                                    @click="showResetPassword = !showResetPassword"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors p-1"
                                    tabindex="-1"
                                >
                                    <EyeOff v-if="showResetPassword" class="size-4" />
                                    <Eye v-else class="size-4" />
                                </button>
                            </div>
                            <InputError :message="resetPasswordForm.errors.password" />

                            <!-- Quick Action Shortcuts -->
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 text-xs px-2 text-muted-foreground hover:text-foreground"
                                    @click="setPasswordDefault"
                                >
                                    Default: 12345678
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 text-xs px-2 gap-1 text-muted-foreground hover:text-foreground"
                                    @click="generateRandomPassword"
                                >
                                    <RefreshCw class="size-3" />
                                    <span>Random</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-xs px-2 gap-1 text-muted-foreground hover:text-foreground"
                                    @click="copyResetPassword"
                                    :disabled="!resetPasswordForm.password"
                                >
                                    <Copy class="size-3" />
                                    <span>Copy</span>
                                </Button>
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            The staff member will be able to log in immediately with this new password.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isResetPasswordModalOpen = false"
                            :disabled="resetPasswordForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="resetPasswordForm.processing || !resetPasswordForm.password"
                            class="gap-2"
                        >
                            <Spinner v-if="resetPasswordForm.processing" class="size-4" />
                            <span>Reset Password</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ================================================================= -->
        <!-- DELETE TEACHER CONFIRMATION MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isDeleteTeacherModalOpen" @update:open="(val) => isDeleteTeacherModalOpen = val">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <div class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-5" />
                        <DialogTitle>Delete Staff Member</DialogTitle>
                    </div>
                    <DialogDescription>
                        Are you sure you want to permanently delete <span class="font-semibold text-foreground">{{ teacherToDelete?.user.name }} ({{ teacherToDelete?.staff_id }})</span>?
                    </DialogDescription>
                </DialogHeader>

                <div v-if="(teacherToDelete?.subject_assignments_count ?? 0) > 0" class="p-3 rounded-lg bg-destructive/10 border border-destructive/20 text-xs text-destructive flex items-start gap-2">
                    <AlertTriangle class="size-4 shrink-0 mt-0.5" />
                    <span>
                        This teacher is currently allocated to {{ teacherToDelete?.subject_assignments_count }} class subject(s). You must remove their teaching assignments before deleting their record.
                    </span>
                </div>

                <DialogFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteTeacherModalOpen = false"
                        :disabled="deleteForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing || (teacherToDelete?.subject_assignments_count ?? 0) > 0"
                        @click="confirmDeleteTeacher"
                        class="gap-2"
                    >
                        <Spinner v-if="deleteForm.processing" class="size-4" />
                        <span>Delete Staff Member</span>
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
