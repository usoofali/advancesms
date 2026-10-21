<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Award,
    BookOpen,
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    HeartHandshake,
    IdCard,
    KeyRound,
    Mail,
    MapPin,
    Phone,
    Printer,
    School,
    ShieldCheck,
    Sparkles,
    User,
    UserCheck,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as peopleAlumniIndex } from '@/routes/people/alumni';
import { index as peopleStudentsIndex, show as peopleStudentsShow } from '@/routes/people/students';

interface UserDetail {
    id: number;
    name: string;
    username: string | null;
    email: string | null;
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    phone: string | null;
    gender: 'male' | 'female';
    blood_group: string | null;
    address: string | null;
    avatar: string | null;
    is_active: boolean;
    created_at?: string;
}

interface GuardianDetail {
    id: number;
    name: string;
    phone: string;
    relationship: string;
    email?: string | null;
    occupation?: string | null;
    address?: string | null;
}

interface EnrollmentDetail {
    id: number;
    student_id: number;
    academic_session_id: number;
    class_id: number;
    section_id: number;
    previous_enrollment_id?: number | null;
    previous_enrollment?: {
        id: number;
        academic_session?: { id: number; name: string } | null;
        school_class?: { id: number; name: string } | null;
        section?: { id: number; name: string } | null;
    } | null;
    roll_number: string | null;
    status: string;
    academic_session?: { id: number; name: string; is_current: boolean } | null;
    school_class?: { id: number; name: string; stage: string | null } | null;
    section?: { id: number; name: string; capacity: number | null } | null;
}

interface StudentMarkDetail {
    id: number;
    enrollment_id: number;
    ca1: number | null;
    ca2: number | null;
    ca3: number | null;
    exam_score: number | null;
    total_score: number | null;
    teacher_comment: string | null;
    subject?: { id: number; name: string; code: string } | null;
    grade?: { id: number; name: string; grade_point?: number; remark: string | null } | null;
    enrollment?: {
        academic_session?: { id: number; name: string } | null;
        school_class?: { id: number; name: string } | null;
        section?: { id: number; name: string } | null;
    } | null;
}

interface AttendanceDetail {
    id: number;
    enrollment_id: number;
    attendance_date: string;
    status: 'present' | 'absent' | 'late' | 'excused';
    remark: string | null;
    enrollment?: {
        academic_session?: { id: number; name: string } | null;
    } | null;
}

interface AttendanceStats {
    total: number;
    present: number;
    late: number;
    absent: number;
    rate: number;
}

interface Props {
    student: {
        id: number;
        user_id: number;
        guardian_id: number | null;
        admission_number: string;
        admission_date: string;
        date_of_birth: string | null;
        state_of_origin: string | null;
        lga: string | null;
        user: UserDetail;
        guardian?: GuardianDetail | null;
        enrollments?: EnrollmentDetail[];
        current_enrollment?: EnrollmentDetail | null;
    };
    marks: StudentMarkDetail[];
    attendances: AttendanceDetail[];
    attendanceStats: AttendanceStats;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Students & Enrollment',
                href: peopleStudentsIndex().url,
            },
            {
                title: 'Student Profile',
                href: '#',
            },
        ],
    },
});

const activeTab = ref<'overview' | 'guardian' | 'enrollments' | 'marks' | 'attendance'>('overview');

const printProfile = () => {
    window.print();
};

const averageScore = computed(() => {
    if (!props.marks || props.marks.length === 0) return null;
    const scoredMarks = props.marks.filter((m) => m.total_score !== null && m.total_score !== undefined);
    if (scoredMarks.length === 0) return null;
    const total = scoredMarks.reduce((sum, m) => sum + Number(m.total_score), 0);
    return Math.round((total / scoredMarks.length) * 10) / 10;
});

const studentInitials = computed(() => {
    const f = props.student.user.first_name?.[0] ?? props.student.user.name?.[0] ?? 'S';
    const l = props.student.user.last_name?.[0] ?? '';
    return `${f}${l}`.toUpperCase();
});

const calculateAge = (dobString: string | null): number | null => {
    if (!dobString) return null;
    const dob = new Date(dobString);
    if (isNaN(dob.getTime())) return null;
    const diffMs = Date.now() - dob.getTime();
    const ageDt = new Date(diffMs);
    return Math.abs(ageDt.getUTCFullYear() - 1970);
};
</script>

<template>
    <Head :title="`${student.user.name} - Student Profile`" />

    <div class="space-y-6 max-w-7xl w-full min-w-0 mx-auto p-3 sm:p-6 lg:p-8 pb-12 print:p-0 print:space-y-3 overflow-x-hidden">
        <!-- Top Back Navigation & Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <Link
                    :href="peopleStudentsIndex().url"
                    class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-medium text-muted-foreground hover:text-foreground transition-colors group"
                >
                    <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1 shrink-0" />
                    <span>Back to Students Directory</span>
                </Link>

                <template v-if="student.current_enrollment?.status === 'graduated'">
                    <span class="text-muted-foreground/40">•</span>
                    <Link
                        :href="peopleAlumniIndex().url"
                        class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-medium text-purple-600 hover:text-purple-700 dark:text-purple-400 transition-colors"
                    >
                        <Award class="h-4 w-4 shrink-0" />
                        <span>Alumni Directory</span>
                    </Link>
                </template>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-xs print:hidden w-full sm:w-auto justify-center"
                    @click="printProfile"
                >
                    <Printer class="h-3.5 w-3.5 shrink-0" />
                    <span>Print Profile</span>
                </Button>
            </div>
        </div>

        <!-- Student Hero Profile Header -->
        <Card class="overflow-hidden border-border/60 shadow-xs w-full max-w-full min-w-0">
            <div class="h-16 sm:h-20 bg-linear-to-r from-primary/15 via-purple-500/10 to-emerald-500/15 border-b border-border/40" />
            <CardContent class="relative pt-0 pb-6 px-4 sm:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 -mt-8 sm:-mt-12">
                    <div class="flex flex-col sm:flex-row sm:items-end gap-3 sm:gap-4 min-w-0 w-full">
                        <!-- Avatar / Initials -->
                        <div class="h-18 w-18 sm:h-24 sm:w-24 rounded-2xl bg-card border-4 border-background shadow-md flex items-center justify-center text-lg sm:text-2xl font-bold text-primary shrink-0 select-none">
                            <img
                                v-if="student.user.avatar"
                                :src="student.user.avatar"
                                :alt="student.user.name"
                                class="h-full w-full rounded-xl object-cover"
                            />
                            <span v-else>{{ studentInitials }}</span>
                        </div>

                        <!-- Core Titles & Badges -->
                        <div class="space-y-1.5 min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                <h1 class="text-lg sm:text-2xl font-bold tracking-tight text-foreground break-words">
                                    {{ student.user.name }}
                                </h1>
                                <Badge
                                    v-if="student.current_enrollment?.status === 'graduated'"
                                    variant="outline"
                                    class="bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-200 text-xs gap-1 font-semibold"
                                >
                                    <GraduationCap class="h-3 w-3 shrink-0" />
                                    <span>Alumni / Graduated</span>
                                </Badge>
                                <Badge
                                    v-else
                                    :variant="student.user.is_active ? 'secondary' : 'outline'"
                                    :class="student.user.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200' : 'text-muted-foreground'"
                                    class="text-xs gap-1"
                                >
                                    <CheckCircle2 v-if="student.user.is_active" class="h-3 w-3 shrink-0" />
                                    <XCircle v-else class="h-3 w-3 shrink-0" />
                                    <span>{{ student.user.is_active ? 'Active Student' : 'Inactive' }}</span>
                                </Badge>
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
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted-foreground font-medium">
                                <div class="flex items-center gap-1.5 font-mono text-primary font-semibold shrink-0">
                                    <IdCard class="h-3.5 w-3.5 shrink-0" />
                                    <span>{{ student.admission_number }}</span>
                                </div>
                                <div v-if="student.user.email" class="flex items-center gap-1.5 min-w-0">
                                    <Mail class="h-3.5 w-3.5 shrink-0 text-muted-foreground/70" />
                                    <span class="break-all">{{ student.user.email }}</span>
                                </div>
                                <div v-if="student.user.phone" class="flex items-center gap-1.5 shrink-0">
                                    <Phone class="h-3.5 w-3.5 shrink-0 text-muted-foreground/70" />
                                    <span>{{ student.user.phone }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <Calendar class="h-3.5 w-3.5 shrink-0 text-muted-foreground/70" />
                                    <span>Admitted: {{ student.admission_date }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- KPI Metric Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 w-full min-w-0">
            <!-- Current Class Card -->
            <Card class="border-border/60 min-w-0">
                <CardContent class="p-4 flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] text-muted-foreground font-medium uppercase tracking-wider">Current Class</p>
                        <h3 class="text-base sm:text-lg font-bold text-foreground mt-0.5 truncate">
                            {{ student.current_enrollment?.school_class?.name ?? 'Unallocated' }}
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5 truncate">
                            Section {{ student.current_enrollment?.section?.name ?? 'General' }}
                            <span v-if="student.current_enrollment?.school_class?.stage"> ({{ student.current_enrollment.school_class.stage }})</span>
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <GraduationCap class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <!-- Academic Session Card -->
            <Card class="border-border/60 min-w-0">
                <CardContent class="p-4 flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] text-muted-foreground font-medium uppercase tracking-wider">Academic Session</p>
                        <h3 class="text-base sm:text-lg font-bold text-foreground mt-0.5 truncate">
                            {{ student.current_enrollment?.academic_session?.name ?? 'No Session' }}
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5 truncate">
                            Roll No: <span class="font-mono font-medium">{{ student.current_enrollment?.roll_number ?? student.admission_number }}</span>
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                        <Calendar class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <!-- Attendance Rate Card -->
            <Card class="border-border/60 min-w-0">
                <CardContent class="p-4 flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] text-muted-foreground font-medium uppercase tracking-wider">Attendance Rate</p>
                        <h3 class="text-base sm:text-lg font-bold text-foreground mt-0.5">
                            {{ attendanceStats.rate }}%
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5 truncate">
                            {{ attendanceStats.present }} Present of {{ attendanceStats.total }} days
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <Clock class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <!-- Academic Marks Summary Card -->
            <Card class="border-border/60 min-w-0">
                <CardContent class="p-4 flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] text-muted-foreground font-medium uppercase tracking-wider">Academic Performance</p>
                        <h3 class="text-base sm:text-lg font-bold text-foreground mt-0.5">
                            {{ averageScore !== null ? `${averageScore}%` : 'N/A' }}
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5 truncate">
                            {{ marks.length }} subjects recorded
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <Award class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Navigation Tabs -->
        <div class="w-full max-w-full min-w-0 border-b border-border/60">
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
                <button
                    type="button"
                    @click="activeTab = 'overview'"
                    class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 whitespace-nowrap shrink-0"
                    :class="activeTab === 'overview'
                        ? 'border-primary text-primary bg-primary/5'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/30'"
                >
                    <IdCard class="h-4 w-4 shrink-0" />
                    <span>Bio-Data & Profile</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'guardian'"
                    class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 whitespace-nowrap shrink-0"
                    :class="activeTab === 'guardian'
                        ? 'border-primary text-primary bg-primary/5'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/30'"
                >
                    <HeartHandshake class="h-4 w-4 shrink-0" />
                    <span>Parent / Guardian</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'enrollments'"
                    class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 whitespace-nowrap shrink-0"
                    :class="activeTab === 'enrollments'
                        ? 'border-primary text-primary bg-primary/5'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/30'"
                >
                    <GraduationCap class="h-4 w-4 shrink-0" />
                    <span>Enrollment History ({{ student.enrollments?.length ?? 0 }})</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'marks'"
                    class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 whitespace-nowrap shrink-0"
                    :class="activeTab === 'marks'
                        ? 'border-primary text-primary bg-primary/5'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/30'"
                >
                    <Award class="h-4 w-4 shrink-0" />
                    <span>Examinations & CA Marks ({{ marks.length }})</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'attendance'"
                    class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 whitespace-nowrap shrink-0"
                    :class="activeTab === 'attendance'
                        ? 'border-primary text-primary bg-primary/5'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/30'"
                >
                    <Clock class="h-4 w-4 shrink-0" />
                    <span>Attendance Log ({{ attendanceStats.total }})</span>
                </button>
            </div>
        </div>

        <!-- TAB 1: BIO-DATA & PROFILE -->
        <div v-if="activeTab === 'overview'" class="space-y-6 w-full min-w-0">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 w-full min-w-0">
                <!-- Personal Bio-Data Card -->
                <Card class="border-border/60 shadow-xs min-w-0 overflow-hidden w-full">
                    <CardHeader class="pb-3 border-b border-border/40 px-4 sm:px-6">
                        <CardTitle class="text-sm font-semibold flex items-center gap-2">
                            <User class="h-4 w-4 text-primary shrink-0" />
                            <span>Personal Information</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4 px-4 sm:px-6 divide-y divide-border/30 text-xs">
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Full Name</span>
                            <span class="font-semibold text-foreground sm:text-right break-words">{{ student.user.name }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Gender</span>
                            <span class="font-medium capitalize text-foreground sm:text-right">{{ student.user.gender }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Date of Birth</span>
                            <span class="font-medium text-foreground sm:text-right break-words">
                                {{ student.date_of_birth ? `${student.date_of_birth} (${calculateAge(student.date_of_birth)} yrs)` : 'Not Recorded' }}
                            </span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">State of Origin</span>
                            <span class="font-medium text-foreground sm:text-right">{{ student.state_of_origin ?? 'Not Recorded' }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">L.G.A.</span>
                            <span class="font-medium text-foreground sm:text-right">{{ student.lga ?? 'Not Recorded' }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Blood Group</span>
                            <span class="font-medium text-foreground sm:text-right">{{ student.user.blood_group ?? 'Not Recorded' }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1">
                            <span class="text-muted-foreground shrink-0">Home Address</span>
                            <span class="font-medium text-foreground sm:text-right break-words sm:max-w-[240px]">
                                {{ student.user.address ?? 'Not Recorded' }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Student Portal & Registration Card -->
                <Card class="border-border/60 shadow-xs min-w-0 overflow-hidden w-full">
                    <CardHeader class="pb-3 border-b border-border/40 px-4 sm:px-6">
                        <CardTitle class="text-sm font-semibold flex items-center gap-2">
                            <ShieldCheck class="h-4 w-4 text-purple-500 shrink-0" />
                            <span>Student Portal & Admission Details</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4 px-4 sm:px-6 divide-y divide-border/30 text-xs">
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Admission Number</span>
                            <span class="font-mono font-semibold text-primary sm:text-right break-all">{{ student.admission_number }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Admission Date</span>
                            <span class="font-medium text-foreground sm:text-right">{{ student.admission_date }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Portal Login Email</span>
                            <span class="font-mono text-foreground font-medium sm:text-right break-all">{{ student.user.email ?? 'None' }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Portal Username</span>
                            <span class="font-mono text-foreground font-medium sm:text-right break-all">{{ student.user.username ?? 'None' }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Student Phone</span>
                            <span class="font-medium text-foreground sm:text-right">{{ student.user.phone ?? 'None' }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Account Status</span>
                            <Badge
                                :variant="student.user.is_active ? 'secondary' : 'outline'"
                                :class="student.user.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200' : 'text-muted-foreground'"
                                class="text-[11px] w-fit"
                            >
                                {{ student.user.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                            <span class="text-muted-foreground shrink-0">Default Initial Password</span>
                            <span class="font-mono font-semibold px-1.5 py-0.5 rounded bg-muted text-foreground w-fit">12345678</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- TAB 2: PARENT / GUARDIAN -->
        <div v-if="activeTab === 'guardian'" class="space-y-6 w-full min-w-0">
            <Card class="border-border/60 shadow-xs max-w-2xl min-w-0 overflow-hidden w-full">
                <CardHeader class="pb-3 border-b border-border/40 px-4 sm:px-6">
                    <CardTitle class="text-sm font-semibold flex items-center gap-2">
                        <HeartHandshake class="h-4 w-4 text-emerald-500 shrink-0" />
                        <span>Assigned Parent / Legal Guardian</span>
                    </CardTitle>
                </CardHeader>
                <CardContent v-if="student.guardian" class="pt-4 px-4 sm:px-6 divide-y divide-border/30 text-xs">
                    <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                        <span class="text-muted-foreground shrink-0">Guardian Full Name</span>
                        <span class="font-semibold text-foreground text-sm sm:text-right break-words">{{ student.guardian.name }}</span>
                    </div>
                    <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                        <span class="text-muted-foreground shrink-0">Relationship</span>
                        <Badge variant="secondary" class="font-medium text-xs w-fit">{{ student.guardian.relationship }}</Badge>
                    </div>
                    <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                        <span class="text-muted-foreground shrink-0">Primary Contact Phone</span>
                        <span class="font-mono font-medium text-foreground sm:text-right">{{ student.guardian.phone }}</span>
                    </div>
                    <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                        <span class="text-muted-foreground shrink-0">Email Address</span>
                        <span class="font-medium text-foreground sm:text-right break-all">{{ student.guardian.email ?? 'Not Provided' }}</span>
                    </div>
                    <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                        <span class="text-muted-foreground shrink-0">Occupation</span>
                        <span class="font-medium text-foreground sm:text-right">{{ student.guardian.occupation ?? 'Not Provided' }}</span>
                    </div>
                    <div class="py-2.5 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1">
                        <span class="text-muted-foreground shrink-0">Address</span>
                        <span class="font-medium text-foreground sm:text-right break-words sm:max-w-xs">
                            {{ student.guardian.address ?? 'Not Provided' }}
                        </span>
                    </div>
                </CardContent>
                <CardContent v-else class="py-12 text-center text-muted-foreground px-4">
                    <HeartHandshake class="h-8 w-8 mx-auto text-muted-foreground/60 mb-2" />
                    <p class="font-medium text-sm">No parent or legal guardian assigned to this student profile.</p>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 3: ENROLLMENT HISTORY -->
        <div v-if="activeTab === 'enrollments'" class="space-y-4 w-full min-w-0">
            <Card class="border-border/60 shadow-xs overflow-hidden w-full max-w-full min-w-0">
                <CardHeader class="pb-3 border-b border-border/40 px-4 sm:px-6">
                    <CardTitle class="text-sm font-semibold flex items-center gap-2">
                        <GraduationCap class="h-4 w-4 text-primary shrink-0" />
                        <span>Session Enrollment History</span>
                    </CardTitle>
                </CardHeader>
                <div class="w-full max-w-full overflow-x-auto min-w-0">
                    <table class="w-full min-w-[640px] text-left text-xs">
                        <thead class="bg-muted/40 text-muted-foreground uppercase tracking-wider text-[11px] border-b border-border/60">
                            <tr>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Academic Session</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Class</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Section</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Previous Record</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Roll Number</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="enr in student.enrollments"
                                :key="enr.id"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td class="px-4 sm:px-6 py-3.5 font-medium text-foreground flex items-center gap-2">
                                    <span>{{ enr.academic_session?.name ?? 'Unknown Session' }}</span>
                                    <Badge
                                        v-if="enr.academic_session?.is_current"
                                        variant="secondary"
                                        class="text-[10px] bg-primary/10 text-primary border-primary/20 shrink-0"
                                    >
                                        Current
                                    </Badge>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-foreground font-medium">
                                    {{ enr.school_class?.name ?? 'N/A' }}
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-muted-foreground">
                                    Section {{ enr.section?.name ?? 'General' }}
                                </td>
                                <td class="px-4 sm:px-6 py-3.5">
                                    <span v-if="enr.previous_enrollment" class="inline-flex items-center gap-1 text-muted-foreground">
                                        <span class="font-medium text-foreground">{{ enr.previous_enrollment.school_class?.name }}</span>
                                        <span>(Sec {{ enr.previous_enrollment.section?.name }})</span>
                                        <span class="text-[10px] opacity-75">[{{ enr.previous_enrollment.academic_session?.name }}]</span>
                                    </span>
                                    <span v-else class="text-muted-foreground italic">
                                        — Initial Entry
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 font-mono text-muted-foreground">
                                    {{ enr.roll_number ?? student.admission_number }}
                                </td>
                                <td class="px-4 sm:px-6 py-3.5">
                                    <Badge
                                        variant="outline"
                                        class="capitalize text-[11px]"
                                        :class="{
                                            'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200': enr.status === 'active',
                                            'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200': enr.status === 'promoted',
                                            'text-muted-foreground': enr.status !== 'active' && enr.status !== 'promoted',
                                        }"
                                    >
                                        {{ enr.status }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="!student.enrollments || student.enrollments.length === 0">
                                <td colspan="6" class="px-4 sm:px-6 py-8 text-center text-muted-foreground">
                                    No enrollment records found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- TAB 4: EXAMINATIONS & CA MARKS -->
        <div v-if="activeTab === 'marks'" class="space-y-4 w-full min-w-0">
            <Card class="border-border/60 shadow-xs overflow-hidden w-full max-w-full min-w-0">
                <CardHeader class="pb-3 border-b border-border/40 px-4 sm:px-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <CardTitle class="text-sm font-semibold flex items-center gap-2">
                            <Award class="h-4 w-4 text-amber-500 shrink-0" />
                            <span>Academic Performance & Assessment Marks</span>
                        </CardTitle>
                        <CardDescription class="text-xs mt-0.5">
                            Continuous Assessment (CA) and terminal exam scores.
                        </CardDescription>
                    </div>
                </CardHeader>
                <div class="w-full max-w-full overflow-x-auto min-w-0">
                    <table class="w-full min-w-[700px] text-left text-xs">
                        <thead class="bg-muted/40 text-muted-foreground uppercase tracking-wider text-[11px] border-b border-border/60">
                            <tr>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Subject</th>
                                <th class="px-3 sm:px-4 py-3 font-semibold text-center">CA 1</th>
                                <th class="px-3 sm:px-4 py-3 font-semibold text-center">CA 2</th>
                                <th class="px-3 sm:px-4 py-3 font-semibold text-center">CA 3</th>
                                <th class="px-3 sm:px-4 py-3 font-semibold text-center">Exam</th>
                                <th class="px-3 sm:px-4 py-3 font-semibold text-center">Total Score</th>
                                <th class="px-3 sm:px-4 py-3 font-semibold text-center">Grade</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Teacher Remark</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="mark in marks"
                                :key="mark.id"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td class="px-4 sm:px-6 py-3.5">
                                    <div class="font-medium text-foreground">
                                        {{ mark.subject?.name ?? 'Subject' }}
                                    </div>
                                    <div class="text-[11px] font-mono text-muted-foreground">
                                        {{ mark.subject?.code }}
                                    </div>
                                </td>
                                <td class="px-3 sm:px-4 py-3.5 text-center font-mono text-muted-foreground">
                                    {{ mark.ca1 ?? '-' }}
                                </td>
                                <td class="px-3 sm:px-4 py-3.5 text-center font-mono text-muted-foreground">
                                    {{ mark.ca2 ?? '-' }}
                                </td>
                                <td class="px-3 sm:px-4 py-3.5 text-center font-mono text-muted-foreground">
                                    {{ mark.ca3 ?? '-' }}
                                </td>
                                <td class="px-3 sm:px-4 py-3.5 text-center font-mono text-muted-foreground font-medium">
                                    {{ mark.exam_score ?? '-' }}
                                </td>
                                <td class="px-3 sm:px-4 py-3.5 text-center font-mono font-bold text-foreground">
                                    {{ mark.total_score ?? '-' }}
                                </td>
                                <td class="px-3 sm:px-4 py-3.5 text-center">
                                    <Badge
                                        v-if="mark.grade"
                                        variant="secondary"
                                        class="font-mono text-xs font-bold"
                                    >
                                        {{ mark.grade.name }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-muted-foreground text-xs italic">
                                    {{ mark.teacher_comment ?? mark.grade?.remark ?? 'No comment' }}
                                </td>
                            </tr>
                            <tr v-if="marks.length === 0">
                                <td colspan="8" class="px-4 sm:px-6 py-12 text-center text-muted-foreground">
                                    <Award class="h-8 w-8 mx-auto text-muted-foreground/60 mb-2" />
                                    <p class="font-medium">No marks or assessment scores recorded yet.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- TAB 5: ATTENDANCE LOG -->
        <div v-if="activeTab === 'attendance'" class="space-y-4 w-full min-w-0">
            <Card class="border-border/60 shadow-xs overflow-hidden w-full max-w-full min-w-0">
                <CardHeader class="pb-3 border-b border-border/40 px-4 sm:px-6 flex flex-row items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <CardTitle class="text-sm font-semibold flex items-center gap-2">
                            <Clock class="h-4 w-4 text-emerald-500 shrink-0" />
                            <span>Daily Attendance</span>
                        </CardTitle>
                        <CardDescription class="text-xs mt-0.5 truncate">
                            Recent attendance tracking records for this student.
                        </CardDescription>
                    </div>
                    <Badge variant="outline" class="font-mono text-xs shrink-0">
                        Rate: {{ attendanceStats.rate }}%
                    </Badge>
                </CardHeader>
                <div class="w-full max-w-full overflow-x-auto min-w-0">
                    <table class="w-full min-w-[500px] text-left text-xs">
                        <thead class="bg-muted/40 text-muted-foreground uppercase tracking-wider text-[11px] border-b border-border/60">
                            <tr>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Date</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Academic Session</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Attendance Status</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Remark</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="att in attendances"
                                :key="att.id"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td class="px-4 sm:px-6 py-3.5 font-medium text-foreground">
                                    {{ att.attendance_date }}
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-muted-foreground">
                                    {{ att.enrollment?.academic_session?.name ?? 'N/A' }}
                                </td>
                                <td class="px-4 sm:px-6 py-3.5">
                                    <Badge
                                        variant="outline"
                                        class="capitalize text-[11px] gap-1 font-medium"
                                        :class="{
                                            'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200': att.status === 'present',
                                            'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200': att.status === 'late',
                                            'bg-red-500/10 text-red-700 dark:text-red-400 border-red-200': att.status === 'absent',
                                            'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200': att.status === 'excused',
                                        }"
                                    >
                                        <CheckCircle2 v-if="att.status === 'present'" class="h-3 w-3 shrink-0" />
                                        <Clock v-else-if="att.status === 'late'" class="h-3 w-3 shrink-0" />
                                        <XCircle v-else class="h-3 w-3 shrink-0" />
                                        <span>{{ att.status }}</span>
                                    </Badge>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-muted-foreground text-xs italic">
                                    {{ att.remark ?? 'Normal' }}
                                </td>
                            </tr>
                            <tr v-if="attendances.length === 0">
                                <td colspan="4" class="px-4 sm:px-6 py-12 text-center text-muted-foreground">
                                    <Clock class="h-8 w-8 mx-auto text-muted-foreground/60 mb-2" />
                                    <p class="font-medium">No daily attendance records found.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </div>
</template>
