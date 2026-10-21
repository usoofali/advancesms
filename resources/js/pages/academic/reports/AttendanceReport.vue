<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    Calendar,
    CalendarCheck,
    CheckCircle2,
    ChevronDown,
    Clock,
    Download,
    Eye,
    FileSpreadsheet,
    Filter,
    GraduationCap,
    Grid3X3,
    Layers,
    ListChecks,
    Printer,
    Search,
    TrendingUp,
    UserCheck,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { usePrintOrientation } from '@/composables/usePrintOrientation';
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
import { attendance as academicAttendanceReport } from '@/routes/academic/reports';
import type { AcademicSession, AcademicTerm, SchoolClass, Section } from '@/types/academic';

interface OverallMetrics {
    total_students: number;
    distinct_dates: number;
    total_records: number;
    present_count: number;
    late_count: number;
    excused_count: number;
    absent_count: number;
    attendance_rate: number;
    chronic_absentees?: number;
}

interface ClassSummary {
    class_id: number;
    class_name: string;
    stage: string;
    sections_count: number;
    total_students: number;
    distinct_dates: number;
    total_records: number;
    present_count: number;
    late_count: number;
    excused_count: number;
    absent_count: number;
    attendance_rate: number;
    chronic_absentees: number;
}

interface StudentRosterItem {
    enrollment_id: number;
    student_id: number;
    admission_number: string;
    student_name: string;
    gender: string | null;
    section_name: string;
    total_days: number;
    present_count: number;
    late_count: number;
    excused_count: number;
    absent_count: number;
    attendance_rate: number;
    status_level: 'excellent' | 'good' | 'warning' | 'critical';
    daily_statuses: Record<string, string>;
}

interface SchoolBranding {
    name: string;
    code: string;
    motto: string | null;
    logo_url: string | null;
}

interface Props {
    sessions: AcademicSession[];
    classes: (SchoolClass & { sections: Section[] })[];
    selectedSessionId: number;
    selectedTermId: number;
    selectedClassId: number | 'all';
    selectedSectionId: number | 'all';
    dateFrom: string;
    dateTo: string;
    viewMode: 'school' | 'class';
    overallMetrics: OverallMetrics;
    classSummaries: ClassSummary[];
    studentRoster: StudentRosterItem[];
    recordedDates: string[];
    selectedClassName?: string;
    school: SchoolBranding;
}

const props = defineProps<Props>();

// Reactive Filters
const selectedSessionId = ref(props.selectedSessionId);
const selectedTermId = ref(props.selectedTermId);
const selectedClassId = ref<string | number>(props.selectedClassId);
const selectedSectionId = ref<string | number>(props.selectedSectionId);
const dateFrom = ref(props.dateFrom);
const dateTo = ref(props.dateTo);
const searchQuery = ref('');
const rosterViewMode = ref<'summary' | 'matrix'>('summary');

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

// Class & Section helpers
const currentClass = computed(() => {
    if (selectedClassId.value === 'all') {
        return null;
    }
    return props.classes.find((c) => c.id === Number(selectedClassId.value));
});

const availableSections = computed(() => {
    return currentClass.value?.sections || [];
});

// Filtered lists by search query
const filteredClassSummaries = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.classSummaries;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.classSummaries.filter(
        (c) =>
            c.class_name.toLowerCase().includes(q) ||
            c.stage.toLowerCase().includes(q)
    );
});

const filteredStudentRoster = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.studentRoster;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.studentRoster.filter(
        (s) =>
            s.student_name.toLowerCase().includes(q) ||
            s.admission_number.toLowerCase().includes(q) ||
            s.section_name.toLowerCase().includes(q)
    );
});

// Navigation & Actions
const applyFilters = () => {
    router.get(
        academicAttendanceReport(),
        {
            session_id: selectedSessionId.value,
            term_id: selectedTermId.value,
            class_id: selectedClassId.value,
            section_id: selectedSectionId.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
};

const onSessionChange = () => {
    const sess = props.sessions.find((s) => s.id === Number(selectedSessionId.value));
    if (sess && sess.terms && sess.terms.length > 0) {
        const curTerm = sess.terms.find((t) => t.is_current) || sess.terms[0];
        selectedTermId.value = curTerm.id;
        if (curTerm.start_date) {
            dateFrom.value = curTerm.start_date.substring(0, 10);
        }
        if (curTerm.end_date) {
            dateTo.value = curTerm.end_date.substring(0, 10);
        }
    }
    applyFilters();
};

const onTermChange = () => {
    const term = availableTerms.value.find((t) => t.id === Number(selectedTermId.value));
    if (term) {
        if (term.start_date) {
            dateFrom.value = term.start_date.substring(0, 10);
        }
        if (term.end_date) {
            dateTo.value = term.end_date.substring(0, 10);
        }
    }
    applyFilters();
};

const onClassChange = () => {
    selectedSectionId.value = 'all';
    applyFilters();
};

const selectClass = (classId: number) => {
    selectedClassId.value = classId;
    selectedSectionId.value = 'all';
    applyFilters();
};

const downloadCsv = () => {
    const url = new URL(window.location.origin + academicAttendanceReport().url);
    url.searchParams.set('session_id', String(selectedSessionId.value));
    url.searchParams.set('term_id', String(selectedTermId.value));
    url.searchParams.set('class_id', String(selectedClassId.value));
    url.searchParams.set('section_id', String(selectedSectionId.value));
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
    <Head title="Student Attendance Reports" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8 print:p-0 print:space-y-3">
        <!-- Institutional Letterhead Print Header -->
        <PrintHeader
            title="Student Attendance Report"
            :subtitle="viewMode === 'school' ? 'Comprehensive School-Wide Attendance Breakdown' : `Daily & Aggregate Attendance Register (${dateFrom} to ${dateTo})`"
            :session-term="`${activeSession?.name || ''} - ${activeTerm?.name || ''}`"
            :class-section="viewMode === 'school' ? 'All Classes' : (selectedClassName || 'Selected Class')"
        />

        <!-- Web Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl flex items-center gap-2.5">
                    <CalendarCheck class="h-7 w-7 text-amber-500" />
                    <span>Student Attendance Reports</span>
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Multi-period attendance aggregates, comparative class attendance rates, and student registers.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
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
                <div class="flex items-center justify-between">
                    <CardTitle class="text-sm font-semibold flex items-center gap-2">
                        <Filter class="h-4 w-4 text-muted-foreground" />
                        <span>Attendance Filters & Scope</span>
                    </CardTitle>
                    <div class="flex items-center gap-2">
                        <Badge variant="outline" class="font-normal text-xs">
                            Period: {{ dateFrom }} → {{ dateTo }}
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-4 sm:p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
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

                    <!-- Class -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Class</Label>
                        <select
                            v-model="selectedClassId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="onClassChange"
                        >
                            <option value="all">All Classes (School-Wide)</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Section (Disabled if All Classes) -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Section</Label>
                        <select
                            v-model="selectedSectionId"
                            :disabled="selectedClassId === 'all'"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                            @change="applyFilters"
                        >
                            <option value="all">All Sections</option>
                            <option v-for="sec in availableSections" :key="sec.id" :value="sec.id">
                                {{ sec.name }}
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
                </div>
            </CardContent>
        </Card>

        <!-- KPI Summary Cards Row -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-5 print:grid-cols-5">
            <!-- Total Students -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Students</span>
                        <Users class="h-4 w-4 text-blue-500" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">
                        {{ overallMetrics.total_students }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ overallMetrics.distinct_dates }} attendance days
                    </p>
                </CardContent>
            </Card>

            <!-- Overall Rate -->
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
                        <span class="text-xs font-medium uppercase tracking-wider">Present / Late</span>
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
                        Attended school sessions
                    </p>
                </CardContent>
            </Card>

            <!-- Absent & Excused -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Absent / Excused</span>
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
                        Missed school sessions
                    </p>
                </CardContent>
            </Card>

            <!-- Chronic Absentees -->
            <Card class="border shadow-xs col-span-2 sm:col-span-1">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Chronic Absentees</span>
                        <AlertCircle class="h-4 w-4 text-rose-500" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                        {{ overallMetrics.chronic_absentees ?? 0 }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Students with &ge;3 absences
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- 1. School-Wide View (Class Breakdown Matrix) -->
        <div v-if="viewMode === 'school'" class="space-y-4">
            <Card class="border shadow-sm overflow-hidden">
                <CardHeader class="p-4 sm:p-6 border-b bg-muted/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <CardTitle class="text-lg font-bold flex items-center gap-2">
                            <Layers class="h-5 w-5 text-primary" />
                            <span>Class-by-Class Attendance Breakdown</span>
                        </CardTitle>
                        <CardDescription class="text-xs sm:text-sm mt-0.5">
                            Comparative evaluation across all secondary levels and departments.
                        </CardDescription>
                    </div>

                    <div class="flex items-center gap-2 print:hidden w-full sm:w-64">
                        <div class="relative w-full">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search class name..."
                                class="h-9 pl-8 text-sm"
                            />
                        </div>
                    </div>
                </CardHeader>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/40 text-xs uppercase font-semibold text-muted-foreground border-b">
                            <tr>
                                <th class="px-4 py-3">Class</th>
                                <th class="px-3 py-3">Stage</th>
                                <th class="px-3 py-3 text-center">Sections</th>
                                <th class="px-3 py-3 text-center">Enrolled</th>
                                <th class="px-3 py-3 text-center">Days</th>
                                <th class="px-3 py-3 text-center">Present</th>
                                <th class="px-3 py-3 text-center">Late</th>
                                <th class="px-3 py-3 text-center">Excused</th>
                                <th class="px-3 py-3 text-center">Absent</th>
                                <th class="px-4 py-3 text-center">Attendance %</th>
                                <th class="px-3 py-3 text-center">Chronic Absentees</th>
                                <th class="px-4 py-3 text-right print:hidden">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="item in filteredClassSummaries"
                                :key="item.class_id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <td class="px-4 py-3.5 font-semibold text-foreground">
                                    {{ item.class_name }}
                                </td>
                                <td class="px-3 py-3.5">
                                    <Badge variant="outline" class="capitalize text-xs font-normal">
                                        {{ item.stage }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3.5 text-center text-muted-foreground">
                                    {{ item.sections_count }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-medium">
                                    {{ item.total_students }}
                                </td>
                                <td class="px-3 py-3.5 text-center text-muted-foreground">
                                    {{ item.distinct_dates }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-medium text-emerald-600 dark:text-emerald-400">
                                    {{ item.present_count }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-medium text-amber-600 dark:text-amber-400">
                                    {{ item.late_count }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-medium text-blue-600 dark:text-blue-400">
                                    {{ item.excused_count }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-medium text-rose-600 dark:text-rose-400">
                                    {{ item.absent_count }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <Badge
                                            :variant="item.attendance_rate >= 85 ? 'default' : (item.attendance_rate >= 75 ? 'secondary' : 'destructive')"
                                            class="text-xs font-semibold px-2 py-0.5"
                                        >
                                            {{ item.attendance_rate }}%
                                        </Badge>
                                        <div class="w-16 bg-muted rounded-full h-1 overflow-hidden">
                                            <div
                                                class="h-1 rounded-full transition-all"
                                                :class="{
                                                    'bg-emerald-500': item.attendance_rate >= 90,
                                                    'bg-amber-500': item.attendance_rate >= 80 && item.attendance_rate < 90,
                                                    'bg-rose-500': item.attendance_rate < 80,
                                                }"
                                                :style="{ width: `${Math.min(item.attendance_rate, 100)}%` }"
                                            />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <span
                                        v-if="item.chronic_absentees > 0"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400"
                                    >
                                        <AlertCircle class="h-3.5 w-3.5" />
                                        {{ item.chronic_absentees }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 py-3.5 text-right print:hidden">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 gap-1.5 text-xs text-primary hover:text-primary hover:bg-primary/10"
                                        @click="selectClass(item.class_id)"
                                    >
                                        <span>View Roster</span>
                                        <ArrowRight class="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>

                            <tr v-if="filteredClassSummaries.length === 0">
                                <td colspan="12" class="px-4 py-8 text-center text-muted-foreground">
                                    No classes matching your criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- 2. Class / Section Student Roster View -->
        <div v-else class="space-y-4">
            <Card class="border shadow-sm overflow-hidden">
                <CardHeader class="p-4 sm:p-6 border-b bg-muted/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs gap-1.5 print:hidden"
                            @click="selectClass('all' as any)"
                        >
                            <span>&larr; All Classes</span>
                        </Button>
                        <div>
                            <CardTitle class="text-lg font-bold flex items-center gap-2">
                                <GraduationCap class="h-5 w-5 text-primary" />
                                <span>{{ selectedClassName }} Student Attendance Roster</span>
                            </CardTitle>
                            <CardDescription class="text-xs sm:text-sm mt-0.5">
                                Showing {{ filteredStudentRoster.length }} students evaluated across {{ recordedDates.length }} school days.
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 print:hidden">
                        <!-- Search Box -->
                        <div class="relative w-full sm:w-56">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search student or admission #..."
                                class="h-9 pl-8 text-sm"
                            />
                        </div>

                        <!-- Toggle Summary / Daily Matrix View (if recorded dates <= 31) -->
                        <div v-if="recordedDates.length > 0 && recordedDates.length <= 31" class="flex items-center border rounded-md p-0.5 bg-background">
                            <Button
                                :variant="rosterViewMode === 'summary' ? 'default' : 'ghost'"
                                size="sm"
                                class="h-7 text-xs px-2.5 gap-1"
                                @click="rosterViewMode = 'summary'"
                            >
                                <ListChecks class="h-3.5 w-3.5" />
                                <span>Summary</span>
                            </Button>
                            <Button
                                :variant="rosterViewMode === 'matrix' ? 'default' : 'ghost'"
                                size="sm"
                                class="h-7 text-xs px-2.5 gap-1"
                                @click="rosterViewMode = 'matrix'"
                            >
                                <Grid3X3 class="h-3.5 w-3.5" />
                                <span>Daily Grid</span>
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <!-- 2A. Student Summary Table -->
                <div v-if="rosterViewMode === 'summary'" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/40 text-xs uppercase font-semibold text-muted-foreground border-b">
                            <tr>
                                <th class="px-3 py-3 text-center w-12">#</th>
                                <th class="px-3 py-3">Adm #</th>
                                <th class="px-4 py-3">Student Name</th>
                                <th class="px-3 py-3">Section</th>
                                <th class="px-3 py-3 text-center">Gender</th>
                                <th class="px-3 py-3 text-center">Days</th>
                                <th class="px-3 py-3 text-center text-emerald-600 dark:text-emerald-400">Present</th>
                                <th class="px-3 py-3 text-center text-amber-600 dark:text-amber-400">Late</th>
                                <th class="px-3 py-3 text-center text-blue-600 dark:text-blue-400">Excused</th>
                                <th class="px-3 py-3 text-center text-rose-600 dark:text-rose-400">Absent</th>
                                <th class="px-4 py-3 text-center">Attendance Rate</th>
                                <th class="px-3 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="(student, index) in filteredStudentRoster"
                                :key="student.enrollment_id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <td class="px-3 py-3.5 text-center text-xs text-muted-foreground font-medium">
                                    {{ index + 1 }}
                                </td>
                                <td class="px-3 py-3.5 font-mono text-xs font-semibold text-foreground">
                                    {{ student.admission_number }}
                                </td>
                                <td class="px-4 py-3.5 font-medium text-foreground">
                                    {{ student.student_name }}
                                </td>
                                <td class="px-3 py-3.5 text-xs text-muted-foreground">
                                    {{ student.section_name }}
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <Badge variant="outline" class="capitalize text-xs font-normal">
                                        {{ student.gender || 'N/A' }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3.5 text-center text-muted-foreground">
                                    {{ student.total_days }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ student.present_count }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-semibold text-amber-600 dark:text-amber-400">
                                    {{ student.late_count }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-semibold text-blue-600 dark:text-blue-400">
                                    {{ student.excused_count }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-semibold text-rose-600 dark:text-rose-400">
                                    {{ student.absent_count }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <span class="font-bold text-xs" :class="{
                                            'text-emerald-600 dark:text-emerald-400': student.attendance_rate >= 90,
                                            'text-amber-600 dark:text-amber-400': student.attendance_rate >= 80 && student.attendance_rate < 90,
                                            'text-rose-600 dark:text-rose-400': student.attendance_rate < 80,
                                        }">
                                            {{ student.attendance_rate }}%
                                        </span>
                                        <div class="w-16 bg-muted rounded-full h-1 overflow-hidden">
                                            <div
                                                class="h-1 rounded-full transition-all"
                                                :class="{
                                                    'bg-emerald-500': student.attendance_rate >= 90,
                                                    'bg-amber-500': student.attendance_rate >= 80 && student.attendance_rate < 90,
                                                    'bg-rose-500': student.attendance_rate < 80,
                                                }"
                                                :style="{ width: `${Math.min(student.attendance_rate, 100)}%` }"
                                            />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <Badge
                                        v-if="student.status_level === 'excellent'"
                                        class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 text-xs"
                                        variant="outline"
                                    >
                                        Excellent
                                    </Badge>
                                    <Badge
                                        v-else-if="student.status_level === 'good'"
                                        class="bg-blue-500/15 text-blue-700 dark:text-blue-400 border-blue-500/30 text-xs"
                                        variant="outline"
                                    >
                                        Good
                                    </Badge>
                                    <Badge
                                        v-else-if="student.status_level === 'warning'"
                                        class="bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30 text-xs"
                                        variant="outline"
                                    >
                                        At Risk
                                    </Badge>
                                    <Badge
                                        v-else
                                        class="bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-500/30 text-xs"
                                        variant="outline"
                                    >
                                        Chronic Absentee
                                    </Badge>
                                </td>
                            </tr>

                            <tr v-if="filteredStudentRoster.length === 0">
                                <td colspan="12" class="px-4 py-8 text-center text-muted-foreground">
                                    No students enrolled or matching criteria in this class.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2B. Daily Matrix View (Dates as Columns) -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-muted/40 text-[11px] uppercase font-semibold text-muted-foreground border-b">
                            <tr>
                                <th class="px-3 py-2.5 sticky left-0 bg-background z-10 border-r">Student</th>
                                <th
                                    v-for="d in recordedDates"
                                    :key="d"
                                    class="px-2 py-2 text-center whitespace-nowrap min-w-[36px]"
                                    :title="d"
                                >
                                    {{ formatDateShort(d) }}
                                </th>
                                <th class="px-3 py-2.5 text-center border-l bg-muted/20 font-bold">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="student in filteredStudentRoster"
                                :key="student.enrollment_id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <td class="px-3 py-2 font-medium sticky left-0 bg-background z-10 border-r whitespace-nowrap">
                                    <div class="font-semibold text-foreground">{{ student.student_name }}</div>
                                    <div class="text-[10px] text-muted-foreground">{{ student.admission_number }} • {{ student.section_name }}</div>
                                </td>

                                <td
                                    v-for="d in recordedDates"
                                    :key="d"
                                    class="px-1 py-1 text-center"
                                >
                                    <span
                                        v-if="student.daily_statuses[d] === 'present'"
                                        class="inline-block w-6 h-6 leading-6 rounded bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 font-bold"
                                        title="Present"
                                    >P</span>
                                    <span
                                        v-else-if="student.daily_statuses[d] === 'late'"
                                        class="inline-block w-6 h-6 leading-6 rounded bg-amber-500/20 text-amber-700 dark:text-amber-400 font-bold"
                                        title="Late"
                                    >L</span>
                                    <span
                                        v-else-if="student.daily_statuses[d] === 'excused'"
                                        class="inline-block w-6 h-6 leading-6 rounded bg-blue-500/20 text-blue-700 dark:text-blue-400 font-bold"
                                        title="Excused"
                                    >E</span>
                                    <span
                                        v-else-if="student.daily_statuses[d] === 'absent'"
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
                                    'text-emerald-600': student.attendance_rate >= 90,
                                    'text-amber-600': student.attendance_rate >= 80 && student.attendance_rate < 90,
                                    'text-rose-600': student.attendance_rate < 80,
                                }">
                                    {{ student.attendance_rate }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Print-Only Institutional Sign-off -->
                <div class="hidden print:flex justify-between items-end mt-8 pt-4 border-t border-black text-[11px]">
                    <div>
                        <p class="font-semibold text-black">Form Teacher's Signature: _______________________</p>
                        <p class="text-[10px] text-gray-600 mt-1">Date: _______________________</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-black">Principal / Head Teacher: _______________________</p>
                        <p class="text-[10px] text-gray-600 mt-1">Official Institutional Seal & Date</p>
                    </div>
                </div>
            </Card>
        </div>
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
