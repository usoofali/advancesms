<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Award,
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    GraduationCap,
    Printer,
    School,
    ShieldAlert,
    UserCheck,
    UserX,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as academicReportCardsIndex, show as academicReportCardsShow } from '@/routes/academic/report-cards';
import { computed } from 'vue';
import { usePrintOrientation } from '@/composables/usePrintOrientation';
import type { ExamMilestone, Grade } from '@/types/academic';

interface MarkItem {
    id: number;
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
    teacher_comment: string | null;
}

interface Props {
    enrollment: {
        id: number;
        roll_number: string | null;
        status: string;
        student: {
            id: number;
            admission_number: string;
            name: string;
            gender: string;
            date_of_birth: string | null;
            avatar: string | null;
            state_of_origin: string | null;
            guardian_name: string | null;
            guardian_phone: string | null;
        };
        class_name: string;
        class_stage: string | null;
        section_name: string;
        session_name: string;
    };
    term: {
        id: number;
        name: string;
        term_number: number;
    };
    marks: MarkItem[];
    summary: {
        total_score_obtained: number;
        total_max_score: number;
        subjects_count: number;
        terminal_average: number;
        section_position: string;
        section_total_students: number;
        class_position: string;
        class_total_students: number;
        highest_class_average: number;
        lowest_class_average: number;
        class_total_average: number;
    };
    attendance: {
        days_present: number;
        days_late: number;
        days_absent: number;
        days_excused: number;
        total_school_days: number;
        attendance_rate: number;
    };
    remarks: {
        teacher: string;
        principal: string;
    };
    school: {
        name: string;
        school_name?: string;
        code: string;
        school_code?: string;
        motto: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        principal_name: string;
        logo_url: string | null;
        stamp_url: string | null;
    };
    exam: ExamMilestone;
    grades: Grade[];
}

const props = defineProps<Props>();

const { applyStyle: enforcePortrait } = usePrintOrientation('portrait', '6mm 8mm');

const printCard = () => {
    enforcePortrait();
    window.print();
};

const resolvedSchoolName = computed(() => {
    return props.school.school_name || props.school.name || 'YUM Academy';
});

// Dynamic layout metrics based on subject count (1 to 14 subjects)
const isDense = computed(() => props.marks.length > 7);
const isUltraDense = computed(() => props.marks.length > 11);

const tableClasses = computed(() => {
    if (isUltraDense.value) return 'text-[9.5px] print:text-[8.5px]';
    if (isDense.value) return 'text-[10.5px] print:text-[9.5px]';
    return 'text-xs print:text-[10.5px]';
});

const thClass = computed(() => {
    if (isUltraDense.value) return 'py-0.5 px-1';
    if (isDense.value) return 'py-1 px-1.5';
    return 'py-1.5 px-2';
});

const tdClass = computed(() => {
    if (isUltraDense.value) return 'py-0.5 px-1 leading-tight';
    if (isDense.value) return 'py-0.5 px-1.5';
    return 'py-1 px-2';
});
</script>

<template>
    <Head :title="`${enrollment.student.name} - Terminal Report Card`" />

    <div class="min-h-screen bg-muted/20 p-2 sm:p-4 lg:p-6 print:bg-white print:p-0">
        <!-- Web Action Toolbar (Hidden in Print) -->
        <div class="mx-auto max-w-4xl mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
            <Link
                :href="academicReportCardsIndex()"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
            >
                <ArrowLeft class="h-3.5 w-3.5" />
                <span>Back to Report Cards Roster</span>
            </Link>

            <div class="flex items-center gap-2">
                <Button
                    size="sm"
                    class="h-8 gap-1.5 text-xs font-semibold shadow-xs"
                    @click="printCard"
                >
                    <Printer class="h-3.5 w-3.5" />
                    <span>Print Report Card</span>
                </Button>
            </div>
        </div>

        <!-- Official Report Card Sheet Container (Constrained strictly to 1 A4 Page) -->
        <div
            class="report-card-sheet mx-auto max-w-4xl bg-card border rounded-lg shadow-sm p-4 sm:p-6 print:border-none print:shadow-none print:p-0 print:m-0 print:max-w-none text-foreground font-sans print:text-black print:break-inside-avoid"
        >
            <!-- 1. Institutional Header -->
            <div class="flex items-center justify-between border-b-2 border-black/80 pb-1.5 mb-1.5">
                <div class="flex items-center gap-2.5">
                    <!-- School Logo -->
                    <div class="size-12 shrink-0 rounded border border-neutral-300 p-0.5 flex items-center justify-center bg-white">
                        <img
                            v-if="school.logo_url"
                            :src="school.logo_url"
                            :alt="resolvedSchoolName"
                            class="size-full object-contain"
                        />
                        <GraduationCap v-else class="size-7 text-neutral-700" />
                    </div>

                    <div class="space-y-0.5">
                        <h1 class="text-base sm:text-lg font-black uppercase tracking-tight text-foreground print:text-black leading-none">
                            {{ resolvedSchoolName }}
                        </h1>
                        <p v-if="school.motto" class="text-[9.5px] italic text-muted-foreground print:text-neutral-700 font-medium leading-none">
                            "{{ school.motto }}"
                        </p>
                        <div class="text-[9px] text-muted-foreground print:text-neutral-700 flex flex-wrap gap-x-2 leading-tight pt-0.5">
                            <span v-if="school.address">{{ school.address }}</span>
                            <span v-if="school.phone">• Tel: {{ school.phone }}</span>
                            <span v-if="school.email">• Email: {{ school.email }}</span>
                            <span v-if="school.code || school.school_code">• Code: {{ school.code || school.school_code }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Academic Session / Term Box -->
                <div class="text-right shrink-0">
                    <div class="inline-block border border-black/80 rounded px-2 py-0.5 text-center bg-muted/40 print:bg-neutral-100">
                        <span class="block text-[8px] font-bold uppercase tracking-wider text-muted-foreground print:text-neutral-600 leading-none">Academic Year</span>
                        <span class="block text-xs font-black text-foreground print:text-black leading-tight">{{ enrollment.session_name }}</span>
                    </div>
                    <div class="text-[9.5px] font-bold text-foreground print:text-black mt-0.5">
                        {{ term.name }}
                    </div>
                </div>
            </div>

            <!-- 2. Official Title Banner -->
            <div class="py-0.5 mb-1.5 text-center bg-muted/50 print:bg-neutral-100 rounded border border-neutral-300 print:border-black/40 text-[10.5px] font-black uppercase tracking-wider text-foreground print:text-black leading-snug">
                Official Continuous Assessment &amp; Terminal Examination Report Card
            </div>

            <!-- 3. Student Bio-Data Matrix (Compact 2-Row Layout) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-2 gap-y-1 text-[10.5px] print:text-[9.5px] border border-neutral-300 print:border-black/40 rounded p-1.5 bg-muted/10 print:bg-transparent mb-1.5">
                <div>
                    <span class="block text-[8px] uppercase tracking-wider text-muted-foreground print:text-neutral-600 font-bold leading-none">Student Full Name</span>
                    <span class="font-bold text-foreground print:text-black truncate block leading-tight">{{ enrollment.student.name }}</span>
                </div>

                <div>
                    <span class="block text-[8px] uppercase tracking-wider text-muted-foreground print:text-neutral-600 font-bold leading-none">Admission Number</span>
                    <span class="font-mono font-bold text-foreground print:text-black block leading-tight">{{ enrollment.student.admission_number }}</span>
                </div>

                <div>
                    <span class="block text-[8px] uppercase tracking-wider text-muted-foreground print:text-neutral-600 font-bold leading-none">Class &amp; Section</span>
                    <span class="font-bold text-foreground print:text-black block leading-tight">{{ enrollment.class_name }} ({{ enrollment.section_name }})</span>
                </div>

                <div>
                    <span class="block text-[8px] uppercase tracking-wider text-muted-foreground print:text-neutral-600 font-bold leading-none">Gender</span>
                    <span class="capitalize font-medium text-foreground print:text-black block leading-tight">{{ enrollment.student.gender }}</span>
                </div>

                <div>
                    <span class="block text-[8px] uppercase tracking-wider text-muted-foreground print:text-neutral-600 font-bold leading-none">Date of Birth</span>
                    <span class="font-medium text-foreground print:text-black block leading-tight">{{ enrollment.student.date_of_birth || 'Not Recorded' }}</span>
                </div>

                <div>
                    <span class="block text-[8px] uppercase tracking-wider text-muted-foreground print:text-neutral-600 font-bold leading-none">State of Origin</span>
                    <span class="font-medium text-foreground print:text-black block leading-tight">{{ enrollment.student.state_of_origin || 'N/A' }}</span>
                </div>

                <div class="sm:col-span-2">
                    <span class="block text-[8px] uppercase tracking-wider text-muted-foreground print:text-neutral-600 font-bold leading-none">Parent / Guardian</span>
                    <span class="font-medium text-foreground print:text-black truncate block leading-tight">
                        {{ enrollment.student.guardian_name || 'N/A' }}
                        <span v-if="enrollment.student.guardian_phone" class="text-muted-foreground print:text-neutral-600 font-mono text-[9px]">({{ enrollment.student.guardian_phone }})</span>
                    </span>
                </div>
            </div>

            <!-- 4. Subject Marks Performance Table (Accommodates 1 to 14 subjects) -->
            <div class="overflow-x-auto border border-neutral-300 print:border-black/50 rounded mb-1.5">
                <table class="w-full text-left border-collapse" :class="tableClasses">
                    <thead>
                        <tr class="border-b bg-muted/60 print:bg-neutral-100 text-muted-foreground print:text-black font-bold uppercase tracking-wider text-[9px]">
                            <th :class="thClass" class="w-6 text-center">#</th>
                            <th :class="thClass">Subject</th>
                            <th :class="thClass" class="text-center w-12">{{ exam.ca1_label || 'CA 1' }} ({{ exam.max_ca1 }})</th>
                            <th v-if="exam.ca_count >= 2" :class="thClass" class="text-center w-12">{{ exam.ca2_label || 'CA 2' }} ({{ exam.max_ca2 }})</th>
                            <th v-if="exam.ca_count >= 3" :class="thClass" class="text-center w-12">{{ exam.ca3_label || 'CA 3' }} ({{ exam.max_ca3 }})</th>
                            <th :class="thClass" class="text-center w-14">Exam ({{ exam.max_exam }})</th>
                            <th :class="thClass" class="text-center w-14 bg-muted/40 print:bg-neutral-200/50 font-black">Total (100)</th>
                            <th :class="thClass" class="text-center w-10 bg-muted/40 print:bg-neutral-200/50 font-black">Grade</th>
                            <th :class="thClass" class="text-center w-8 bg-muted/40 print:bg-neutral-200/50">Pt</th>
                            <th :class="thClass" class="min-w-[120px]">Teacher's Remark</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border print:divide-neutral-300">
                        <tr
                            v-for="(mark, index) in marks"
                            :key="mark.id"
                            class="hover:bg-muted/10 print:hover:bg-transparent font-medium"
                            :class="index % 2 === 1 ? 'bg-muted/5 print:bg-neutral-50/50' : ''"
                        >
                            <td :class="tdClass" class="text-center font-mono text-muted-foreground print:text-neutral-600 text-[9px]">{{ index + 1 }}</td>
                            <td :class="tdClass" class="font-semibold text-foreground print:text-black">{{ mark.subject_name }}</td>
                            <td :class="tdClass" class="text-center font-mono text-muted-foreground print:text-black">{{ mark.ca1 ?? '-' }}</td>
                            <td v-if="exam.ca_count >= 2" :class="tdClass" class="text-center font-mono text-muted-foreground print:text-black">{{ mark.ca2 ?? '-' }}</td>
                            <td v-if="exam.ca_count >= 3" :class="tdClass" class="text-center font-mono text-muted-foreground print:text-black">{{ mark.ca3 ?? '-' }}</td>
                            <td :class="tdClass" class="text-center font-mono text-muted-foreground print:text-black">{{ mark.exam_score ?? '-' }}</td>
                            <td
                                :class="[
                                    tdClass,
                                    'text-center font-mono font-bold bg-muted/20 print:bg-neutral-100/50 text-foreground print:text-black',
                                    mark.total_score < 50 ? 'text-rose-600 print:text-black font-black' : '',
                                ]"
                            >
                                {{ mark.total_score }}
                            </td>
                            <td :class="tdClass" class="text-center font-bold bg-muted/20 print:bg-neutral-100/50">
                                <span
                                    class="inline-block px-1 rounded font-bold"
                                    :class="{
                                        'text-emerald-700 bg-emerald-50 print:bg-transparent print:text-black': ['A', 'B'].includes(mark.grade),
                                        'text-sky-700 bg-sky-50 print:bg-transparent print:text-black': mark.grade === 'C',
                                        'text-amber-700 bg-amber-50 print:bg-transparent print:text-black': ['D', 'E'].includes(mark.grade),
                                        'text-rose-700 bg-rose-50 print:bg-transparent print:text-black': mark.grade === 'F',
                                    }"
                                >
                                    {{ mark.grade }}
                                </span>
                            </td>
                            <td :class="tdClass" class="text-center font-mono bg-muted/20 print:bg-neutral-100/50 text-muted-foreground print:text-black">{{ mark.grade_point }}</td>
                            <td :class="tdClass" class="text-muted-foreground print:text-neutral-800 italic text-[9.5px] print:text-[8.5px]">
                                {{ mark.teacher_comment || mark.remark }}
                            </td>
                        </tr>

                        <!-- Empty state if no marks recorded -->
                        <tr v-if="marks.length === 0">
                            <td :colspan="7 + exam.ca_count" class="py-4 text-center text-muted-foreground italic text-xs">
                                No assessment marks recorded for this terminal examination milestone yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 5. Academic Performance & Attendance Standing (Side-by-Side) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 mb-1.5 text-[10px] print:text-[9px]">
                <!-- Terminal Academic Standing -->
                <div class="border border-neutral-300 print:border-black/40 rounded p-1.5 bg-muted/10 print:bg-transparent">
                    <h3 class="font-bold uppercase tracking-wider text-muted-foreground print:text-black border-b border-neutral-300 pb-0.5 flex items-center justify-between text-[9px] leading-tight">
                        <span>Terminal Academic Standing</span>
                        <Award class="h-3 w-3 text-primary print:text-black" />
                    </h3>

                    <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 pt-1">
                        <div>
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 uppercase font-semibold">Total Score</span>
                            <span class="font-black font-mono text-foreground print:text-black">{{ summary.total_score_obtained }} / {{ summary.total_max_score }}</span>
                        </div>
                        <div>
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 uppercase font-semibold">Terminal Average</span>
                            <span class="font-black font-mono" :class="summary.terminal_average >= 50 ? 'text-emerald-700 print:text-black' : 'text-rose-600 print:text-black'">
                                {{ summary.terminal_average }}%
                            </span>
                        </div>
                        <div class="border-t border-dashed border-neutral-300 pt-0.5">
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 uppercase font-semibold">Position in Section</span>
                            <span class="font-black text-foreground print:text-black leading-tight">
                                {{ summary.section_position }} <span class="font-normal text-muted-foreground print:text-neutral-600 text-[8px]">of {{ summary.section_total_students }}</span>
                            </span>
                        </div>
                        <div class="border-t border-dashed border-neutral-300 pt-0.5">
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 uppercase font-semibold">Position in Class</span>
                            <span class="font-black text-foreground print:text-black leading-tight">
                                {{ summary.class_position }} <span class="font-normal text-muted-foreground print:text-neutral-600 text-[8px]">of {{ summary.class_total_students }}</span>
                            </span>
                        </div>
                        <div class="col-span-2 border-t border-neutral-300 pt-0.5 flex items-center justify-between text-[8.5px] text-muted-foreground print:text-neutral-700">
                            <span>Class Avg: <b class="text-foreground print:text-black">{{ summary.class_total_average }}%</b></span>
                            <span>Highest: <b class="text-emerald-700 print:text-black">{{ summary.highest_class_average }}%</b></span>
                            <span>Lowest: <b class="text-rose-600 print:text-black">{{ summary.lowest_class_average }}%</b></span>
                        </div>
                    </div>
                </div>

                <!-- Attendance Statistics Box -->
                <div class="border border-neutral-300 print:border-black/40 rounded p-1.5 bg-muted/10 print:bg-transparent">
                    <h3 class="font-bold uppercase tracking-wider text-muted-foreground print:text-black border-b border-neutral-300 pb-0.5 flex items-center justify-between text-[9px] leading-tight">
                        <span>Attendance &amp; Punctuality Profile</span>
                        <Calendar class="h-3 w-3 text-primary print:text-black" />
                    </h3>

                    <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 pt-1">
                        <div>
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 uppercase font-semibold">School Days Recorded</span>
                            <span class="font-bold font-mono text-foreground print:text-black">{{ attendance.total_school_days }} Days</span>
                        </div>
                        <div>
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 uppercase font-semibold">Attendance Rate</span>
                            <span class="font-bold font-mono text-emerald-700 print:text-black">{{ attendance.attendance_rate }}%</span>
                        </div>
                        <div class="border-t border-dashed border-neutral-300 pt-0.5">
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 uppercase font-semibold">Days Present / Late</span>
                            <span class="font-bold text-foreground print:text-black">{{ attendance.days_present }} Present, {{ attendance.days_late }} Late</span>
                        </div>
                        <div class="border-t border-dashed border-neutral-300 pt-0.5">
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 uppercase font-semibold">Days Absent / Excused</span>
                            <span class="font-bold text-rose-600 print:text-black">{{ attendance.days_absent }} Absent, {{ attendance.days_excused }} Excused</span>
                        </div>
                        <div class="col-span-2 border-t border-neutral-300 pt-0.5 text-[8.5px] text-muted-foreground print:text-neutral-700">
                            Minimum Term Attendance Threshold: <b class="text-foreground print:text-black">85% Required</b>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Grading Scale Reference Legend (Single-line horizontal) -->
            <div class="border border-neutral-300 print:border-black/40 rounded px-2 py-0.5 bg-muted/5 print:bg-transparent mb-1.5 text-[8.5px] print:text-[8px] flex flex-wrap items-center">
                <span class="font-bold uppercase tracking-wider text-muted-foreground print:text-black mr-2">Grading Benchmark:</span>
                <span v-for="(g, idx) in grades" :key="g.id" class="inline-flex items-center">
                    <span v-if="idx > 0" class="mx-1 text-neutral-400">•</span>
                    <b class="font-bold text-foreground print:text-black">{{ g.name }}:</b>&nbsp;{{ g.mark_from }}-{{ g.mark_to }}% ({{ g.remark }})
                </span>
            </div>

            <!-- 7. Remarks, Certification & Endorsement (Form Master + Principal Seal) -->
            <div class="border border-neutral-300 print:border-black/40 rounded p-1.5 bg-muted/10 print:bg-transparent text-[10px] print:text-[9px] mb-1">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 items-end">
                    <!-- Remarks Columns -->
                    <div class="sm:col-span-2 space-y-1">
                        <div>
                            <span class="block font-bold uppercase tracking-wider text-[8px] text-muted-foreground print:text-neutral-600 leading-none">
                                Form Master / Class Teacher's Remark:
                            </span>
                            <p class="italic text-foreground print:text-black border-b border-dashed border-neutral-400 pb-0.5 text-[10px] print:text-[9px] leading-tight">
                                {{ remarks.teacher }}
                            </p>
                        </div>
                        <div>
                            <span class="block font-bold uppercase tracking-wider text-[8px] text-muted-foreground print:text-neutral-600 leading-none">
                                Principal's Official Recommendation:
                            </span>
                            <p class="italic text-foreground print:text-black border-b border-dashed border-neutral-400 pb-0.5 text-[10px] print:text-[9px] leading-tight">
                                {{ remarks.principal }}
                            </p>
                        </div>
                    </div>

                    <!-- Principal Seal & Signature Box -->
                    <div class="text-center shrink-0 border-t sm:border-t-0 sm:border-l sm:pl-2.5 border-neutral-300 print:border-black/30">
                        <div class="h-9 sm:h-11 flex items-center justify-center">
                            <img
                                v-if="school.stamp_url"
                                :src="school.stamp_url"
                                alt="Principal Stamp"
                                class="h-9 sm:h-11 object-contain"
                            />
                            <div v-else class="text-[8px] text-muted-foreground/70 italic border border-dashed rounded px-2 py-0.5">
                                [ Official Seal / Signature ]
                            </div>
                        </div>
                        <div class="border-t border-black/80 pt-0.5 mt-0.5">
                            <span class="block font-bold text-[10px] print:text-[9px] uppercase tracking-wide text-foreground print:text-black leading-tight">{{ school.principal_name }}</span>
                            <span class="block text-[8px] text-muted-foreground print:text-neutral-600 leading-none">Principal / Head of School</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    @page {
        size: A4 portrait;
        margin: 6mm 8mm;
    }
    :root, body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print\:break-inside-avoid {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
    .report-card-sheet {
        box-sizing: border-box !important;
        max-height: 284mm !important;
        overflow: hidden !important;
    }
}
</style>

