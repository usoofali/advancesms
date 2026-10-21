<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    CalendarCheck,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    FileSpreadsheet,
    HelpCircle,
    Info,
    Printer,
    RotateCcw,
    Save,
    Search,
    Trash2,
    UserCheck,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
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
import {
    destroy as teacherAttendanceDestroy,
    index as teacherAttendanceIndex,
    report as teacherAttendanceReport,
    store as teacherAttendanceStore,
} from '@/routes/people/teachers/attendance';
import type { AcademicSession } from '@/types/academic';

interface DayMetrics {
    total: number;
    present: number;
    absent: number;
    late: number;
    excused: number;
    unrecorded: number;
    attendance_rate: number;
}

interface TeacherRosterItem {
    teacher_id: number;
    staff_id: string;
    name: string;
    email: string;
    gender: string | null;
    avatar: string | null;
    department_name: string;
    designation: string;
    qualification: string;
    attendance_id: number | null;
    status: 'present' | 'absent' | 'late' | 'excused' | null;
    remark: string;
}

interface Props {
    sessions: AcademicSession[];
    selectedSessionId: number;
    selectedTermId: number;
    selectedDate: string;
    isWeekend: boolean;
    roster: TeacherRosterItem[];
    metrics: DayMetrics;
    canRecord: boolean;
    canUpdate: boolean;
    canDelete: boolean;
}

const props = defineProps<Props>();

// State
const selectedSessionId = ref(props.selectedSessionId);
const selectedTermId = ref(props.selectedTermId);
const selectedDate = ref(props.selectedDate);
const searchQuery = ref('');
const isSubmitting = ref(false);
const isDeleting = ref(false);

// Local editable attendance state map
const editableAttendance = reactive<Record<number, { status: 'present' | 'absent' | 'late' | 'excused' | null; remark: string }>>({});

props.roster.forEach((item) => {
    editableAttendance[item.teacher_id] = {
        status: item.status,
        remark: item.remark || '',
    };
});

// Computed available terms for session
const activeSession = computed(() => {
    return props.sessions.find((s) => s.id === Number(selectedSessionId.value));
});

const availableTerms = computed(() => {
    return activeSession.value?.terms || [];
});

const activeTerm = computed(() => {
    return availableTerms.value.find((t) => t.id === Number(selectedTermId.value));
});

const printRegister = () => {
    window.print();
};

// Live metrics based on current edits
const liveMetrics = computed<DayMetrics>(() => {
    let present = 0;
    let absent = 0;
    let late = 0;
    let excused = 0;
    let unrecorded = 0;

    props.roster.forEach((t) => {
        const current = editableAttendance[t.teacher_id]?.status;
        if (current === 'present') {
            present++;
        } else if (current === 'absent') {
            absent++;
        } else if (current === 'late') {
            late++;
        } else if (current === 'excused') {
            excused++;
        } else {
            unrecorded++;
        }
    });

    const recorded = present + late + excused + absent;
    const rate = recorded > 0 ? roundOne(((present + late) / recorded) * 100) : 0;

    return {
        total: props.roster.length,
        present,
        absent,
        late,
        excused,
        unrecorded,
        attendance_rate: rate,
    };
});

const hasExistingRecords = computed(() => {
    return props.roster.some((item) => item.attendance_id !== null);
});

const hasUnsavedChanges = computed(() => {
    return props.roster.some((item) => {
        const edit = editableAttendance[item.teacher_id];
        return edit?.status !== item.status || (edit?.remark || '') !== (item.remark || '');
    });
});

const filteredRoster = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.roster;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.roster.filter(
        (t) =>
            t.name.toLowerCase().includes(q) ||
            t.staff_id.toLowerCase().includes(q) ||
            t.department_name.toLowerCase().includes(q) ||
            t.designation.toLowerCase().includes(q)
    );
});

// Helpers
function roundOne(num: number): number {
    return Math.round(num * 10) / 10;
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2);
}

// Navigation & Actions
const changeDate = (newDate: string) => {
    router.get(
        teacherAttendanceIndex(),
        {
            session_id: selectedSessionId.value,
            term_id: selectedTermId.value,
            attendance_date: newDate,
        },
        {
            preserveState: false,
            preserveScroll: true,
        }
    );
};

const goToPreviousWeekday = () => {
    const d = new Date(selectedDate.value);
    d.setDate(d.getDate() - 1);
    // If it falls on Sunday (0), shift to Friday (-2)
    if (d.getDay() === 0) {
        d.setDate(d.getDate() - 2);
    } else if (d.getDay() === 6) {
        // If Saturday (6), shift to Friday (-1)
        d.setDate(d.getDate() - 1);
    }
    changeDate(d.toISOString().substring(0, 10));
};

const goToNextWeekday = () => {
    const d = new Date(selectedDate.value);
    d.setDate(d.getDate() + 1);
    // If it falls on Saturday (6), shift to Monday (+2)
    if (d.getDay() === 6) {
        d.setDate(d.getDate() + 2);
    } else if (d.getDay() === 0) {
        // If Sunday (0), shift to Monday (+1)
        d.setDate(d.getDate() + 1);
    }
    changeDate(d.toISOString().substring(0, 10));
};

const onDateInputChange = () => {
    changeDate(selectedDate.value);
};

const onSessionChange = () => {
    const sess = props.sessions.find((s) => s.id === Number(selectedSessionId.value));
    if (sess && sess.terms && sess.terms.length > 0) {
        const curTerm = sess.terms.find((t) => t.is_current) || sess.terms[0];
        selectedTermId.value = curTerm.id;
    }
    changeDate(selectedDate.value);
};

// Batch Set Actions
const markAllPresent = () => {
    if (props.isWeekend) {
        toast.error('Cannot mark attendance on weekends.');
        return;
    }
    props.roster.forEach((t) => {
        if (!editableAttendance[t.teacher_id]) {
            editableAttendance[t.teacher_id] = { status: 'present', remark: '' };
        } else {
            editableAttendance[t.teacher_id].status = 'present';
        }
    });
    toast.success('All active teachers marked as Present.');
};

const clearAll = () => {
    props.roster.forEach((t) => {
        if (editableAttendance[t.teacher_id]) {
            editableAttendance[t.teacher_id].status = null;
        }
    });
    toast.info('Cleared status selections.');
};

// Submit Batch
const saveAttendance = () => {
    if (props.isWeekend) {
        toast.error('Teacher attendance can only be recorded on weekdays (Monday to Friday).');
        return;
    }

    // Collect all teachers that have a selected status
    const records = props.roster
        .filter((t) => editableAttendance[t.teacher_id]?.status !== null)
        .map((t) => ({
            teacher_id: t.teacher_id,
            status: editableAttendance[t.teacher_id].status,
            remark: editableAttendance[t.teacher_id].remark || null,
        }));

    if (records.length === 0) {
        toast.warning('Please mark attendance for at least one teacher before saving.');
        return;
    }

    isSubmitting.value = true;
    router.post(
        teacherAttendanceStore(),
        {
            attendance_date: selectedDate.value,
            academic_session_id: selectedSessionId.value,
            term_id: selectedTermId.value || null,
            records,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Teacher attendance saved successfully.');
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                toast.error(typeof first === 'string' ? first : 'Failed to save attendance.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
};

// Delete Daily Record
const deleteDailyRecord = () => {
    if (!confirm(`Are you sure you want to delete all teacher attendance records for ${selectedDate.value}?`)) {
        return;
    }

    isDeleting.value = true;
    router.delete(teacherAttendanceDestroy({ date: selectedDate.value }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Teacher attendance for ${selectedDate.value} deleted.`);
        },
        onError: () => {
            toast.error('Failed to delete attendance records.');
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Teacher Attendance Register" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8 print:p-0 print:space-y-3">
        <!-- Institutional Letterhead Print Header -->
        <PrintHeader
            title="Daily Staff Attendance Register"
            :subtitle="`Instructional Day Staff Roll Call & Duty Log • Date: ${selectedDate}`"
            :session-term="`${activeSession?.name || ''} - ${activeTerm?.name || ''}`"
            class-section="Teaching & Academic Staff"
        />

        <!-- Web Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl flex items-center gap-2.5">
                    <CalendarCheck class="h-7 w-7 text-emerald-600 dark:text-emerald-400" />
                    <span>Teacher Attendance Register</span>
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Daily weekday attendance register, status capture (Present, Late, Excused, Absent), and punctuality tracking.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    class="h-10 gap-2 text-sm"
                    as-child
                >
                    <a :href="teacherAttendanceReport().url">
                        <FileSpreadsheet class="h-4 w-4 text-indigo-500" />
                        <span>Performance Reports</span>
                    </a>
                </Button>

                <Button
                    variant="outline"
                    class="h-10 gap-2 text-sm"
                    @click="printRegister"
                >
                    <Printer class="h-4 w-4" />
                    <span>Print Register</span>
                </Button>

                <Button
                    v-if="hasExistingRecords && canDelete"
                    variant="outline"
                    class="h-10 gap-2 text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                    :disabled="isDeleting || isSubmitting"
                    @click="deleteDailyRecord"
                >
                    <Trash2 class="h-4 w-4" />
                    <span>Clear Day</span>
                </Button>

                <Button
                    v-if="canRecord || canUpdate"
                    class="h-10 gap-2 font-semibold shadow-sm bg-emerald-600 hover:bg-emerald-700 text-white"
                    :disabled="isSubmitting || isWeekend"
                    @click="saveAttendance"
                >
                    <Save class="h-4 w-4" />
                    <span>{{ isSubmitting ? 'Saving...' : 'Save Register' }}</span>
                </Button>
            </div>
        </div>

        <!-- Weekend Alert Banner (if weekend) -->
        <div
            v-if="isWeekend"
            class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-200 flex items-start gap-3 shadow-xs print:hidden"
        >
            <AlertCircle class="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div class="text-sm space-y-1">
                <div class="font-semibold text-amber-800 dark:text-amber-300">
                    Weekend Notice: Saturday / Sunday Selected
                </div>
                <div>
                    Teacher and staff attendance is only conducted on instructional weekdays (Monday to Friday). Registration is disabled for weekend dates. Please choose a weekday date to take or view attendance.
                </div>
            </div>
        </div>

        <!-- Filter & Date Controls Card -->
        <Card class="border shadow-sm print:hidden">
            <CardContent class="p-4 sm:p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
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
                            @change="onDateInputChange"
                        >
                            <option v-for="t in availableTerms" :key="t.id" :value="t.id">
                                {{ t.name }} {{ t.is_current ? '(Current)' : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Date Picker with Step Controls -->
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Attendance Date (Mon – Fri)
                        </Label>
                        <div class="flex items-center gap-1.5">
                            <Button
                                variant="outline"
                                size="icon"
                                class="h-10 w-10 shrink-0"
                                title="Previous Weekday"
                                @click="goToPreviousWeekday"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>

                            <Input
                                v-model="selectedDate"
                                type="date"
                                class="h-10 text-sm"
                                @change="onDateInputChange"
                            />

                            <Button
                                variant="outline"
                                size="icon"
                                class="h-10 w-10 shrink-0"
                                title="Next Weekday"
                                @click="goToNextWeekday"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 print:hidden">
            <!-- Total Staff -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Total Staff</span>
                        <Users class="h-4 w-4 text-blue-500" />
                    </div>
                    <div class="text-2xl font-bold text-foreground">
                        {{ liveMetrics.total }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">Active teachers</p>
                </CardContent>
            </Card>

            <!-- Present -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Present</span>
                        <UserCheck class="h-4 w-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ liveMetrics.present }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">On time</p>
                </CardContent>
            </Card>

            <!-- Late -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Late</span>
                        <Clock class="h-4 w-4 text-amber-500" />
                    </div>
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {{ liveMetrics.late }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">Late arrival</p>
                </CardContent>
            </Card>

            <!-- Excused -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Excused</span>
                        <Info class="h-4 w-4 text-blue-500" />
                    </div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ liveMetrics.excused }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">Official duty / leave</p>
                </CardContent>
            </Card>

            <!-- Absent -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Absent</span>
                        <XCircle class="h-4 w-4 text-rose-500" />
                    </div>
                    <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                        {{ liveMetrics.absent }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">Unexcused absence</p>
                </CardContent>
            </Card>

            <!-- Attendance Rate -->
            <Card class="border shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-medium uppercase tracking-wider">Rate</span>
                        <Badge variant="outline" class="text-[10px] px-1 py-0 font-normal">
                            {{ liveMetrics.unrecorded }} unrecorded
                        </Badge>
                    </div>
                    <div class="text-2xl font-bold" :class="{
                        'text-emerald-600 dark:text-emerald-400': liveMetrics.attendance_rate >= 90,
                        'text-amber-600 dark:text-amber-400': liveMetrics.attendance_rate >= 80 && liveMetrics.attendance_rate < 90,
                        'text-rose-600 dark:text-rose-400': liveMetrics.attendance_rate < 80,
                    }">
                        {{ liveMetrics.attendance_rate }}%
                    </div>
                    <div class="w-full bg-muted rounded-full h-1 mt-1.5 overflow-hidden">
                        <div
                            class="h-1 rounded-full bg-emerald-500 transition-all duration-300"
                            :style="{ width: `${Math.min(liveMetrics.attendance_rate, 100)}%` }"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Teacher Roster Table Card -->
        <Card class="border shadow-sm overflow-hidden print:border-none print:shadow-none">
            <CardHeader class="p-4 sm:p-6 border-b bg-muted/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 print:hidden">
                <div>
                    <CardTitle class="text-lg font-bold flex items-center gap-2">
                        <Users class="h-5 w-5 text-primary" />
                        <span>Teaching Staff Register</span>
                    </CardTitle>
                    <CardDescription class="text-xs sm:text-sm mt-0.5">
                        {{ filteredRoster.length }} active teachers listed for {{ selectedDate }}.
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-56">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search teacher name..."
                            class="h-9 pl-8 text-sm"
                        />
                    </div>

                    <!-- 1-Click Fast Actions -->
                    <div v-if="!isWeekend && (canRecord || canUpdate)" class="flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 text-xs gap-1.5 border-emerald-500/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/20"
                            @click="markAllPresent"
                        >
                            <Check class="h-3.5 w-3.5" />
                            <span>Mark All Present</span>
                        </Button>

                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-9 text-xs gap-1.5 text-muted-foreground"
                            @click="clearAll"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                            <span>Clear</span>
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-muted/40 text-xs uppercase font-semibold text-muted-foreground border-b">
                        <tr>
                            <th class="px-3 py-3 w-12 text-center">#</th>
                            <th class="px-4 py-3 min-w-[220px]">Teacher / Staff Details</th>
                            <th class="px-3 py-3 min-w-[130px]">Department</th>
                            <th class="px-4 py-3 min-w-[320px]">Attendance Status</th>
                            <th class="px-4 py-3 min-w-[220px]">Remark / Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="(teacher, index) in filteredRoster"
                            :key="teacher.teacher_id"
                            class="hover:bg-muted/20 transition-colors"
                            :class="{
                                'bg-emerald-50/25 dark:bg-emerald-950/10': editableAttendance[teacher.teacher_id]?.status === 'present',
                                'bg-amber-50/25 dark:bg-amber-950/10': editableAttendance[teacher.teacher_id]?.status === 'late',
                                'bg-blue-50/25 dark:bg-blue-950/10': editableAttendance[teacher.teacher_id]?.status === 'excused',
                                'bg-rose-50/25 dark:bg-rose-950/10': editableAttendance[teacher.teacher_id]?.status === 'absent',
                            }"
                        >
                            <!-- Index -->
                            <td class="px-3 py-3.5 text-center text-xs text-muted-foreground font-medium">
                                {{ index + 1 }}
                            </td>

                            <!-- Teacher Details -->
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

                            <!-- Department -->
                            <td class="px-3 py-3.5 text-xs text-muted-foreground">
                                <Badge variant="secondary" class="font-normal text-xs">
                                    {{ teacher.department_name }}
                                </Badge>
                            </td>

                            <!-- Status Segmented Control -->
                            <td class="px-4 py-3.5">
                                <!-- Print-only Status Label -->
                                <div class="hidden print:inline-block font-bold text-xs uppercase" :class="{
                                    'text-emerald-700': editableAttendance[teacher.teacher_id]?.status === 'present',
                                    'text-amber-700': editableAttendance[teacher.teacher_id]?.status === 'late',
                                    'text-blue-700': editableAttendance[teacher.teacher_id]?.status === 'excused',
                                    'text-rose-700': editableAttendance[teacher.teacher_id]?.status === 'absent',
                                    'text-gray-500 italic': !editableAttendance[teacher.teacher_id]?.status,
                                }">
                                    {{ editableAttendance[teacher.teacher_id]?.status || 'Unrecorded' }}
                                </div>

                                <div class="inline-flex rounded-lg border bg-muted/40 p-1 gap-1 print:hidden">
                                    <!-- Present -->
                                    <button
                                        type="button"
                                        :disabled="isWeekend || (!canRecord && !canUpdate)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all disabled:opacity-50"
                                        :class="editableAttendance[teacher.teacher_id]?.status === 'present'
                                            ? 'bg-emerald-600 text-white shadow-xs font-semibold'
                                            : 'text-muted-foreground hover:text-foreground hover:bg-background/80'"
                                        @click="editableAttendance[teacher.teacher_id].status = 'present'"
                                    >
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                        <span>Present</span>
                                    </button>

                                    <!-- Late -->
                                    <button
                                        type="button"
                                        :disabled="isWeekend || (!canRecord && !canUpdate)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all disabled:opacity-50"
                                        :class="editableAttendance[teacher.teacher_id]?.status === 'late'
                                            ? 'bg-amber-600 text-white shadow-xs font-semibold'
                                            : 'text-muted-foreground hover:text-foreground hover:bg-background/80'"
                                        @click="editableAttendance[teacher.teacher_id].status = 'late'"
                                    >
                                        <Clock class="h-3.5 w-3.5" />
                                        <span>Late</span>
                                    </button>

                                    <!-- Excused -->
                                    <button
                                        type="button"
                                        :disabled="isWeekend || (!canRecord && !canUpdate)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all disabled:opacity-50"
                                        :class="editableAttendance[teacher.teacher_id]?.status === 'excused'
                                            ? 'bg-blue-600 text-white shadow-xs font-semibold'
                                            : 'text-muted-foreground hover:text-foreground hover:bg-background/80'"
                                        @click="editableAttendance[teacher.teacher_id].status = 'excused'"
                                    >
                                        <Info class="h-3.5 w-3.5" />
                                        <span>Excused</span>
                                    </button>

                                    <!-- Absent -->
                                    <button
                                        type="button"
                                        :disabled="isWeekend || (!canRecord && !canUpdate)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all disabled:opacity-50"
                                        :class="editableAttendance[teacher.teacher_id]?.status === 'absent'
                                            ? 'bg-rose-600 text-white shadow-xs font-semibold'
                                            : 'text-muted-foreground hover:text-foreground hover:bg-background/80'"
                                        @click="editableAttendance[teacher.teacher_id].status = 'absent'"
                                    >
                                        <XCircle class="h-3.5 w-3.5" />
                                        <span>Absent</span>
                                    </button>
                                </div>
                            </td>

                            <!-- Remark -->
                            <td class="px-4 py-3.5">
                                <div class="hidden print:block text-xs">
                                    {{ editableAttendance[teacher.teacher_id]?.remark || '—' }}
                                </div>
                                <Input
                                    v-model="editableAttendance[teacher.teacher_id].remark"
                                    :disabled="isWeekend || (!canRecord && !canUpdate)"
                                    placeholder="Optional remark or reason..."
                                    class="h-8 text-xs bg-background/60 print:hidden"
                                />
                            </td>
                        </tr>

                        <tr v-if="filteredRoster.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                No active teachers matching your search query.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Print-Only Institutional Sign-off -->
            <div class="hidden print:flex justify-between items-end mt-8 pt-4 border-t border-black text-[11px] px-4 pb-4">
                <div>
                    <p class="font-semibold text-black">Timekeeper / Head of Department: _______________________</p>
                    <p class="text-[10px] text-gray-600 mt-1">Date: _______________________</p>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-black">Principal / Head Teacher Signature: _______________________</p>
                    <p class="text-[10px] text-gray-600 mt-1">Daily Log Certified & Archived</p>
                </div>
            </div>

            <!-- Bottom Floating Save Bar if changes -->
            <div
                v-if="hasUnsavedChanges && !isWeekend && (canRecord || canUpdate)"
                class="p-4 border-t bg-muted/20 flex items-center justify-between print:hidden"
            >
                <div class="text-xs text-amber-700 dark:text-amber-400 font-medium flex items-center gap-2">
                    <AlertCircle class="h-4 w-4" />
                    <span>You have unsaved attendance marks for {{ selectedDate }}.</span>
                </div>

                <Button
                    class="h-9 px-4 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm"
                    :disabled="isSubmitting"
                    @click="saveAttendance"
                >
                    <Save class="h-3.5 w-3.5" />
                    <span>Save Daily Attendance</span>
                </Button>
            </div>
        </Card>
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
