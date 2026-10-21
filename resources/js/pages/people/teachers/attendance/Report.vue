<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    CalendarCheck,
    CheckCircle2,
    Clock,
    Download,
    FileSpreadsheet,
    Filter,
    Grid3X3,
    ListChecks,
    Printer,
    Search,
    TrendingUp,
    UserCheck,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { usePrintOrientation } from '@/composables/usePrintOrientation';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    index as teacherAttendanceIndex,
    report as teacherAttendanceReport,
} from '@/routes/people/teachers/attendance';
import type { AcademicSession } from '@/types/academic';

interface OverallMetrics {
    total_staff: number;
    distinct_dates: number;
    total_records: number;
    present_count: number;
    late_count: number;
    excused_count: number;
    absent_count: number;
    attendance_rate: number;
    at_risk_count: number;
}

interface TeacherSummaryItem {
    teacher_id: number;
    staff_id: string;
    name: string;
    email: string;
    gender: string | null;
    avatar: string | null;
    department_name: string;
    designation: string;
    qualification: string;
    total_days: number;
    present_count: number;
    late_count: number;
    excused_count: number;
    absent_count: number;
    attendance_rate: number;
    status_level: 'excellent' | 'good' | 'warning' | 'critical';
    daily_statuses: Record<string, string>;
}

interface DepartmentOption {
    id: number;
    name: string;
}

interface SchoolBranding {
    name: string;
    code: string;
    motto: string | null;
    logo_url: string | null;
}

interface Props {
    sessions: AcademicSession[];
    departments: DepartmentOption[];
    selectedSessionId: number;
    selectedTermId: number;
    selectedDepartmentId: number | 'all';
    dateFrom: string;
    dateTo: string;
    overallMetrics: OverallMetrics;
    teacherSummaries: TeacherSummaryItem[];
    recordedDates: string[];
    school: SchoolBranding;
}

const props = defineProps<Props>();

// Filters State
const selectedSessionId = ref(props.selectedSessionId);
const selectedTermId = ref(props.selectedTermId);
const selectedDepartmentId = ref<string | number>(props.selectedDepartmentId);
const dateFrom = ref(props.dateFrom);
const dateTo = ref(props.dateTo);
const searchQuery = ref('');
const viewMode = ref<'summary' | 'matrix'>('summary');
const isFiltering = ref(false);

// Synchronize incoming props with local reactive state
watch(() => props.dateFrom, (val) => { dateFrom.value = val; });
watch(() => props.dateTo, (val) => { dateTo.value = val; });
watch(() => props.selectedSessionId, (val) => { selectedSessionId.value = val; });
watch(() => props.selectedTermId, (val) => { selectedTermId.value = val; });
watch(() => props.selectedDepartmentId, (val) => { selectedDepartmentId.value = val; });

// Session & Term helpers
const activeSession = computed(() => {
    return props.sessions.find((s) => s.id === Number(selectedSessionId.value));
});

const availableTerms = computed(() => {
    return activeSession.value?.terms || [];
});

const activeTerm = computed(() => {
    return availableTerms.value.find((t) => t.id === Number(selectedTermId.value));
});

// Search filter
const filteredSummaries = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.teacherSummaries;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.teacherSummaries.filter(
        (t) =>
            t.name.toLowerCase().includes(q) ||
            t.staff_id.toLowerCase().includes(q) ||
            t.department_name.toLowerCase().includes(q) ||
            t.designation.toLowerCase().includes(q)
    );
});

// Navigation & Actions
const applyFilters = () => {
    isFiltering.value = true;
    router.get(
        teacherAttendanceReport().url,
        {
            session_id: selectedSessionId.value,
            term_id: selectedTermId.value,
            department_id: selectedDepartmentId.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isFiltering.value = false;
            },
        }
    );
};

const setDatePreset = (preset: 'all' | 'this_month' | 'last_30_days' | 'today') => {
    const today = new Date();
    const todayStr = today.toISOString().substring(0, 10);

    if (preset === 'today') {
        dateFrom.value = todayStr;
        dateTo.value = todayStr;
    } else if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        dateFrom.value = firstDay.toISOString().substring(0, 10);
        dateTo.value = todayStr;
    } else if (preset === 'last_30_days') {
        const prior30 = new Date();
        prior30.setDate(prior30.getDate() - 30);
        dateFrom.value = prior30.toISOString().substring(0, 10);
        dateTo.value = todayStr;
    } else if (preset === 'all') {
        dateFrom.value = props.dateFrom;
        dateTo.value = props.dateTo;
    }
    applyFilters();
};

const onSessionChange = () => {
    const sess = props.sessions.find((s) => s.id === Number(selectedSessionId.value));
    if (sess && sess.terms && sess.terms.length > 0) {
        const curTerm = sess.terms.find((t) => t.is_current) || sess.terms[0];
        selectedTermId.value = curTerm.id;
        const todayStr = new Date().toISOString().substring(0, 10);
        if (curTerm.start_date) {
            const startStr = curTerm.start_date.substring(0, 10);
            if (startStr <= todayStr) {
                dateFrom.value = startStr;
            }
        }
        if (curTerm.end_date) {
            const endStr = curTerm.end_date.substring(0, 10);
            dateTo.value = endStr <= todayStr ? endStr : todayStr;
        }
    }
    applyFilters();
};

const onTermChange = () => {
    const term = availableTerms.value.find((t) => t.id === Number(selectedTermId.value));
    if (term) {
        const todayStr = new Date().toISOString().substring(0, 10);
        if (term.start_date) {
            const startStr = term.start_date.substring(0, 10);
            if (startStr <= todayStr) {
                dateFrom.value = startStr;
            }
        }
        if (term.end_date) {
            const endStr = term.end_date.substring(0, 10);
            dateTo.value = endStr <= todayStr ? endStr : todayStr;
        }
    }
    applyFilters();
};

const downloadCsv = () => {
    const url = new URL(window.location.origin + teacherAttendanceReport().url);
    url.searchParams.set('session_id', String(selectedSessionId.value));
    url.searchParams.set('term_id', String(selectedTermId.value));
    url.searchParams.set('department_id', String(selectedDepartmentId.value));
    url.searchParams.set('date_from', dateFrom.value);
    url.searchParams.set('date_to', dateTo.value);
    url.searchParams.set('export', 'csv');
    window.open(url.toString(), '_blank');
};

const { applyStyle: enforceLandscape } = usePrintOrientation('landscape', '8mm');

const printReport = () => {
    enforceLandscape();
    window.print();
};

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2);
}

const formatDateShort = (dateStr: string) => {
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', weekday: 'short' });
    } catch {
        return dateStr;
    }
};
</script>

<template>
    <Head title="Teacher Attendance Reports" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8 print:p-0 print:space-y-3">
        <!-- Institutional Letterhead Print Header -->
        <PrintHeader
            title="Teacher & Staff Attendance Report"
            :subtitle="`Staff Punctuality, Daily Roster & Aggregate Attendance (${dateFrom} to ${dateTo})`"
            :session-term="`${activeSession?.name || ''} - ${activeTerm?.name || ''}`"
            :class-section="selectedDepartmentId === 'all' ? 'All Departments' : (departments.find(d => d.id === Number(selectedDepartmentId))?.name || 'Selected Department')"
        />

        <!-- Web Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl flex items-center gap-2.5">
                    <FileSpreadsheet class="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                    <span>Teacher Attendance &amp; Performance Reports</span>
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Multi-period teaching staff attendance analytics, punctuality metrics, and staff absence summaries.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    class="h-10 gap-2"
                    as-child
                >
                    <a :href="teacherAttendanceIndex().url">
                        <CalendarCheck class="h-4 w-4 text-emerald-600" />
                        <span>Daily Register</span>
                    </a>
                </Button>

                <Button
                    variant="outline"
                    class="h-10 gap-2"
                    @click="downloadCsv"
                >
                    <Download class="h-4 w-4" />
                    <span>Export CSV</span>
                </Button>

                <Button
                    class="h-10 gap-2 font-semibold shadow-sm"
                    @click="printReport"
                >
                    <Printer class="h-4 w-4" />
                    <span>Print Report</span>
                </Button>
            </div>
        </div>

        <!-- Filter Controls Card (Hidden in print) -->
        <Card class="border shadow-sm print:hidden">
            <CardHeader class="pb-3 border-b bg-muted/20">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <CardTitle class="text-sm font-semibold flex items-center gap-2">
                        <Filter class="h-4 w-4 text-muted-foreground" />
                        <span>Filter Period &amp; Department Scope</span>
                    </CardTitle>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-muted-foreground hidden sm:inline">Presets:</span>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="h-7 text-xs px-2.5"
                            @click="setDatePreset('all')"
                        >
                            All Attendance
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="h-7 text-xs px-2.5"
                            @click="setDatePreset('this_month')"
                        >
                            This Month
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="h-7 text-xs px-2.5"
                            @click="setDatePreset('last_30_days')"
                        >
                            Last 30 Days
                        </Button>
                        <Badge variant="outline" class="font-normal text-xs ml-auto">
                            Period: {{ dateFrom }} &rarr; {{ dateTo }}
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-4 sm:p-6">
                <form @submit.prevent="applyFilters">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6 items-end">
                        <!-- Session -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Session</Label>
                            <select
                                v-model="selectedSessionId"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                @change="onSessionChange"
                            >
                                <option v-for="s in sessions" :key="s.id" :value="s.id">
                                    {{ s.name }} {{ s.is_current ? '(Current)' : '' }}
                                </option>
                            </select>
                        </div>

                        <!-- Term -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Term</Label>
                            <select
                                v-model="selectedTermId"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                @change="onTermChange"
                            >
                                <option v-for="t in availableTerms" :key="t.id" :value="t.id">
                                    {{ t.name }} {{ t.is_current ? '(Current)' : '' }}
                                </option>
                            </select>
                        </div>

                        <!-- Department -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Department</Label>
                            <select
                                v-model="selectedDepartmentId"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                @change="applyFilters"
                            >
                                <option value="all">All Departments</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    {{ dept.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Date From -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Date From</Label>
                            <Input
                                v-model="dateFrom"
                                type="date"
                                class="h-10 text-sm"
                                @change="applyFilters"
                            />
                        </div>

                        <!-- Date To -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Date To</Label>
                            <Input
                                v-model="dateTo"
                                type="date"
                                class="h-10 text-sm"
                                @change="applyFilters"
                            />
                        </div>

                        <!-- Submit / Filter Button -->
                        <div>
                            <Button
                                type="submit"
                                class="h-10 w-full gap-2 font-semibold shadow-xs"
                                :disabled="isFiltering"
                            >
                                <Spinner v-if="isFiltering" class="h-4 w-4" />
                                <Filter v-else class="h-4 w-4" />
                                <span>{{ isFiltering ? 'Filtering...' : 'Apply Filter' }}</span>
                            </Button>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- KPI Summary Cards Row -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-5 print:grid-cols-5">
            <!-- Total Staff -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Teachers Evaluated</span>
                        <Users class="h-4 w-4 text-blue-500" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">
                        {{ overallMetrics.total_staff }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Across {{ overallMetrics.distinct_dates }} instructional days
                    </p>
                </CardContent>
            </Card>

            <!-- Overall Staff Attendance Rate -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Attendance Rate</span>
                        <TrendingUp class="h-4 w-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight" :class="{
                        'text-emerald-600 dark:text-emerald-400': overallMetrics.attendance_rate >= 90,
                        'text-amber-600 dark:text-amber-400': overallMetrics.attendance_rate >= 80 && overallMetrics.attendance_rate < 90,
                        'text-rose-600 dark:text-rose-400': overallMetrics.attendance_rate < 80,
                    }">
                        {{ overallMetrics.attendance_rate }}%
                    </div>
                    <div class="w-full bg-muted rounded-full h-1.5 mt-2 overflow-hidden">
                        <div
                            class="h-1.5 rounded-full transition-all duration-500"
                            :class="{
                                'bg-emerald-500': overallMetrics.attendance_rate >= 90,
                                'bg-amber-500': overallMetrics.attendance_rate >= 80 && overallMetrics.attendance_rate < 90,
                                'bg-rose-500': overallMetrics.attendance_rate < 80,
                            }"
                            :style="{ width: `${Math.min(overallMetrics.attendance_rate, 100)}%` }"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Present & Late -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Present &amp; Late</span>
                        <UserCheck class="h-4 w-4 text-emerald-500" />
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ overallMetrics.present_count }}
                        </span>
                        <span class="text-sm font-semibold text-amber-600 dark:text-amber-400">
                            +{{ overallMetrics.late_count }} late
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Total school duty sessions
                    </p>
                </CardContent>
            </Card>

            <!-- Absent & Excused -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Absent &amp; Excused</span>
                        <XCircle class="h-4 w-4 text-rose-500" />
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {{ overallMetrics.absent_count }}
                        </span>
                        <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                            +{{ overallMetrics.excused_count }} exc.
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Missed duty sessions
                    </p>
                </CardContent>
            </Card>

            <!-- Staff Attendance Concerns -->
            <Card class="border shadow-xs col-span-2 sm:col-span-1">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Attendance Concerns</span>
                        <AlertCircle class="h-4 w-4 text-rose-500" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                        {{ overallMetrics.at_risk_count }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Staff with &ge;3 absences or &lt;85%
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Teacher Attendance Records Card -->
        <Card class="border shadow-sm overflow-hidden">
            <CardHeader class="p-4 sm:p-6 border-b bg-muted/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <CardTitle class="text-lg font-bold flex items-center gap-2">
                        <Users class="h-5 w-5 text-primary" />
                        <span>Staff Attendance &amp; Punctuality Breakdown</span>
                    </CardTitle>
                    <CardDescription class="text-xs sm:text-sm mt-0.5">
                        Evaluating {{ filteredSummaries.length }} teachers over {{ recordedDates.length }} recorded instructional days.
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-56">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search teacher or ID..."
                            class="h-9 pl-8 text-sm"
                        />
                    </div>

                    <!-- Toggle Summary / Daily Matrix View -->
                    <div v-if="recordedDates.length > 0 && recordedDates.length <= 31" class="flex items-center border rounded-md p-0.5 bg-background">
                        <Button
                            :variant="viewMode === 'summary' ? 'default' : 'ghost'"
                            size="sm"
                            class="h-7 text-xs px-2.5 gap-1"
                            @click="viewMode = 'summary'"
                        >
                            <ListChecks class="h-3.5 w-3.5" />
                            <span>Summary</span>
                        </Button>
                        <Button
                            :variant="viewMode === 'matrix' ? 'default' : 'ghost'"
                            size="sm"
                            class="h-7 text-xs px-2.5 gap-1"
                            @click="viewMode = 'matrix'"
                        >
                            <Grid3X3 class="h-3.5 w-3.5" />
                            <span>Daily Grid</span>
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <!-- 1. Summary Table -->
            <div v-if="viewMode === 'summary'" class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-muted/40 text-xs uppercase font-semibold text-muted-foreground border-b">
                        <tr>
                            <th class="px-3 py-3 text-center w-12">#</th>
                            <th class="px-4 py-3 min-w-[220px]">Teacher / Staff Details</th>
                            <th class="px-3 py-3">Department</th>
                            <th class="px-3 py-3 text-center">Days</th>
                            <th class="px-3 py-3 text-center text-emerald-600 dark:text-emerald-400">Present</th>
                            <th class="px-3 py-3 text-center text-amber-600 dark:text-amber-400">Late</th>
                            <th class="px-3 py-3 text-center text-blue-600 dark:text-blue-400">Excused</th>
                            <th class="px-3 py-3 text-center text-rose-600 dark:text-rose-400">Absent</th>
                            <th class="px-4 py-3 text-center">Attendance %</th>
                            <th class="px-3 py-3 text-center">Punctuality Rating</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="(teacher, index) in filteredSummaries"
                            :key="teacher.teacher_id"
                            class="hover:bg-muted/20 transition-colors"
                        >
                            <td class="px-3 py-3.5 text-center text-xs text-muted-foreground font-medium">
                                {{ index + 1 }}
                            </td>

                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <Avatar class="h-9 w-9 border">
                                        <AvatarImage :src="teacher.avatar || ''" :alt="teacher.name" />
                                        <AvatarFallback class="text-xs font-bold bg-primary/10 text-primary">
                                            {{ getInitials(teacher.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <div class="font-semibold text-foreground text-sm flex items-center gap-1.5">
                                            <span>{{ teacher.name }}</span>
                                            <Badge variant="outline" class="text-[10px] font-mono px-1.5 py-0">
                                                {{ teacher.staff_id }}
                                            </Badge>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ teacher.designation }} {{ teacher.qualification ? '• ' + teacher.qualification : '' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-3 py-3.5 text-xs text-muted-foreground">
                                <Badge variant="secondary" class="font-normal text-xs">
                                    {{ teacher.department_name }}
                                </Badge>
                            </td>

                            <td class="px-3 py-3.5 text-center text-muted-foreground">
                                {{ teacher.total_days }}
                            </td>

                            <td class="px-3 py-3.5 text-center font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ teacher.present_count }}
                            </td>

                            <td class="px-3 py-3.5 text-center font-semibold text-amber-600 dark:text-amber-400">
                                {{ teacher.late_count }}
                            </td>

                            <td class="px-3 py-3.5 text-center font-semibold text-blue-600 dark:text-blue-400">
                                {{ teacher.excused_count }}
                            </td>

                            <td class="px-3 py-3.5 text-center font-semibold text-rose-600 dark:text-rose-400">
                                {{ teacher.absent_count }}
                            </td>

                            <td class="px-4 py-3.5 text-center">
                                <div class="inline-flex flex-col items-center gap-1">
                                    <span class="font-bold text-xs" :class="{
                                        'text-emerald-600 dark:text-emerald-400': teacher.attendance_rate >= 90,
                                        'text-amber-600 dark:text-amber-400': teacher.attendance_rate >= 80 && teacher.attendance_rate < 90,
                                        'text-rose-600 dark:text-rose-400': teacher.attendance_rate < 80,
                                    }">
                                        {{ teacher.attendance_rate }}%
                                    </span>
                                    <div class="w-16 bg-muted rounded-full h-1 overflow-hidden">
                                        <div
                                            class="h-1 rounded-full transition-all"
                                            :class="{
                                                'bg-emerald-500': teacher.attendance_rate >= 90,
                                                'bg-amber-500': teacher.attendance_rate >= 80 && teacher.attendance_rate < 90,
                                                'bg-rose-500': teacher.attendance_rate < 80,
                                            }"
                                            :style="{ width: `${Math.min(teacher.attendance_rate, 100)}%` }"
                                        />
                                    </div>
                                </div>
                            </td>

                            <td class="px-3 py-3.5 text-center">
                                <Badge
                                    v-if="teacher.status_level === 'excellent'"
                                    class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 text-xs"
                                    variant="outline"
                                >
                                    Excellent
                                </Badge>
                                <Badge
                                    v-else-if="teacher.status_level === 'good'"
                                    class="bg-blue-500/15 text-blue-700 dark:text-blue-400 border-blue-500/30 text-xs"
                                    variant="outline"
                                >
                                    Good
                                </Badge>
                                <Badge
                                    v-else-if="teacher.status_level === 'warning'"
                                    class="bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30 text-xs"
                                    variant="outline"
                                >
                                    Review Needed
                                </Badge>
                                <Badge
                                    v-else
                                    class="bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-500/30 text-xs font-semibold"
                                    variant="outline"
                                >
                                    At Risk
                                </Badge>
                            </td>
                        </tr>

                        <tr v-if="filteredSummaries.length === 0">
                            <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">
                                No teacher records matching your search query.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 2. Daily Grid Matrix (Dates as Columns) -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-muted/40 text-[11px] uppercase font-semibold text-muted-foreground border-b">
                        <tr>
                            <th class="px-3 py-2.5 sticky left-0 bg-background z-10 border-r min-w-[180px]">Teacher</th>
                            <th
                                v-for="d in recordedDates"
                                :key="d"
                                class="px-2 py-2 text-center whitespace-nowrap min-w-[36px]"
                                :title="d"
                            >
                                {{ formatDateShort(d) }}
                            </th>
                            <th class="px-3 py-2.5 text-center border-l bg-muted/20 font-bold min-w-[60px]">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="teacher in filteredSummaries"
                            :key="teacher.teacher_id"
                            class="hover:bg-muted/20 transition-colors"
                        >
                            <td class="px-3 py-2 font-medium sticky left-0 bg-background z-10 border-r whitespace-nowrap">
                                <div class="font-semibold text-foreground">{{ teacher.name }}</div>
                                <div class="text-[10px] text-muted-foreground font-mono">{{ teacher.staff_id }} • {{ teacher.department_name }}</div>
                            </td>

                            <td
                                v-for="d in recordedDates"
                                :key="d"
                                class="px-1 py-1 text-center"
                            >
                                <span
                                    v-if="teacher.daily_statuses[d] === 'present'"
                                    class="inline-block w-6 h-6 leading-6 rounded bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 font-bold"
                                    title="Present"
                                >P</span>
                                <span
                                    v-else-if="teacher.daily_statuses[d] === 'late'"
                                    class="inline-block w-6 h-6 leading-6 rounded bg-amber-500/20 text-amber-700 dark:text-amber-400 font-bold"
                                    title="Late"
                                >L</span>
                                <span
                                    v-else-if="teacher.daily_statuses[d] === 'excused'"
                                    class="inline-block w-6 h-6 leading-6 rounded bg-blue-500/20 text-blue-700 dark:text-blue-400 font-bold"
                                    title="Excused"
                                >E</span>
                                <span
                                    v-else-if="teacher.daily_statuses[d] === 'absent'"
                                    class="inline-block w-6 h-6 leading-6 rounded bg-rose-500/20 text-rose-700 dark:text-rose-400 font-bold"
                                    title="Absent"
                                >A</span>
                                <span
                                    v-else
                                    class="inline-block w-6 h-6 leading-6 rounded bg-muted text-muted-foreground"
                                    title="Unrecorded"
                                >-</span>
                            </td>

                            <td class="px-3 py-2 text-center border-l bg-muted/10 font-bold" :class="{
                                'text-emerald-600': teacher.attendance_rate >= 90,
                                'text-amber-600': teacher.attendance_rate >= 80 && teacher.attendance_rate < 90,
                                'text-rose-600': teacher.attendance_rate < 80,
                            }">
                                {{ teacher.attendance_rate }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Print-Only Institutional Sign-off -->
            <div class="hidden print:flex justify-between items-end mt-8 pt-4 border-t border-black text-[11px] px-2 pb-2">
                <div>
                    <p class="font-semibold text-black">Human Resources / Registrar: _______________________</p>
                    <p class="text-[10px] text-gray-600 mt-1">Date: _______________________</p>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-black">Principal / Head of Institution: _______________________</p>
                    <p class="text-[10px] text-gray-600 mt-1">Official Institutional Seal & Date</p>
                </div>
            </div>
        </Card>
    </div>
</template>

<style scoped>
@media print {
    body {
        font-size: 10pt;
        background: white !important;
        color: black !important;
    }
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    th, td {
        border: 1px solid #d1d5db !important;
        padding: 4px 6px !important;
    }
}
</style>
