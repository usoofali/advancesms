<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    Award,
    BookOpen,
    Calendar,
    CalendarCheck,
    Check,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    HeartHandshake,
    PenTool,
    Printer,
    TrendingUp,
    UserCheck,
    Users,
    UserX,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as academicAllocations } from '@/routes/academic/allocations';
import { index as academicAttendance } from '@/routes/academic/attendance';
import { index as academicBroadsheet } from '@/routes/academic/broadsheet';
import { index as academicMarks } from '@/routes/academic/marks';
import { index as academicReportCards, show as academicReportCardsShow } from '@/routes/academic/report-cards';

interface StudentMarkRow {
    subject_name: string;
    subject_code: string;
    ca1: number;
    ca2: number;
    ca3: number;
    exam_score: number;
    total_score: number;
    grade: string;
    grade_point: number;
    remark: string;
}

interface AttendanceStats {
    total_school_days: number;
    days_present: number;
    days_late: number;
    days_absent: number;
    days_excused: number;
    attendance_rate: number;
}

interface Props {
    role_view: 'student' | 'guardian' | 'admin';
    // Student props
    student_profile?: {
        enrollment_id: number;
        admission_number: string;
        student_name: string;
        class_name: string;
        section_name: string;
        session_name: string;
        roll_number: string | null;
    } | null;
    term_name?: string;
    marks?: StudentMarkRow[];
    total_score?: number;
    terminal_average?: number;
    attendance?: AttendanceStats;
    recent_attendances?: { date: string; status: string; remark: string | null }[];
    // Guardian props
    wards?: { id: number; name: string; admission_number: string; class_name: string; section_name: string }[];
    selected_ward_id?: number;
    active_ward?: {
        ward_id: number;
        enrollment_id: number | null;
        name: string;
        admission_number: string;
        class_name: string;
        section_name: string;
        session_name: string;
        marks: StudentMarkRow[];
        total_score: number;
        terminal_average: number;
        attendance: AttendanceStats;
    } | null;
    // Admin props
    stats?: {
        total_students: number;
        total_teachers: number;
        total_classes: number;
        total_sections: number;
        active_session: string;
        active_term: string;
        present_today: number;
        absent_today: number;
        late_today: number;
        total_today_recorded: number;
    };
    recent_enrollments?: {
        id: number;
        student_name: string;
        gender: string;
        class_name: string;
        section_name: string;
        date: string;
    }[];
}

const props = defineProps<Props>();

const selectWard = (wardId: number) => {
    router.get(
        '/dashboard',
        { ward_id: wardId },
        { preserveState: true, preserveScroll: true }
    );
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- ========================================== -->
        <!-- 1. STUDENT DASHBOARD VIEW                  -->
        <!-- ========================================== -->
        <template v-if="role_view === 'student'">
            <!-- Student Header Banner -->
            <div
                v-if="student_profile"
                class="rounded-xl border bg-gradient-to-r from-primary/10 via-primary/5 to-background p-6 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Badge variant="outline" class="text-xs uppercase tracking-wider font-semibold border-primary/30 text-primary">
                            Student Portal
                        </Badge>
                        <span class="text-xs text-muted-foreground">{{ student_profile.session_name }}</span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Welcome, {{ student_profile.student_name }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3">
                        <span class="font-medium text-foreground">{{ student_profile.class_name }} ({{ student_profile.section_name }})</span>
                        <span>•</span>
                        <span class="font-mono">Adm: {{ student_profile.admission_number }}</span>
                    </p>
                </div>

                <div v-if="student_profile.enrollment_id">
                    <Link
                        :href="academicReportCardsShow(student_profile.enrollment_id)"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-primary text-primary-foreground hover:bg-primary/90 transition-all shadow-sm"
                    >
                        <Printer class="h-4 w-4" />
                        <span>Print Official Report Card</span>
                    </Link>
                </div>
            </div>

            <!-- Student Summary Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Card class="border shadow-xs">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Terminal Average</p>
                            <p class="text-xl font-bold tracking-tight" :class="(terminal_average || 0) >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                {{ terminal_average || 0 }}%
                            </p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-2 text-primary">
                            <TrendingUp class="h-4 w-4" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-xs">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Total Score</p>
                            <p class="text-xl font-bold tracking-tight text-foreground font-mono">
                                {{ total_score || 0 }}
                            </p>
                        </div>
                        <div class="rounded-full bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                            <Award class="h-4 w-4" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-xs">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Attendance Rate</p>
                            <p class="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 font-mono">
                                {{ attendance?.attendance_rate || 0 }}%
                            </p>
                        </div>
                        <div class="rounded-full bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                            <CalendarCheck class="h-4 w-4" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-xs">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Subjects Evaluated</p>
                            <p class="text-xl font-bold tracking-tight text-foreground">
                                {{ marks?.length || 0 }}
                            </p>
                        </div>
                        <div class="rounded-full bg-muted p-2 text-muted-foreground">
                            <BookOpen class="h-4 w-4" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Subject Assessment Breakdown -->
            <Card class="border shadow-sm">
                <CardHeader class="p-4 sm:p-6 pb-2 border-b flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base sm:text-lg flex items-center gap-2">
                            <span>Results</span>
                            <Badge variant="secondary" class="font-normal text-xs">{{ term_name }}</Badge>
                        </CardTitle>
                        <CardDescription class="text-xs text-muted-foreground">
                            Subject scores and grade determinations for the active academic term.
                        </CardDescription>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="!marks || marks.length === 0" class="p-8 text-center text-xs text-muted-foreground italic">
                        No examination results recorded for this term yet.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b bg-muted/40 font-semibold uppercase tracking-wider text-muted-foreground text-[11px]">
                                    <th class="py-2.5 px-4">Subject</th>
                                    <th class="py-2.5 px-2 text-center w-12">CA 1</th>
                                    <th class="py-2.5 px-2 text-center w-12">CA 2</th>
                                    <th class="py-2.5 px-2 text-center w-12">CA 3</th>
                                    <th class="py-2.5 px-2 text-center w-14">Exam</th>
                                    <th class="py-2.5 px-3 text-center w-16 bg-muted/20 font-bold">Total</th>
                                    <th class="py-2.5 px-2 text-center w-12 bg-muted/20">Grade</th>
                                    <th class="py-2.5 px-4">Remark</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="m in marks" :key="m.subject_code" class="hover:bg-muted/10">
                                    <td class="py-2.5 px-4 font-semibold text-foreground">{{ m.subject_name }}</td>
                                    <td class="py-2.5 px-2 text-center font-mono text-muted-foreground">{{ m.ca1 ?? '-' }}</td>
                                    <td class="py-2.5 px-2 text-center font-mono text-muted-foreground">{{ m.ca2 ?? '-' }}</td>
                                    <td class="py-2.5 px-2 text-center font-mono text-muted-foreground">{{ m.ca3 ?? '-' }}</td>
                                    <td class="py-2.5 px-2 text-center font-mono text-muted-foreground">{{ m.exam_score ?? '-' }}</td>
                                    <td class="py-2.5 px-3 text-center font-mono font-bold bg-muted/10 text-foreground" :class="m.total_score < 50 ? 'text-rose-600' : ''">
                                        {{ m.total_score }}
                                    </td>
                                    <td class="py-2.5 px-2 text-center font-bold bg-muted/10">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold"
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': ['A', 'B'].includes(m.grade),
                                                'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300': m.grade === 'C',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': ['D', 'E'].includes(m.grade),
                                                'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': m.grade === 'F',
                                            }"
                                        >
                                            {{ m.grade }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 text-muted-foreground">{{ m.remark }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- ========================================== -->
        <!-- 2. GUARDIAN / PARENT DASHBOARD VIEW        -->
        <!-- ========================================== -->
        <template v-else-if="role_view === 'guardian'">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl flex items-center gap-2">
                        <HeartHandshake class="h-7 w-7 text-primary" />
                        <span>Parent / Guardian Portal</span>
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Monitor your ward's continuous assessment results, academic standing, and attendance.
                    </p>
                </div>

                <!-- Ward Switcher Pills -->
                <div v-if="wards && wards.length > 1" class="flex flex-wrap gap-1.5 p-1 bg-muted rounded-lg border">
                    <button
                        v-for="ward in wards"
                        :key="ward.id"
                        type="button"
                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-all"
                        :class="ward.id === selected_ward_id ? 'bg-background shadow-xs text-foreground font-bold' : 'text-muted-foreground hover:text-foreground'"
                        @click="selectWard(ward.id)"
                    >
                        {{ ward.name }}
                    </button>
                </div>
            </div>

            <!-- Active Ward Profile Card -->
            <div v-if="active_ward" class="space-y-6">
                <Card class="border shadow-sm bg-gradient-to-r from-primary/5 via-card to-card">
                    <CardContent class="p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-primary">Ward Academic Profile</span>
                            <h2 class="text-xl font-bold text-foreground mt-0.5">{{ active_ward.name }}</h2>
                            <p class="text-xs text-muted-foreground mt-1 flex flex-wrap gap-x-3">
                                <span>Class: <b class="text-foreground">{{ active_ward.class_name }} ({{ active_ward.section_name }})</b></span>
                                <span>•</span>
                                <span>Admission No: <b class="text-foreground font-mono">{{ active_ward.admission_number }}</b></span>
                            </p>
                        </div>

                        <div v-if="active_ward.enrollment_id">
                            <Link
                                :href="academicReportCardsShow(active_ward.enrollment_id)"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-primary text-primary-foreground hover:bg-primary/90 transition-all shadow-sm"
                            >
                                <Printer class="h-4 w-4" />
                                <span>View Official Report Card</span>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <!-- Ward Performance Stats -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card class="border shadow-xs">
                        <CardContent class="p-4">
                            <p class="text-xs font-medium text-muted-foreground">Terminal Average</p>
                            <p class="text-xl font-bold font-mono" :class="active_ward.terminal_average >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                {{ active_ward.terminal_average }}%
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="border shadow-xs">
                        <CardContent class="p-4">
                            <p class="text-xs font-medium text-muted-foreground">Total Aggregate</p>
                            <p class="text-xl font-bold text-foreground font-mono">{{ active_ward.total_score }}</p>
                        </CardContent>
                    </Card>

                    <Card class="border shadow-xs">
                        <CardContent class="p-4">
                            <p class="text-xs font-medium text-muted-foreground">Attendance Rate</p>
                            <p class="text-xl font-bold font-mono text-emerald-600">{{ active_ward.attendance.attendance_rate }}%</p>
                        </CardContent>
                    </Card>

                    <Card class="border shadow-xs">
                        <CardContent class="p-4">
                            <p class="text-xs font-medium text-muted-foreground">Subjects Evaluated</p>
                            <p class="text-xl font-bold text-foreground">{{ active_ward.marks.length }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Ward Marks Table -->
                <Card class="border shadow-sm">
                    <CardHeader class="p-4 sm:p-6 pb-2 border-b">
                        <CardTitle class="text-base sm:text-lg">Terminal Examination Results</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="active_ward.marks.length === 0" class="p-8 text-center text-xs text-muted-foreground italic">
                            No marks recorded for this student yet.
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b bg-muted/40 font-semibold uppercase tracking-wider text-muted-foreground text-[11px]">
                                        <th class="py-2.5 px-4">Subject</th>
                                        <th class="py-2.5 px-2 text-center">CA 1</th>
                                        <th class="py-2.5 px-2 text-center">CA 2</th>
                                        <th class="py-2.5 px-2 text-center">CA 3</th>
                                        <th class="py-2.5 px-2 text-center">Exam</th>
                                        <th class="py-2.5 px-3 text-center font-bold bg-muted/20">Total</th>
                                        <th class="py-2.5 px-2 text-center bg-muted/20">Grade</th>
                                        <th class="py-2.5 px-4">Remark</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="m in active_ward.marks" :key="m.subject_code" class="hover:bg-muted/10">
                                        <td class="py-2.5 px-4 font-semibold text-foreground">{{ m.subject_name }}</td>
                                        <td class="py-2.5 px-2 text-center font-mono text-muted-foreground">{{ m.ca1 ?? '-' }}</td>
                                        <td class="py-2.5 px-2 text-center font-mono text-muted-foreground">{{ m.ca2 ?? '-' }}</td>
                                        <td class="py-2.5 px-2 text-center font-mono text-muted-foreground">{{ m.ca3 ?? '-' }}</td>
                                        <td class="py-2.5 px-2 text-center font-mono text-muted-foreground">{{ m.exam_score ?? '-' }}</td>
                                        <td class="py-2.5 px-3 text-center font-mono font-bold bg-muted/10 text-foreground">{{ m.total_score }}</td>
                                        <td class="py-2.5 px-2 text-center font-bold bg-muted/10">{{ m.grade }}</td>
                                        <td class="py-2.5 px-4 text-muted-foreground">{{ m.remark }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- ========================================== -->
        <!-- 3. ADMINISTRATIVE / STAFF DASHBOARD VIEW   -->
        <!-- ========================================== -->
        <template v-else>
            <!-- Header -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Executive Overview
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Institutional management dashboard for AdvanceSMS School Management System.
                    </p>
                </div>

                <div v-if="stats" class="flex items-center gap-2">
                    <Badge variant="outline" class="text-xs border-primary/30 text-primary py-1 px-2.5">
                        {{ stats.active_session }}
                    </Badge>
                    <Badge variant="secondary" class="text-xs py-1 px-2.5">
                        {{ stats.active_term }}
                    </Badge>
                </div>
            </div>

            <!-- Key Metric Cards -->
            <div v-if="stats" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Card class="border shadow-xs">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Enrolled Students</p>
                            <p class="text-2xl font-bold tracking-tight text-foreground">{{ stats.total_students }}</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-2.5 text-primary">
                            <Users class="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-xs">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Academic Staff</p>
                            <p class="text-2xl font-bold tracking-tight text-foreground">{{ stats.total_teachers }}</p>
                        </div>
                        <div class="rounded-full bg-emerald-500/10 p-2.5 text-emerald-600 dark:text-emerald-400">
                            <UserCheck class="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-xs">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Classes</p>
                            <p class="text-2xl font-bold tracking-tight text-foreground">{{ stats.total_classes }}</p>
                        </div>
                        <div class="rounded-full bg-amber-500/10 p-2.5 text-amber-600 dark:text-amber-400">
                            <GraduationCap class="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-xs">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Sections</p>
                            <p class="text-2xl font-bold tracking-tight text-foreground">{{ stats.total_sections }}</p>
                        </div>
                        <div class="rounded-full bg-sky-500/10 p-2.5 text-sky-600 dark:text-sky-400">
                            <BookOpen class="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Action Shortcuts -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <Link
                    :href="academicMarks()"
                    class="group block p-4 rounded-xl border bg-card hover:border-primary/50 hover:shadow-md transition-all"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary group-hover:scale-105 transition-transform">
                            <PenTool class="h-5 w-5" />
                        </div>
                        <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:text-primary transition-colors" />
                    </div>
                    <h3 class="font-semibold text-sm text-foreground">Assessment Mark Entry</h3>
                    <p class="text-xs text-muted-foreground mt-1">Excel-style spreadsheet mark entry grid for teachers.</p>
                </Link>

                <Link
                    :href="academicAttendance()"
                    class="group block p-4 rounded-xl border bg-card hover:border-primary/50 hover:shadow-md transition-all"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 group-hover:scale-105 transition-transform">
                            <CalendarCheck class="h-5 w-5" />
                        </div>
                        <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:text-emerald-600 transition-colors" />
                    </div>
                    <h3 class="font-semibold text-sm text-foreground">Daily Attendance Register</h3>
                    <p class="text-xs text-muted-foreground mt-1">Record class section attendance with rapid status toggles.</p>
                </Link>

                <Link
                    :href="academicBroadsheet()"
                    class="group block p-4 rounded-xl border bg-card hover:border-primary/50 hover:shadow-md transition-all"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="rounded-lg bg-amber-500/10 p-2 text-amber-600 group-hover:scale-105 transition-transform">
                            <FileSpreadsheet class="h-5 w-5" />
                        </div>
                        <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:text-amber-600 transition-colors" />
                    </div>
                    <h3 class="font-semibold text-sm text-foreground">Result Broadsheet</h3>
                    <p class="text-xs text-muted-foreground mt-1">Compile class-wide exam matrices, averages, and rankings.</p>
                </Link>

                <Link
                    :href="academicReportCards()"
                    class="group block p-4 rounded-xl border bg-card hover:border-primary/50 hover:shadow-md transition-all"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="rounded-lg bg-purple-500/10 p-2 text-purple-600 group-hover:scale-105 transition-transform">
                            <FileText class="h-5 w-5" />
                        </div>
                        <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:text-purple-600 transition-colors" />
                    </div>
                    <h3 class="font-semibold text-sm text-foreground">Terminal Report Cards</h3>
                    <p class="text-xs text-muted-foreground mt-1">Print official institutional report cards with stamp.</p>
                </Link>
            </div>

            <!-- Recent Activity & Attendance Snapshot -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Today's Attendance Snapshot -->
                <Card v-if="stats" class="border shadow-sm">
                    <CardHeader class="p-4 sm:p-6 pb-2 border-b">
                        <CardTitle class="text-sm sm:text-base flex items-center justify-between">
                            <span>Today's Attendance</span>
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-4 sm:p-6 space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground">Present</span>
                            <span class="font-bold text-emerald-600 font-mono">{{ stats.present_today }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground">Late</span>
                            <span class="font-bold text-amber-600 font-mono">{{ stats.late_today }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground">Absent</span>
                            <span class="font-bold text-rose-600 font-mono">{{ stats.absent_today }}</span>
                        </div>
                        <div class="border-t pt-2 flex items-center justify-between text-xs font-semibold">
                            <span>Total Recorded Today</span>
                            <span class="font-mono text-foreground">{{ stats.total_today_recorded }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Enrollments Table -->
                <Card class="border shadow-sm lg:col-span-2">
                    <CardHeader class="p-4 sm:p-6 pb-2 border-b">
                        <CardTitle class="text-sm sm:text-base flex items-center justify-between">
                            <span>Recent Student Enrollments</span>
                            <Users class="h-4 w-4 text-muted-foreground" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="!recent_enrollments || recent_enrollments.length === 0" class="p-6 text-center text-xs text-muted-foreground italic">
                            No student enrollments recorded yet.
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b bg-muted/30 font-semibold uppercase tracking-wider text-muted-foreground text-[10px]">
                                        <th class="py-2.5 px-4">Student</th>
                                        <th class="py-2.5 px-3">Class & Section</th>
                                        <th class="py-2.5 px-3 text-right">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="r in recent_enrollments" :key="r.id" class="hover:bg-muted/10">
                                        <td class="py-2.5 px-4">
                                            <div class="font-semibold text-foreground">{{ r.student_name }}</div>
                                            <div class="capitalize text-[10px] text-muted-foreground">{{ r.gender }}</div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="font-medium text-foreground">{{ r.class_name }}</span>
                                            <span class="text-muted-foreground ml-1">({{ r.section_name }})</span>
                                        </td>
                                        <td class="py-2.5 px-3 text-right text-muted-foreground font-mono text-[11px]">
                                            {{ r.date }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>
    </div>
</template>
