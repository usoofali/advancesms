<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Award,
    Calculator,
    Check,
    CheckCircle2,
    ChevronDown,
    Download,
    FileSpreadsheet,
    HelpCircle,
    Info,
    Layers,
    PenTool,
    RotateCcw,
    Save,
    Search,
    ShieldCheck,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
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
import { batchSave, index as academicMarksIndex } from '@/routes/academic/marks';
import type { AcademicSession, AcademicTerm, Grade, SchoolClass, Section, Subject } from '@/types/academic';

interface GridRow {
    enrollment_id: number;
    student_id: number;
    admission_number: string;
    student_name: string;
    gender: string;
    roll_number: string | null;
    mark_id: number | null;
    ca1: number | null;
    ca2: number | null;
    ca3: number | null;
    exam_score: number | null;
    total_score: number;
    grade_name: string | null;
    teacher_comment: string;
}

interface ExamMilestone {
    id: number;
    academic_session_id: number;
    academic_term_id: number;
    name: string;
    ca_count: number;
    max_ca1: string | number;
    max_ca2: string | number;
    max_ca3: string | number;
    max_exam: string | number;
    ca1_label?: string | null;
    ca2_label?: string | null;
    ca3_label?: string | null;
    is_published: boolean;
}

interface Props {
    sessions: (AcademicSession & { terms?: AcademicTerm[] })[];
    selectedSessionId: number;
    selectedTermId: number;
    classes: (SchoolClass & { sections?: Section[] })[];
    selectedClassId: number;
    sections: Section[];
    selectedSectionId: number;
    subjects: Subject[];
    selectedSubjectId: number;
    exam: ExamMilestone;
    gridRows: GridRow[];
    grades: Grade[];
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Marks & Assessment',
                href: academicMarksIndex().url,
            },
        ],
    },
});

const page = usePage();

// Current contexts
const currentSession = computed(() => props.sessions.find((s) => s.id === props.selectedSessionId));
const availableTerms = computed(() => currentSession.value?.terms ?? []);
const currentTerm = computed(() => availableTerms.value.find((t) => t.id === props.selectedTermId));
const currentClass = computed(() => props.classes.find((c) => c.id === props.selectedClassId));
const currentSection = computed(() => props.sections.find((s) => s.id === props.selectedSectionId));
const currentSubject = computed(() => props.subjects.find((s) => s.id === props.selectedSubjectId));

// Score limits & CA configuration
const caCount = computed(() => Number(props.exam.ca_count) || 3);
const ca1Label = computed(() => props.exam.ca1_label || 'CA 1');
const ca2Label = computed(() => props.exam.ca2_label || 'CA 2');
const ca3Label = computed(() => props.exam.ca3_label || 'CA 3');

const maxCa1 = computed(() => Number(props.exam.max_ca1));
const maxCa2 = computed(() => Number(props.exam.max_ca2));
const maxCa3 = computed(() => Number(props.exam.max_ca3));
const maxExam = computed(() => Number(props.exam.max_exam));
const maxTotal = computed(() => {
    let total = maxCa1.value + maxExam.value;
    if (caCount.value >= 2) total += maxCa2.value;
    if (caCount.value >= 3) total += maxCa3.value;
    return total;
});

// Reactive student marks matrix in memory
interface MarkCell {
    enrollment_id: number;
    student_id: number;
    admission_number: string;
    student_name: string;
    gender: string;
    roll_number: string | null;
    ca1: string | number;
    ca2: string | number;
    ca3: string | number;
    exam_score: string | number;
    teacher_comment: string;
}

const marksGrid = ref<MarkCell[]>([]);
const hasUnsavedChanges = ref(false);
const isAutosaving = ref(false);
const lastSavedTime = ref<string | null>(null);

// Real-time calculation helpers
const calculateRowTotal = (row: MarkCell): number => {
    const c1 = Number(row.ca1) || 0;
    const c2 = caCount.value >= 2 ? (Number(row.ca2) || 0) : 0;
    const c3 = caCount.value >= 3 ? (Number(row.ca3) || 0) : 0;
    const ex = Number(row.exam_score) || 0;
    return Number((c1 + c2 + c3 + ex).toFixed(2));
};

const resolveGrade = (totalScore: number): { name: string; remark: string } => {
    const matched = props.grades.find(
        (g) => totalScore >= Number(g.mark_from) && totalScore <= Number(g.mark_to),
    );
    if (matched) {
        return { name: matched.name, remark: matched.remark };
    }
    return { name: '—', remark: '—' };
};

// Initialize or update rows
const initGrid = () => {
    marksGrid.value = props.gridRows.map((r) => {
        const ca1 = r.ca1 !== null && r.ca1 !== undefined ? r.ca1 : '';
        const ca2 = r.ca2 !== null && r.ca2 !== undefined ? r.ca2 : '';
        const ca3 = r.ca3 !== null && r.ca3 !== undefined ? r.ca3 : '';
        const examScore = r.exam_score !== null && r.exam_score !== undefined ? r.exam_score : '';

        const hasAnyScore = ca1 !== '' || ca2 !== '' || ca3 !== '' || examScore !== '';
        const total = (Number(ca1) || 0) + (caCount.value >= 2 ? (Number(ca2) || 0) : 0) + (caCount.value >= 3 ? (Number(ca3) || 0) : 0) + (Number(examScore) || 0);
        const defaultRemark = hasAnyScore ? resolveGrade(total).remark : '';

        let comment = r.teacher_comment ? r.teacher_comment.trim() : '';
        if (!comment && defaultRemark && defaultRemark !== '—') {
            comment = defaultRemark;
        }

        return {
            enrollment_id: r.enrollment_id,
            student_id: r.student_id,
            admission_number: r.admission_number,
            student_name: r.student_name,
            gender: r.gender,
            roll_number: r.roll_number,
            ca1,
            ca2,
            ca3,
            exam_score: examScore,
            teacher_comment: comment,
        };
    });
    hasUnsavedChanges.value = false;
};

watch(() => props.gridRows, initGrid, { immediate: true });

// Context switcher
const onContextChange = (newParams: {
    session_id?: number | string;
    term_id?: number | string;
    class_id?: number | string;
    section_id?: number | string;
    subject_id?: number | string;
}) => {
    if (hasUnsavedChanges.value) {
        if (!confirm('You have unsaved mark changes. Are you sure you want to switch class/subject?')) {
            return;
        }
    }

    const isClassChanging = newParams.class_id !== undefined && Number(newParams.class_id) !== props.selectedClassId;
    const isSessionChanging = newParams.session_id !== undefined && Number(newParams.session_id) !== props.selectedSessionId;

    const query: Record<string, any> = {
        session_id: newParams.session_id ?? props.selectedSessionId,
        term_id: isSessionChanging ? undefined : (newParams.term_id ?? props.selectedTermId),
        class_id: newParams.class_id ?? props.selectedClassId,
        section_id: isClassChanging ? undefined : (newParams.section_id ?? props.selectedSectionId),
        subject_id: isClassChanging ? undefined : (newParams.subject_id ?? props.selectedSubjectId),
    };

    router.get(academicMarksIndex().url, query, { preserveState: false });
};

// Statistical Analytics
const analytics = computed(() => {
    const rows = marksGrid.value;
    if (rows.length === 0) {
        return { average: 0, highest: 0, lowest: 0, passCount: 0, passRate: 0 };
    }

    const scores = rows.map((r) => calculateRowTotal(r));
    const total = scores.reduce((acc, s) => acc + s, 0);
    const average = Number((total / rows.length).toFixed(1));
    const highest = Math.max(...scores);
    const lowest = Math.min(...scores);
    const passThreshold = 40; // Standard WAEC / Nigerian Secondary benchmark
    const passCount = scores.filter((s) => s >= passThreshold).length;
    const passRate = Math.round((passCount / rows.length) * 100);

    return { average, highest, lowest, passCount, passRate };
});

// Search filter inside student grid
const tableFilterQuery = ref('');
const displayedRows = computed(() => {
    if (!tableFilterQuery.value.trim()) return marksGrid.value;
    const q = tableFilterQuery.value.toLowerCase().trim();
    return marksGrid.value.filter(
        (r) =>
            r.student_name.toLowerCase().includes(q) ||
            r.admission_number.toLowerCase().includes(q) ||
            (r.roll_number && r.roll_number.toLowerCase().includes(q)),
    );
});

// Mark change handler
const onScoreChange = (row: MarkCell, field: 'ca1' | 'ca2' | 'ca3' | 'exam_score', max: number) => {
    hasUnsavedChanges.value = true;
    const val = Number(row[field]);
    if (val > max) {
        toast.warning(`Maximum score for ${field.toUpperCase()} is ${max}`);
    }

    const total = calculateRowTotal(row);
    const newGrade = resolveGrade(total);
    const allGradeRemarks = props.grades.map((g) => g.remark);

    // If teacher_comment is empty or currently matches a default grade remark, update to new score's default remark
    if (!row.teacher_comment || row.teacher_comment.trim() === '' || allGradeRemarks.includes(row.teacher_comment.trim())) {
        if (newGrade.remark && newGrade.remark !== '—') {
            row.teacher_comment = newGrade.remark;
        }
    }
};

// Excel-style Keyboard Navigation
const handleKeyDown = (
    e: KeyboardEvent,
    rowIndex: number,
    columnName: 'ca1' | 'ca2' | 'ca3' | 'exam_score' | 'teacher_comment',
) => {
    const columns: ('ca1' | 'ca2' | 'ca3' | 'exam_score' | 'teacher_comment')[] = ['ca1'];
    if (caCount.value >= 2) columns.push('ca2');
    if (caCount.value >= 3) columns.push('ca3');
    columns.push('exam_score', 'teacher_comment');

    const colIndex = columns.indexOf(columnName);

    // Enter / Shift+Enter Navigation (Vertical down/up entry)
    if (e.key === 'Enter') {
        e.preventDefault();
        const targetRow = e.shiftKey ? rowIndex - 1 : rowIndex + 1;
        focusCell(targetRow, columnName);
    }
    // Arrow Down
    else if (e.key === 'ArrowDown') {
        e.preventDefault();
        focusCell(rowIndex + 1, columnName);
    }
    // Arrow Up
    else if (e.key === 'ArrowUp') {
        e.preventDefault();
        focusCell(rowIndex - 1, columnName);
    }
};

const focusCell = (rowIndex: number, colName: string) => {
    if (rowIndex < 0 || rowIndex >= displayedRows.value.length) return;
    nextTick(() => {
        const el = document.getElementById(`cell-${rowIndex}-${colName}`);
        if (el) {
            (el as HTMLInputElement).focus();
            (el as HTMLInputElement).select();
        }
    });
};

// Save Action
const saveMarks = (isSilent = false) => {
    if (!props.selectedSubjectId || !props.exam.id) return;

    if (!isSilent) {
        isAutosaving.value = true;
    }

    const payload = {
        exam_id: props.exam.id,
        subject_id: props.selectedSubjectId,
        marks: marksGrid.value.map((r) => ({
            enrollment_id: r.enrollment_id,
            ca1: r.ca1 !== '' ? Number(r.ca1) : null,
            ca2: caCount.value >= 2 ? (r.ca2 !== '' ? Number(r.ca2) : null) : null,
            ca3: caCount.value >= 3 ? (r.ca3 !== '' ? Number(r.ca3) : null) : null,
            exam_score: r.exam_score !== '' ? Number(r.exam_score) : null,
            teacher_comment: r.teacher_comment,
        })),
    };

    router.post(batchSave().url, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            hasUnsavedChanges.value = false;
            isAutosaving.value = false;
            lastSavedTime.value = new Date().toLocaleTimeString();
            toast.success('Assessment marks saved successfully.');
        },
        onError: (errs) => {
            isAutosaving.value = false;
            toast.error('Failed to save assessment marks. Please check score limits.');
        },
    });
};

// Grading Scale Reference Modal
const isGradeGuideOpen = ref(false);
</script>

<template>
    <Head title="Marks Entry" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl flex items-center gap-2.5">
                    <PenTool class="h-6 w-6 text-primary" />
                    <span>Marks Entry</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Excel-style rapid mark entry grid with real-time grade resolution and score boundary enforcement.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-2 w-full sm:w-auto">
                <Button
                    variant="outline"
                    class="h-10 w-full sm:w-auto gap-2"
                    @click="isGradeGuideOpen = true"
                >
                    <HelpCircle class="h-4 w-4 text-muted-foreground" />
                    <span>Grading Scale</span>
                </Button>

                <Button
                    class="h-10 w-full sm:w-auto gap-2 shadow-sm font-semibold"
                    :disabled="isAutosaving || displayedRows.length === 0"
                    @click="() => saveMarks(false)"
                >
                    <Spinner v-if="isAutosaving" class="h-4 w-4" />
                    <Save v-else class="h-4 w-4" />
                    <span>{{ isAutosaving ? 'Saving Marks...' : 'Save All Marks' }}</span>
                </Button>
            </div>
        </div>

        <!-- Class, Section, and Subject Context Bar -->
        <Card class="border-border/60 shadow-xs bg-muted/20">
            <CardContent class="p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- Session Selector -->
                    <div class="space-y-1">
                        <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Session</Label>
                        <select
                            :value="selectedSessionId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                            @change="(e) => onContextChange({ session_id: (e.target as HTMLSelectElement).value })"
                        >
                            <option v-for="s in sessions" :key="s.id" :value="s.id">
                                {{ s.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Term Selector -->
                    <div class="space-y-1">
                        <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Term</Label>
                        <select
                            :value="selectedTermId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                            @change="(e) => onContextChange({ term_id: (e.target as HTMLSelectElement).value })"
                        >
                            <option v-for="t in availableTerms" :key="t.id" :value="t.id">
                                {{ t.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Class Selector -->
                    <div class="space-y-1">
                        <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Class</Label>
                        <select
                            :value="selectedClassId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                            :disabled="classes.length === 0"
                            @change="(e) => onContextChange({ class_id: (e.target as HTMLSelectElement).value })"
                        >
                            <option v-if="classes.length === 0" :value="0" disabled>
                                No classes allocated
                            </option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Section Selector -->
                    <div class="space-y-1">
                        <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Section</Label>
                        <select
                            :value="selectedSectionId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                            :disabled="sections.length === 0"
                            @change="(e) => onContextChange({ section_id: (e.target as HTMLSelectElement).value })"
                        >
                            <option v-if="sections.length === 0" :value="0" disabled>
                                No sections allocated
                            </option>
                            <option v-for="sec in sections" :key="sec.id" :value="sec.id">
                                Section {{ sec.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Subject Selector -->
                    <div class="space-y-1">
                        <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Subject</Label>
                        <select
                            :value="selectedSubjectId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                            :disabled="subjects.length === 0"
                            @change="(e) => onContextChange({ subject_id: (e.target as HTMLSelectElement).value })"
                        >
                            <option v-if="subjects.length === 0" :value="0" disabled>
                                No subjects allocated to this class
                            </option>
                            <option v-for="sub in subjects" :key="sub.id" :value="sub.id">
                                {{ sub.name }} ({{ sub.code }})
                            </option>
                        </select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- KPI Metrics & Score Limits Banner -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Class Roster Size
                    </CardTitle>
                    <Users class="h-4 w-4 text-primary" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight">
                        {{ marksGrid.length }} Students
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ currentClass?.name }} - Section {{ currentSection?.name }}
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Class Average
                    </CardTitle>
                    <Calculator class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                        {{ analytics.average }}%
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        High: {{ analytics.highest }}% | Low: {{ analytics.lowest }}%
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Passing Rate (≥40%)
                    </CardTitle>
                    <Award class="h-4 w-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ analytics.passRate }}%
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ analytics.passCount }} of {{ marksGrid.length }} students passing
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Score Structure
                    </CardTitle>
                    <Info class="h-4 w-4 text-purple-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-sm font-semibold tracking-tight">
                        CA: {{ maxCa1 + (caCount >= 2 ? maxCa2 : 0) + (caCount >= 3 ? maxCa3 : 0) }}% | Exam: {{ maxExam }}%
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ ca1Label }}({{ maxCa1 }})
                        <span v-if="caCount >= 2"> + {{ ca2Label }}({{ maxCa2 }})</span>
                        <span v-if="caCount >= 3"> + {{ ca3Label }}({{ maxCa3 }})</span>
                        + Exam({{ maxExam }})
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Excel-Style Score Spreadsheet Grid -->
        <Card class="border-border/60 shadow-xs overflow-hidden">
            <CardHeader class="px-6 py-4 border-b border-border/40 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <CardTitle class="text-base font-semibold flex items-center gap-2">
                        <span>{{ currentSubject?.name ?? 'Subject' }} Mark Sheet</span>
                        <Badge variant="outline" class="text-xs font-mono">
                            {{ currentClass?.name }} - Section {{ currentSection?.name }}
                        </Badge>
                    </CardTitle>
                    <CardDescription>
                        Use <kbd class="px-1.5 py-0.5 rounded bg-muted text-[10px] font-mono border">Tab</kbd> or
                        <kbd class="px-1.5 py-0.5 rounded bg-muted text-[10px] font-mono border">Enter</kbd> to move rapidly between cells.
                    </CardDescription>
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative w-full sm:w-64">
                        <Search class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="tableFilterQuery"
                            placeholder="Filter student list..."
                            class="pl-8 h-9 text-xs"
                        />
                    </div>
                </div>
            </CardHeader>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-muted/50 border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase tracking-wider select-none">
                        <tr>
                            <th class="w-12 px-3 py-3 text-center">#</th>
                            <th class="w-32 px-4 py-3">Admission #</th>
                            <th class="min-w-[180px] px-4 py-3">Student Name</th>
                            <th class="w-24 px-2 py-3 text-center bg-blue-500/5">
                                {{ ca1Label }} ({{ maxCa1 }})
                            </th>
                            <th v-if="caCount >= 2" class="w-24 px-2 py-3 text-center bg-blue-500/5">
                                {{ ca2Label }} ({{ maxCa2 }})
                            </th>
                            <th v-if="caCount >= 3" class="w-24 px-2 py-3 text-center bg-blue-500/5">
                                {{ ca3Label }} ({{ maxCa3 }})
                            </th>
                            <th class="w-28 px-2 py-3 text-center bg-purple-500/5">
                                Exam ({{ maxExam }})
                            </th>
                            <th class="w-20 px-3 py-3 text-center bg-muted/80">
                                Total ({{ maxTotal }})
                            </th>
                            <th class="w-20 px-3 py-3 text-center">Grade</th>
                            <th class="w-28 px-3 py-3">Remark</th>
                            <th class="min-w-[200px] px-4 py-3">Teacher Remark</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr
                            v-for="(row, idx) in displayedRows"
                            :key="row.enrollment_id"
                            class="hover:bg-muted/20 transition-colors"
                        >
                            <!-- Row Index -->
                            <td class="px-3 py-2 text-center text-xs font-mono text-muted-foreground">
                                {{ idx + 1 }}
                            </td>

                            <!-- Admission Number -->
                            <td class="px-4 py-2 font-mono text-xs font-medium text-primary">
                                {{ row.admission_number }}
                            </td>

                            <!-- Student Name -->
                            <td class="px-4 py-2 font-medium text-foreground">
                                <div class="truncate max-w-[220px]" :title="row.student_name">
                                    {{ row.student_name }}
                                </div>
                                <div class="text-[10px] text-muted-foreground font-mono">
                                    Adm: {{ row.admission_number }}
                                </div>
                            </td>

                            <!-- CA 1 Input -->
                            <td class="px-2 py-1.5 text-center bg-blue-500/5">
                                <input
                                    :id="`cell-${idx}-ca1`"
                                    v-model.number="row.ca1"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    :max="maxCa1"
                                    class="w-16 h-8 text-center text-sm font-semibold rounded-md border bg-background transition-colors focus:ring-2 focus:ring-primary focus:outline-hidden"
                                    :class="{
                                        'border-destructive text-destructive bg-destructive/5': Number(row.ca1) > maxCa1,
                                        'border-border/60': Number(row.ca1) <= maxCa1,
                                    }"
                                    @input="() => onScoreChange(row, 'ca1', maxCa1)"
                                    @keydown="(e) => handleKeyDown(e, idx, 'ca1')"
                                />
                            </td>

                            <!-- CA 2 Input -->
                            <td v-if="caCount >= 2" class="px-2 py-1.5 text-center bg-blue-500/5">
                                <input
                                    :id="`cell-${idx}-ca2`"
                                    v-model.number="row.ca2"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    :max="maxCa2"
                                    class="w-16 h-8 text-center text-sm font-semibold rounded-md border bg-background transition-colors focus:ring-2 focus:ring-primary focus:outline-hidden"
                                    :class="{
                                        'border-destructive text-destructive bg-destructive/5': Number(row.ca2) > maxCa2,
                                        'border-border/60': Number(row.ca2) <= maxCa2,
                                    }"
                                    @input="() => onScoreChange(row, 'ca2', maxCa2)"
                                    @keydown="(e) => handleKeyDown(e, idx, 'ca2')"
                                />
                            </td>

                            <!-- CA 3 Input -->
                            <td v-if="caCount >= 3" class="px-2 py-1.5 text-center bg-blue-500/5">
                                <input
                                    :id="`cell-${idx}-ca3`"
                                    v-model.number="row.ca3"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    :max="maxCa3"
                                    class="w-16 h-8 text-center text-sm font-semibold rounded-md border bg-background transition-colors focus:ring-2 focus:ring-primary focus:outline-hidden"
                                    :class="{
                                        'border-destructive text-destructive bg-destructive/5': Number(row.ca3) > maxCa3,
                                        'border-border/60': Number(row.ca3) <= maxCa3,
                                    }"
                                    @input="() => onScoreChange(row, 'ca3', maxCa3)"
                                    @keydown="(e) => handleKeyDown(e, idx, 'ca3')"
                                />
                            </td>

                            <!-- Exam Score Input -->
                            <td class="px-2 py-1.5 text-center bg-purple-500/5">
                                <input
                                    :id="`cell-${idx}-exam_score`"
                                    v-model.number="row.exam_score"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    :max="maxExam"
                                    class="w-20 h-8 text-center text-sm font-bold rounded-md border bg-background transition-colors focus:ring-2 focus:ring-primary focus:outline-hidden"
                                    :class="{
                                        'border-destructive text-destructive bg-destructive/5': Number(row.exam_score) > maxExam,
                                        'border-border/60': Number(row.exam_score) <= maxExam,
                                    }"
                                    @input="() => onScoreChange(row, 'exam_score', maxExam)"
                                    @keydown="(e) => handleKeyDown(e, idx, 'exam_score')"
                                />
                            </td>

                            <!-- Computed Total -->
                            <td class="px-3 py-2 text-center font-bold text-sm bg-muted/30">
                                <span
                                    :class="{
                                        'text-emerald-600 dark:text-emerald-400': calculateRowTotal(row) >= 60,
                                        'text-blue-600 dark:text-blue-400': calculateRowTotal(row) >= 50 && calculateRowTotal(row) < 60,
                                        'text-amber-600 dark:text-amber-400': calculateRowTotal(row) >= 40 && calculateRowTotal(row) < 50,
                                        'text-destructive': calculateRowTotal(row) < 40,
                                    }"
                                >
                                    {{ calculateRowTotal(row) }}
                                </span>
                            </td>

                            <!-- Dynamic Letter Grade -->
                            <td class="px-3 py-2 text-center">
                                <Badge
                                    variant="outline"
                                    class="font-mono font-bold text-xs"
                                    :class="{
                                        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200': resolveGrade(calculateRowTotal(row)).name.startsWith('A') || resolveGrade(calculateRowTotal(row)).name.startsWith('B'),
                                        'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200': resolveGrade(calculateRowTotal(row)).name.startsWith('C'),
                                        'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200': resolveGrade(calculateRowTotal(row)).name.startsWith('D') || resolveGrade(calculateRowTotal(row)).name.startsWith('E'),
                                        'bg-destructive/10 text-destructive border-destructive/20': resolveGrade(calculateRowTotal(row)).name.startsWith('F'),
                                    }"
                                >
                                    {{ resolveGrade(calculateRowTotal(row)).name }}
                                </Badge>
                            </td>

                            <!-- Remark -->
                            <td class="px-3 py-2 text-xs font-medium text-muted-foreground">
                                {{ resolveGrade(calculateRowTotal(row)).remark }}
                            </td>

                            <!-- Teacher Comment -->
                            <td class="px-4 py-1.5">
                                <input
                                    :id="`cell-${idx}-teacher_comment`"
                                    v-model="row.teacher_comment"
                                    :placeholder="resolveGrade(calculateRowTotal(row)).remark !== '—' ? resolveGrade(calculateRowTotal(row)).remark : 'Performance remark...'"
                                    class="w-full h-8 px-2 text-xs rounded-md border border-border/60 bg-background focus:ring-1 focus:ring-primary focus:outline-hidden"
                                    @input="() => (hasUnsavedChanges = true)"
                                    @keydown="(e) => handleKeyDown(e, idx, 'teacher_comment')"
                                />
                            </td>
                        </tr>

                        <tr v-if="displayedRows.length === 0">
                            <td colspan="11" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <FileSpreadsheet class="h-8 w-8 text-muted-foreground/60" />
                                    <p v-if="classes.length === 0" class="font-medium">No classes allocated to your teaching profile for this term.</p>
                                    <p v-else-if="subjects.length === 0" class="font-medium">No subjects allocated to {{ currentClass?.name || 'this class' }}.</p>
                                    <p v-else class="font-medium">No students enrolled in this class section.</p>
                                    <p v-if="classes.length === 0" class="text-xs">Contact the school administrator or examination officer to assign subjects to your account.</p>
                                    <p v-else-if="subjects.length === 0" class="text-xs">Allocate subjects to this class under Curriculum / Class Subjects to record marks.</p>
                                    <p v-else class="text-xs">Select another class section or enroll students to begin continuous assessment.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Save Bar -->
            <div class="p-4 border-t border-border/40 bg-muted/20 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <div
                        class="h-2 w-2 rounded-full"
                        :class="hasUnsavedChanges ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"
                    />
                    <span v-if="hasUnsavedChanges" class="text-amber-600 font-medium">
                        Unsaved changes pending. Click "Save All Marks" to commit.
                    </span>
                    <span v-else-if="lastSavedTime">
                        All marks committed at {{ lastSavedTime }}.
                    </span>
                    <span v-else>
                        Ready for data entry.
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        class="gap-2 font-semibold"
                        :disabled="isAutosaving || displayedRows.length === 0"
                        @click="() => saveMarks(false)"
                    >
                        <Spinner v-if="isAutosaving" class="h-4 w-4" />
                        <Save v-else class="h-4 w-4" />
                        <span>Save All Marks</span>
                    </Button>
                </div>
            </div>
        </Card>

        <!-- Grading Scale Reference Dialog -->
        <Dialog v-model:open="isGradeGuideOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Award class="h-5 w-5 text-primary" />
                        <span>Institutional Grading Scale</span>
                    </DialogTitle>
                    <DialogDescription>
                        Standard 9-point Secondary grading system used for terminal calculation.
                    </DialogDescription>
                </DialogHeader>

                <div class="divide-y divide-border/40 border border-border/60 rounded-md overflow-hidden text-sm">
                    <div
                        v-for="g in grades"
                        :key="g.id"
                        class="flex items-center justify-between px-4 py-2.5 hover:bg-muted/30"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-bold font-mono text-base text-foreground w-8">
                                {{ g.name }}
                            </span>
                            <span class="text-xs text-muted-foreground font-mono">
                                ({{ g.mark_from }}% - {{ g.mark_to }}%)
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="font-medium text-xs">{{ g.remark }}</span>
                            <div class="text-[10px] text-muted-foreground font-mono">
                                {{ g.grade_point }} pts
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isGradeGuideOpen = false"
                    >
                        Close Guide
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
