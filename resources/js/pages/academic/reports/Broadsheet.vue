<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    Calendar,
    ChevronDown,
    Download,
    FileSpreadsheet,
    Filter,
    Layers,
    Printer,
    Search,
    TrendingUp,
    Users,
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
import { index as academicBroadsheetIndex } from '@/routes/academic/broadsheet';
import type { AcademicSession, AcademicTerm, SchoolClass, Section, Subject } from '@/types/academic';

interface SubjectScoreItem {
    score: number | null;
    grade: string | null;
}

interface MatrixStudentRow {
    enrollment_id: number;
    student_id: number;
    admission_number: string;
    student_name: string;
    gender: string;
    roll_number: string | null;
    section_id: number;
    section_name: string;
    subject_scores: Record<number, SubjectScoreItem>;
    total_score: number;
    average: number;
    subjects_taken: number;
    class_rank: number;
    class_position: string;
    section_rank: number;
    section_position: string;
}

interface SubjectStat {
    average: number;
    highest: number;
    lowest: number;
    passes: number;
    fails: number;
    total_sat: number;
}

interface ClassMetrics {
    total_students: number;
    class_average: number;
    highest_total: number;
    lowest_total: number;
}

interface Props {
    sessions: AcademicSession[];
    classes: (SchoolClass & { sections: Section[] })[];
    selectedSessionId: number;
    selectedTermId: number;
    selectedClassId: number;
    selectedSectionId: number;
    subjects: Subject[];
    matrixRows: MatrixStudentRow[];
    subjectStats: Record<number, SubjectStat>;
    classMetrics: ClassMetrics;
    school: {
        name: string;
        code: string;
        motto: string | null;
        logo_url: string | null;
    };
}

const props = defineProps<Props>();

// Filters state
const selectedSessionId = ref(props.selectedSessionId);
const selectedTermId = ref(props.selectedTermId);
const selectedClassId = ref(props.selectedClassId);
const selectedSectionId = ref(props.selectedSectionId);
const searchQuery = ref('');

// Computed available sections
const availableSections = computed(() => {
    const cls = props.classes.find((c) => c.id === Number(selectedClassId.value));
    return cls?.sections || [];
});

const activeSession = computed(() => {
    return props.sessions.find((s) => s.id === Number(selectedSessionId.value));
});

const availableTerms = computed(() => {
    return activeSession.value?.terms || [];
});

const currentClass = computed(() => {
    return props.classes.find((c) => c.id === Number(selectedClassId.value));
});

const currentTerm = computed(() => {
    return availableTerms.value.find((t) => t.id === Number(selectedTermId.value));
});

// Filter matrix rows by search query
const displayedRows = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.matrixRows;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.matrixRows.filter(
        (r) =>
            r.student_name.toLowerCase().includes(q) ||
            r.admission_number.toLowerCase().includes(q) ||
            r.section_name.toLowerCase().includes(q)
    );
});

const applyFilters = () => {
    router.get(
        academicBroadsheetIndex(),
        {
            session_id: selectedSessionId.value,
            term_id: selectedTermId.value,
            class_id: selectedClassId.value,
            section_id: selectedSectionId.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
};

const onClassChange = () => {
    selectedSectionId.value = 0; // reset to all sections
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

const { applyStyle: enforceLandscape } = usePrintOrientation('landscape', '8mm');

const printBroadsheet = () => {
    enforceLandscape();
    window.print();
};

const downloadCsv = () => {
    const url = new URL(window.location.origin + academicBroadsheetIndex().url);
    url.searchParams.set('session_id', String(selectedSessionId.value));
    url.searchParams.set('term_id', String(selectedTermId.value));
    url.searchParams.set('class_id', String(selectedClassId.value));
    url.searchParams.set('section_id', String(selectedSectionId.value));
    url.searchParams.set('export', 'csv');
    window.open(url.toString(), '_blank');
};
</script>

<template>
    <Head title="Result Broadsheet Compilation" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8 print:p-0 print:space-y-3">
        <!-- Institutional Letterhead Print Header -->
        <PrintHeader
            title="Academic Broadsheet Compilation"
            subtitle="Master Subject Performance, Total Marks & Class Ranking"
            :session-term="`${activeSession?.name || ''} - ${currentTerm?.name || ''}`"
            :class-section="`${currentClass?.name || ''}${selectedSectionId ? ' (' + (availableSections.find(s => s.id === Number(selectedSectionId))?.name || '') + ')' : ' (All Sections)'}`"
        />

        <!-- Web Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl flex items-center gap-2.5">
                    <FileSpreadsheet class="h-7 w-7 text-primary" />
                    <span>Result Broadsheet Compilation</span>
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Multi-subject terminal grade broadsheet matrix with ordinal class & section rankings.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-2 w-full sm:w-auto">
                <Button
                    variant="outline"
                    class="h-10 w-full sm:w-auto gap-2"
                    :disabled="matrixRows.length === 0"
                    @click="downloadCsv"
                >
                    <Download class="h-4 w-4" />
                    <span>Export CSV</span>
                </Button>

                <Button
                    class="h-10 w-full sm:w-auto gap-2 font-semibold shadow-sm"
                    :disabled="matrixRows.length === 0"
                    @click="printBroadsheet"
                >
                    <Printer class="h-4 w-4" />
                    <span>Print Broadsheet</span>
                </Button>
            </div>
        </div>

        <!-- Filter Controls Bar (Hidden in print) -->
        <Card class="border shadow-sm print:hidden">
            <CardContent class="p-4 sm:p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

                    <!-- Term Selector -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Term / Exam</Label>
                        <select
                            v-model="selectedTermId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="applyFilters"
                        >
                            <option
                                v-for="term in availableTerms"
                                :key="term.id"
                                :value="term.id"
                            >
                                {{ term.name }} {{ term.is_current ? '(Current)' : '' }}
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
                            <option :value="0">All Sections (Entire Class)</option>
                            <option
                                v-for="sec in availableSections"
                                :key="sec.id"
                                :value="sec.id"
                            >
                                Section {{ sec.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Summary Statistics Strip (Hidden in print) -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 print:hidden">
            <Card class="border shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Students Evaluated</p>
                        <p class="text-xl font-bold tracking-tight text-foreground">{{ classMetrics.total_students }}</p>
                    </div>
                    <div class="rounded-full bg-primary/10 p-2 text-primary">
                        <Users class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Class Average</p>
                        <p class="text-xl font-bold tracking-tight text-foreground">{{ classMetrics.class_average }}%</p>
                    </div>
                    <div class="rounded-full bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                        <TrendingUp class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Highest Aggregate</p>
                        <p class="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                            {{ classMetrics.highest_total }}
                        </p>
                    </div>
                    <div class="rounded-full bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                        <Award class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-xs">
                <CardContent class="p-3.5 sm:p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Lowest Aggregate</p>
                        <p class="text-xl font-bold tracking-tight text-muted-foreground">{{ classMetrics.lowest_total }}</p>
                    </div>
                    <div class="rounded-full bg-muted p-2 text-muted-foreground">
                        <BookOpen class="h-4 w-4" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Broadsheet Matrix Card -->
        <Card class="border shadow-sm print:border-none print:shadow-none">
            <CardHeader class="p-4 sm:p-6 pb-2 sm:pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b print:hidden">
                <div>
                    <CardTitle class="text-base sm:text-lg flex items-center gap-2">
                        <span>Broadsheet Master Matrix</span>
                        <Badge variant="secondary" class="font-normal text-xs">
                            {{ displayedRows.length }} Candidates
                        </Badge>
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">
                        Showing academic records for {{ currentClass?.name }} • {{ currentTerm?.name }} • {{ activeSession?.name }}
                    </CardDescription>
                </div>

                <div class="w-full sm:w-72">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Filter candidate by name..."
                            class="pl-9 h-9 text-xs"
                        />
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <!-- Empty State -->
                <div
                    v-if="displayedRows.length === 0"
                    class="flex flex-col items-center justify-center p-12 text-center"
                >
                    <div class="rounded-full bg-muted p-4 mb-3">
                        <FileSpreadsheet class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <h3 class="text-base font-semibold text-foreground">No broadsheet records found</h3>
                    <p class="text-xs text-muted-foreground max-w-sm mt-1">
                        {{
                            searchQuery
                                ? 'No student matches your search query in this broadsheet.'
                                : 'There are no examination marks recorded yet for this class and term.'
                        }}
                    </p>
                </div>

                <!-- Matrix Table -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs print:text-[10px]">
                        <thead>
                            <tr class="border-b bg-muted/60 text-muted-foreground font-semibold uppercase tracking-wider">
                                <th class="py-2.5 px-3 w-10 text-center">Rank</th>
                                <th class="py-2.5 px-3 min-w-[170px]">Candidate Details</th>
                                <th class="py-2.5 px-2 min-w-[70px] text-center">Section</th>
                                <!-- Dynamic Subject Columns -->
                                <th
                                    v-for="sub in subjects"
                                    :key="sub.id"
                                    class="py-2.5 px-2 text-center min-w-[70px] border-l"
                                    :title="sub.name"
                                >
                                    {{ sub.code || sub.name.substring(0, 4) }}
                                </th>
                                <!-- Computed Aggregates -->
                                <th class="py-2.5 px-3 min-w-[70px] text-center border-l bg-muted/30">Total</th>
                                <th class="py-2.5 px-3 min-w-[70px] text-center bg-muted/30">Average</th>
                                <th class="py-2.5 px-3 min-w-[70px] text-center bg-muted/30">Pos (Sec)</th>
                                <th class="py-2.5 px-3 min-w-[70px] text-center bg-muted/30">Pos (Class)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="(row, index) in displayedRows"
                                :key="row.enrollment_id"
                                class="transition-colors hover:bg-muted/20"
                                :class="{
                                    'bg-amber-50/20 dark:bg-amber-950/10 font-medium': row.class_rank === 1,
                                }"
                            >
                                <!-- Rank -->
                                <td class="py-2 px-3 text-center text-muted-foreground font-medium">
                                    <span
                                        v-if="row.class_rank <= 3"
                                        class="inline-flex items-center justify-center h-5 w-5 rounded-full text-[10px] font-bold"
                                        :class="{
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300': row.class_rank === 1,
                                            'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-200': row.class_rank === 2,
                                            'bg-amber-200 text-amber-900 dark:bg-amber-950 dark:text-amber-400': row.class_rank === 3,
                                        }"
                                    >
                                        {{ row.class_rank }}
                                    </span>
                                    <span v-else>{{ row.class_rank }}</span>
                                </td>

                                <!-- Student Details -->
                                <td class="py-2 px-3">
                                    <div class="font-medium text-foreground">
                                        {{ row.student_name }}
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground font-mono">
                                        <span>{{ row.admission_number }}</span>
                                        <span class="capitalize text-[10px] px-1 rounded bg-muted">
                                            {{ row.gender }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Section -->
                                <td class="py-2 px-2 text-center text-muted-foreground">
                                    <span class="px-1.5 py-0.5 rounded bg-muted/70 text-[11px]">
                                        {{ row.section_name }}
                                    </span>
                                </td>

                                <!-- Dynamic Subject Scores -->
                                <td
                                    v-for="sub in subjects"
                                    :key="sub.id"
                                    class="py-2 px-2 text-center border-l font-mono"
                                >
                                    <template v-if="row.subject_scores[sub.id] && row.subject_scores[sub.id].score !== null">
                                        <div class="font-semibold" :class="Number(row.subject_scores[sub.id].score) < 50 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'">
                                            {{ row.subject_scores[sub.id].score }}
                                        </div>
                                        <span
                                            v-if="row.subject_scores[sub.id].grade"
                                            class="text-[10px] px-1 rounded"
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': ['A', 'B'].includes(row.subject_scores[sub.id].grade!),
                                                'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300': row.subject_scores[sub.id].grade === 'C',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': ['D', 'E'].includes(row.subject_scores[sub.id].grade!),
                                                'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': row.subject_scores[sub.id].grade === 'F',
                                            }"
                                        >
                                            {{ row.subject_scores[sub.id].grade }}
                                        </span>
                                    </template>
                                    <span v-else class="text-muted-foreground/40">-</span>
                                </td>

                                <!-- Total Score -->
                                <td class="py-2 px-3 text-center border-l font-mono font-bold text-foreground bg-muted/20">
                                    {{ row.total_score }}
                                </td>

                                <!-- Average -->
                                <td class="py-2 px-3 text-center font-mono font-semibold bg-muted/20" :class="row.average >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                    {{ row.average }}%
                                </td>

                                <!-- Section Position -->
                                <td class="py-2 px-3 text-center font-semibold text-foreground bg-muted/20">
                                    {{ row.section_position }}
                                </td>

                                <!-- Class Position -->
                                <td class="py-2 px-3 text-center font-bold text-primary bg-muted/20">
                                    {{ row.class_position }}
                                </td>
                            </tr>
                        </tbody>

                        <!-- Subject Column Summary Row (Averages, Highest, Lowest, Passes) -->
                        <tfoot class="border-t-2 font-semibold bg-muted/40">
                            <!-- Subject Averages Row -->
                            <tr class="text-[11px]">
                                <td colspan="3" class="py-2 px-3 text-right text-muted-foreground uppercase tracking-wider">
                                    Subject Average:
                                </td>
                                <td
                                    v-for="sub in subjects"
                                    :key="sub.id"
                                    class="py-2 px-2 text-center border-l font-mono"
                                >
                                    {{ subjectStats[sub.id]?.average ?? '-' }}%
                                </td>
                                <td colspan="4" class="bg-muted/20"></td>
                            </tr>

                            <!-- Highest Score Row -->
                            <tr class="text-[11px] text-emerald-600 dark:text-emerald-400">
                                <td colspan="3" class="py-1.5 px-3 text-right uppercase tracking-wider text-[10px]">
                                    Highest Score:
                                </td>
                                <td
                                    v-for="sub in subjects"
                                    :key="sub.id"
                                    class="py-1.5 px-2 text-center border-l font-mono"
                                >
                                    {{ subjectStats[sub.id]?.highest ?? '-' }}
                                </td>
                                <td colspan="4" class="bg-muted/20"></td>
                            </tr>

                            <!-- Pass / Fail Count Row -->
                            <tr class="text-[10px] text-muted-foreground">
                                <td colspan="3" class="py-1.5 px-3 text-right uppercase tracking-wider">
                                    Pass / Fail:
                                </td>
                                <td
                                    v-for="sub in subjects"
                                    :key="sub.id"
                                    class="py-1.5 px-2 text-center border-l font-mono"
                                >
                                    <span class="text-emerald-600">{{ subjectStats[sub.id]?.passes ?? 0 }}P</span> /
                                    <span class="text-rose-600">{{ subjectStats[sub.id]?.fails ?? 0 }}F</span>
                                </td>
                                <td colspan="4" class="bg-muted/20"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Print-Only Institutional Sign-off -->
                <div class="hidden print:flex justify-between items-end mt-8 pt-4 border-t border-black text-[11px]">
                    <div>
                        <p class="font-semibold text-black">Class Teacher's Signature: _______________________</p>
                        <p class="text-[10px] text-gray-600 mt-1">Date: _______________________</p>
                    </div>
                    <div class="text-center">
                        <p class="font-semibold text-black">Examination Officer: _______________________</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-black">Principal's Signature & Stamp: _______________________</p>
                        <p class="text-[10px] text-gray-600 mt-1">Official Institutional Seal</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
@media print {
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
