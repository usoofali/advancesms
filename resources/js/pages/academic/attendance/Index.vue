<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    CalendarCheck,
    Check,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    Clock,
    Filter,
    HelpCircle,
    Info,
    RotateCcw,
    Save,
    Search,
    ShieldAlert,
    UserCheck,
    Users,
    UserX,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index as academicAttendanceIndex, store as academicAttendanceStore } from '@/routes/academic/attendance';
import type { AcademicSession, AcademicTerm, SchoolClass, Section } from '@/types/academic';

type AttendanceStatusType = 'present' | 'absent' | 'late' | 'excused';

interface RosterStudent {
    enrollment_id: number;
    student_id: number;
    admission_number: string;
    student_name: string;
    gender: string;
    roll_number: string | null;
    attendance_id: number | null;
    status: AttendanceStatusType | null;
    remark: string;
}

interface Metrics {
    total: number;
    present: number;
    absent: number;
    late: number;
    excused: number;
    unrecorded: number;
    attendance_rate: number;
}

interface Props {
    sessions: AcademicSession[];
    classes: (SchoolClass & { sections: Section[] })[];
    selectedSessionId: number;
    selectedTermId: number;
    selectedClassId: number;
    selectedSectionId: number;
    selectedDate: string;
    roster: RosterStudent[];
    metrics: Metrics;
    attendanceStatuses: { value: AttendanceStatusType; label: string }[];
}

const props = defineProps<Props>();

// Reactive state
const selectedSessionId = ref(props.selectedSessionId);
const selectedTermId = ref(props.selectedTermId);
const selectedClassId = ref(props.selectedClassId);
const selectedSectionId = ref(props.selectedSectionId);
const selectedDate = ref(props.selectedDate);

const searchQuery = ref('');
const isSaving = ref(false);
const hasUnsavedChanges = ref(false);
const lastSavedTime = ref<string | null>(null);

// Local editable copy of roster
const localRoster = ref<RosterStudent[]>(
    props.roster.map((student) => ({
        ...student,
        status: student.status,
        remark: student.remark || '',
    }))
);

// Keep localRoster synced if props change from server visit
watch(
    () => props.roster,
    (newRoster) => {
        localRoster.value = newRoster.map((student) => ({
            ...student,
            status: student.status,
            remark: student.remark || '',
        }));
        hasUnsavedChanges.value = false;
    },
    { deep: true }
);

// Computed list of sections for current class
const availableSections = computed(() => {
    const cls = props.classes.find((c) => c.id === Number(selectedClassId.value));
    return cls?.sections || [];
});

// Computed active session and terms
const activeSession = computed(() => {
    return props.sessions.find((s) => s.id === Number(selectedSessionId.value));
});

const availableTerms = computed(() => {
    return activeSession.value?.terms || [];
});

// Live metrics derived from local state
const liveMetrics = computed<Metrics>(() => {
    const total = localRoster.value.length;
    let present = 0;
    let absent = 0;
    let late = 0;
    let excused = 0;
    let unrecorded = 0;

    for (const item of localRoster.value) {
        if (item.status === 'present') present++;
        else if (item.status === 'absent') absent++;
        else if (item.status === 'late') late++;
        else if (item.status === 'excused') excused++;
        else unrecorded++;
    }

    const attendanceRate = total > 0 ? Math.round(((present + late) / total) * 1000) / 10 : 0.0;

    return {
        total,
        present,
        absent,
        late,
        excused,
        unrecorded,
        attendance_rate: attendanceRate,
    };
});

// Filtered roster for search
const displayedStudents = computed(() => {
    if (!searchQuery.value.trim()) {
        return localRoster.value;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return localRoster.value.filter(
        (s) =>
            s.student_name.toLowerCase().includes(q) ||
            s.admission_number.toLowerCase().includes(q) ||
            (s.roll_number && s.roll_number.toLowerCase().includes(q))
    );
});

// Navigation & Filters
const applyFilters = () => {
    router.get(
        academicAttendanceIndex(),
        {
            session_id: selectedSessionId.value,
            term_id: selectedTermId.value,
            class_id: selectedClassId.value,
            section_id: selectedSectionId.value,
            attendance_date: selectedDate.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
};

const onClassChange = () => {
    const cls = props.classes.find((c) => c.id === Number(selectedClassId.value));
    if (cls && cls.sections.length > 0) {
        selectedSectionId.value = cls.sections[0].id;
    } else {
        selectedSectionId.value = 0;
    }
    applyFilters();
};

const onSessionChange = () => {
    const sess = props.sessions.find((s) => s.id === Number(selectedSessionId.value));
    if (sess && sess.terms && sess.terms.length > 0) {
        const curTerm = sess.terms.find((t) => t.is_current) || sess.terms[0];
        selectedTermId.value = curTerm.id;
    }
    applyFilters();
};

const shiftDate = (days: number) => {
    const current = new Date(selectedDate.value);
    current.setDate(current.getDate() + days);
    selectedDate.value = current.toISOString().split('T')[0];
    applyFilters();
};

const setToday = () => {
    selectedDate.value = new Date().toISOString().split('T')[0];
    applyFilters();
};

// Attendance marking actions
const setStatus = (student: RosterStudent, status: AttendanceStatusType) => {
    student.status = status;
    hasUnsavedChanges.value = true;
};

const markAllPresent = () => {
    for (const student of localRoster.value) {
        student.status = 'present';
    }
    hasUnsavedChanges.value = true;
    toast.info('All students marked as Present. Remember to save.');
};

const resetUnrecorded = () => {
    for (const student of localRoster.value) {
        student.status = null;
        student.remark = '';
    }
    hasUnsavedChanges.value = true;
    toast.info('Attendance statuses reset.');
};

// Save submission
const saveAttendance = () => {
    if (localRoster.value.length === 0) {
        toast.warning('No student enrollments to save.');
        return;
    }

    // Default any remaining unrecorded students to 'present' or confirm
    const unrecordedCount = localRoster.value.filter((s) => !s.status).length;
    if (unrecordedCount > 0) {
        // Auto-assign remaining as present
        for (const s of localRoster.value) {
            if (!s.status) {
                s.status = 'present';
            }
        }
    }

    isSaving.value = true;

    const payload = {
        attendance_date: selectedDate.value,
        class_id: Number(selectedClassId.value),
        section_id: Number(selectedSectionId.value),
        records: localRoster.value.map((s) => ({
            enrollment_id: s.enrollment_id,
            status: s.status || 'present',
            remark: s.remark.trim() || null,
        })),
    };

    router.post(academicAttendanceStore(), payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isSaving.value = false;
            hasUnsavedChanges.value = false;
            lastSavedTime.value = new Date().toLocaleTimeString();
            toast.success('Daily attendance register saved successfully.');
        },
        onError: () => {
            isSaving.value = false;
            toast.error('Failed to save attendance register. Please check entries.');
        },
    });
};

// Keyboard shortcut (Ctrl+S or Cmd+S to save)
const handleGlobalKeyDown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        saveAttendance();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeyDown);
});
</script>

<template>
    <Head title="Daily Attendance Register" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl flex items-center gap-2.5">
                    <CalendarCheck class="h-7 w-7 text-primary" />
                    <span>Daily Attendance</span>
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Class and section attendance roster.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-2 w-full sm:w-auto">
                <Button
                    variant="outline"
                    class="h-10 w-full sm:w-auto gap-2 border-dashed"
                    :disabled="displayedStudents.length === 0"
                    @click="markAllPresent"
                >
                    <CheckCheck class="h-4 w-4 text-emerald-600" />
                    <span>Mark All Present</span>
                </Button>

                <Button
                    class="h-10 w-full sm:w-auto gap-2 font-semibold shadow-sm"
                    :disabled="isSaving || displayedStudents.length === 0"
                    @click="saveAttendance"
                >
                    <Spinner v-if="isSaving" class="h-4 w-4" />
                    <Save v-else class="h-4 w-4" />
                    <span>{{ isSaving ? 'Saving...' : 'Save Register' }}</span>
                </Button>
            </div>
        </div>

        <!-- Filter Controls Bar -->
        <Card class="border shadow-sm">
            <CardContent class="p-4 sm:p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <!-- Session Selector -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Academic Session</Label>
                        <select
                            v-model="selectedSessionId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="onSessionChange"
                        >
                            <option
                                v-for="sess in sessions"
                                :key="sess.id"
                                :value="sess.id"
                            >
                                {{ sess.name }} {{ sess.is_current ? '(Active)' : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Class Selector -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Class</Label>
                        <select
                            v-model="selectedClassId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="onClassChange"
                        >
                            <option
                                v-for="cls in classes"
                                :key="cls.id"
                                :value="cls.id"
                            >
                                {{ cls.name }} {{ cls.stage ? `(${cls.stage})` : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Section Selector -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Section</Label>
                        <select
                            v-model="selectedSectionId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="applyFilters"
                        >
                            <option
                                v-for="sec in availableSections"
                                :key="sec.id"
                                :value="sec.id"
                            >
                                Section {{ sec.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Date Selection with Quick Navigation -->
                    <div class="space-y-1.5 lg:col-span-2">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Attendance Date</Label>
                        <div class="flex items-center gap-1.5">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-10 w-10 shrink-0"
                                title="Previous Day"
                                @click="shiftDate(-1)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>

                            <Input
                                v-model="selectedDate"
                                type="date"
                                class="h-10 text-sm"
                                @change="applyFilters"
                            />

                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-10 w-10 shrink-0"
                                title="Next Day"
                                @click="shiftDate(1)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>

                            <Button
                                type="button"
                                variant="secondary"
                                class="h-10 px-3 text-xs font-medium shrink-0"
                                @click="setToday"
                            >
                                Today
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Unsaved Changes Alert & Save Indicator -->
                <div
                    v-if="hasUnsavedChanges || lastSavedTime"
                    class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-xs"
                >
                    <div v-if="hasUnsavedChanges" class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400 font-medium animate-pulse">
                        <AlertCircle class="h-3.5 w-3.5" />
                        <span>You have unsaved attendance modifications. Press Ctrl+S or click "Save Register".</span>
                    </div>
                    <div v-else-if="lastSavedTime" class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <Check class="h-3.5 w-3.5" />
                        <span>Attendance synchronized (Last saved at {{ lastSavedTime }}).</span>
                    </div>

                    <div class="text-muted-foreground flex items-center gap-1">
                        <Clock class="h-3 w-3" />
                        <span>Shortcut: <kbd class="px-1.5 py-0.5 rounded bg-muted text-[10px] border">Ctrl</kbd> + <kbd class="px-1.5 py-0.5 rounded bg-muted text-[10px] border">S</kbd></span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Attendance Metrics Strip -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <!-- Total Enrolled -->
            <Card class="border shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Total Enrolled</p>
                        <p class="text-xl font-bold tracking-tight text-foreground">{{ liveMetrics.total }}</p>
                    </div>
                    <div class="rounded-full bg-primary/10 p-2 text-primary">
                        <Users class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>

            <!-- Present -->
            <Card class="border border-emerald-500/20 bg-emerald-50/30 dark:bg-emerald-950/20 shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Present</p>
                        <p class="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ liveMetrics.present }}</p>
                    </div>
                    <div class="rounded-full bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                        <UserCheck class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>

            <!-- Late -->
            <Card class="border border-amber-500/20 bg-amber-50/30 dark:bg-amber-950/20 shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-amber-700 dark:text-amber-300">Late</p>
                        <p class="text-xl font-bold tracking-tight text-amber-600 dark:text-amber-400">{{ liveMetrics.late }}</p>
                    </div>
                    <div class="rounded-full bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                        <Clock class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>

            <!-- Absent -->
            <Card class="border border-rose-500/20 bg-rose-50/30 dark:bg-rose-950/20 shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-rose-700 dark:text-rose-300">Absent</p>
                        <p class="text-xl font-bold tracking-tight text-rose-600 dark:text-rose-400">{{ liveMetrics.absent }}</p>
                    </div>
                    <div class="rounded-full bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                        <UserX class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>

            <!-- Excused -->
            <Card class="border border-sky-500/20 bg-sky-50/30 dark:bg-sky-950/20 shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-sky-700 dark:text-sky-300">Excused</p>
                        <p class="text-xl font-bold tracking-tight text-sky-600 dark:text-sky-400">{{ liveMetrics.excused }}</p>
                    </div>
                    <div class="rounded-full bg-sky-500/10 p-2 text-sky-600 dark:text-sky-400">
                        <ShieldAlert class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>

            <!-- Attendance Rate -->
            <Card class="border shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Attendance Rate</p>
                        <p class="text-xl font-bold tracking-tight" :class="liveMetrics.attendance_rate >= 80 ? 'text-emerald-600 dark:text-emerald-400' : liveMetrics.attendance_rate >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400'">
                            {{ liveMetrics.attendance_rate }}%
                        </p>
                    </div>
                    <div class="rounded-full bg-muted p-2 text-muted-foreground">
                        <Calendar class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Student Attendance Sheet -->
        <Card class="border shadow-sm">
            <CardHeader class="p-4 sm:p-6 pb-2 sm:pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b">
                <div>
                    <CardTitle class="text-base sm:text-lg flex items-center gap-2">
                        <span>Class Roster</span>
                        <Badge variant="secondary" class="font-normal text-xs">
                            {{ displayedStudents.length }} {{ displayedStudents.length === 1 ? 'Student' : 'Students' }}
                        </Badge>
                        <Badge v-if="liveMetrics.unrecorded > 0" variant="outline" class="text-amber-600 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30 text-xs">
                            {{ liveMetrics.unrecorded }} Unrecorded
                        </Badge>
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">
                        Toggle attendance status for each enrolled student on {{ selectedDate }}.
                    </CardDescription>
                </div>

                <div class="w-full sm:w-72">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Filter by name or admission..."
                            class="pl-9 h-9 text-xs"
                        />
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <!-- Empty State -->
                <div
                    v-if="displayedStudents.length === 0"
                    class="flex flex-col items-center justify-center p-12 text-center"
                >
                    <div class="rounded-full bg-muted p-4 mb-3">
                        <Users class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <h3 class="text-base font-semibold text-foreground">No students found</h3>
                    <p class="text-xs text-muted-foreground max-w-sm mt-1">
                        {{
                            searchQuery
                                ? 'No student matches your search query in this section.'
                                : 'There are currently no active student enrollments for this class and section in the selected academic session.'
                        }}
                    </p>
                </div>

                <!-- Attendance Table -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4 min-w-[200px]">Student Details</th>
                                <th class="py-3 px-4 min-w-[280px]">Attendance Status</th>
                                <th class="py-3 px-4 min-w-[220px]">Remarks / Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="(student, index) in displayedStudents"
                                :key="student.enrollment_id"
                                class="transition-colors hover:bg-muted/20"
                                :class="{
                                    'bg-emerald-50/20 dark:bg-emerald-950/10': student.status === 'present',
                                    'bg-rose-50/20 dark:bg-rose-950/10': student.status === 'absent',
                                    'bg-amber-50/20 dark:bg-amber-950/10': student.status === 'late',
                                    'bg-sky-50/20 dark:bg-sky-950/10': student.status === 'excused',
                                }"
                            >
                                <td class="py-3 px-4 text-center text-xs font-medium text-muted-foreground">
                                    {{ index + 1 }}
                                </td>

                                <!-- Student Details -->
                                <td class="py-3 px-4">
                                    <div class="font-medium text-foreground text-sm">
                                        {{ student.student_name }}
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5 text-xs text-muted-foreground">
                                        <span class="font-mono">{{ student.admission_number }}</span>
                                        <span class="capitalize text-[11px] px-1.5 py-0.2 rounded bg-muted font-normal">
                                            {{ student.gender }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Attendance Status Segmented Buttons -->
                                <td class="py-3 px-4">
                                    <div class="inline-flex rounded-lg border bg-muted/50 p-1 gap-1">
                                        <!-- Present Button -->
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all"
                                            :class="
                                                student.status === 'present'
                                                    ? 'bg-emerald-600 text-white shadow-xs font-semibold'
                                                    : 'text-muted-foreground hover:text-foreground hover:bg-background/80'
                                            "
                                            @click="setStatus(student, 'present')"
                                        >
                                            <Check class="h-3.5 w-3.5" />
                                            <span>Present</span>
                                        </button>

                                        <!-- Late Button -->
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all"
                                            :class="
                                                student.status === 'late'
                                                    ? 'bg-amber-500 text-white shadow-xs font-semibold'
                                                    : 'text-muted-foreground hover:text-foreground hover:bg-background/80'
                                            "
                                            @click="setStatus(student, 'late')"
                                        >
                                            <Clock class="h-3.5 w-3.5" />
                                            <span>Late</span>
                                        </button>

                                        <!-- Absent Button -->
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all"
                                            :class="
                                                student.status === 'absent'
                                                    ? 'bg-rose-600 text-white shadow-xs font-semibold'
                                                    : 'text-muted-foreground hover:text-foreground hover:bg-background/80'
                                            "
                                            @click="setStatus(student, 'absent')"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                            <span>Absent</span>
                                        </button>

                                        <!-- Excused Button -->
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all"
                                            :class="
                                                student.status === 'excused'
                                                    ? 'bg-sky-600 text-white shadow-xs font-semibold'
                                                    : 'text-muted-foreground hover:text-foreground hover:bg-background/80'
                                            "
                                            @click="setStatus(student, 'excused')"
                                        >
                                            <ShieldAlert class="h-3.5 w-3.5" />
                                            <span>Excused</span>
                                        </button>
                                    </div>
                                </td>

                                <!-- Remarks / Notes -->
                                <td class="py-3 px-4">
                                    <Input
                                        v-model="student.remark"
                                        type="text"
                                        placeholder="Optional remark..."
                                        class="h-8 text-xs bg-background/80"
                                        @input="hasUnsavedChanges = true"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary Bar -->
                <div
                    v-if="displayedStudents.length > 0"
                    class="border-t p-4 sm:px-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-muted/10 text-xs text-muted-foreground"
                >
                    <div class="flex items-center gap-2">
                        <span>Showing {{ displayedStudents.length }} of {{ localRoster.length }} students.</span>
                        <span v-if="liveMetrics.unrecorded > 0" class="font-medium text-amber-600 dark:text-amber-400">
                            ({{ liveMetrics.unrecorded }} unrecorded students will default to 'Present' upon saving).
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs gap-1.5"
                            :disabled="isSaving"
                            @click="resetUnrecorded"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                            <span>Reset Sheet</span>
                        </Button>

                        <Button
                            size="sm"
                            class="h-8 text-xs gap-1.5 font-semibold"
                            :disabled="isSaving"
                            @click="saveAttendance"
                        >
                            <Spinner v-if="isSaving" class="h-3.5 w-3.5" />
                            <Save v-else class="h-3.5 w-3.5" />
                            <span>Save Attendance</span>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
