<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Award,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Download,
    ExternalLink,
    Eye,
    FileDown,
    FileText,
    Filter,
    GraduationCap,
    Info,
    Loader2,
    Printer,
    Search,
    Sliders,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    generateClassBatchReportPdf,
    type BatchReportCardData,
} from '@/services/reportCardPdfGenerator';
import { index as academicReportCardsIndex, show as academicReportCardsShow } from '@/routes/academic/report-cards';
import type { AcademicSession, AcademicTerm, SchoolClass, Section } from '@/types/academic';

interface StudentRosterItem {
    id: number;
    student_id: number;
    admission_number: string;
    student_name: string;
    gender: string;
    roll_number: string | null;
    class_name: string;
    section_name: string;
    subjects_evaluated: number;
    average_score: number | null;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    sessions: AcademicSession[];
    classes: (SchoolClass & { sections: Section[] })[];
    selectedSessionId: number;
    selectedTermId: number;
    selectedClassId: number;
    selectedSectionId: number;
    students: PaginatedData<StudentRosterItem>;
    filters: {
        search?: string;
    };
}

const props = defineProps<Props>();

const selectedSessionId = ref(props.selectedSessionId);
const selectedTermId = ref(props.selectedTermId);
const selectedClassId = ref(props.selectedClassId);
const selectedSectionId = ref(props.selectedSectionId);
const search = ref(props.filters.search || '');

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

const applyFilters = () => {
    router.get(
        academicReportCardsIndex(),
        {
            session_id: selectedSessionId.value,
            term_id: selectedTermId.value,
            class_id: selectedClassId.value,
            section_id: selectedSectionId.value,
            search: search.value.trim() || undefined,
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

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// -----------------------------------------------------------------------------
// Batch PDF Generation (Single Class PDF with Dynamic Letterhead & Margins)
// -----------------------------------------------------------------------------
const isBatchModalOpen = ref(false);
const isFetchingBatch = ref(false);
const isGeneratingPdf = ref(false);
const batchData = ref<BatchReportCardData | null>(null);

const pdfStyle = ref<'minimal' | 'comprehensive'>('comprehensive');
const fontPairing = ref<'academic_serif' | 'modern_sans' | 'classic_formal'>('academic_serif');
const useLetterheadImage = ref(true);

const openBatchModal = async () => {
    isBatchModalOpen.value = true;
    isFetchingBatch.value = true;
    try {
        const res = await fetch(
            `/academic/report-cards/batch-data?session_id=${selectedSessionId.value}&term_id=${selectedTermId.value}&class_id=${selectedClassId.value}&section_id=${selectedSectionId.value}`
        );
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.error || 'Failed to fetch class batch data.');
        }
        const json: BatchReportCardData = await res.json();
        batchData.value = json;
        useLetterheadImage.value = Boolean(json.school.letterhead_base64);
    } catch (err: any) {
        toast.error(err.message || 'Error loading batch data.');
        isBatchModalOpen.value = false;
    } finally {
        isFetchingBatch.value = false;
    }
};

const handleGeneratePdf = () => {
    if (!batchData.value) return;
    if (batchData.value.students.length === 0) {
        toast.error('No students found in this class/section to generate report cards for.');
        return;
    }

    isGeneratingPdf.value = true;
    try {
        generateClassBatchReportPdf(batchData.value, {
            useLetterheadImage: useLetterheadImage.value,
            style: pdfStyle.value,
            fontPairing: fontPairing.value,
        });
        toast.success(`Generated batch report cards for ${batchData.value.students.length} students!`);
        isBatchModalOpen.value = false;
    } catch (err: any) {
        toast.error(err.message || 'Error generating batch PDF');
    } finally {
        isGeneratingPdf.value = false;
    }
};
</script>

<template>
    <Head title="Terminal Report Cards Directory" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl flex items-center gap-2.5">
                    <FileText class="h-7 w-7 text-primary" />
                    <span>Terminal Report Cards</span>
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Generate and print official terminal report cards with institutional branding, marks, and attendance.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    class="gap-2 text-xs font-semibold shadow-xs"
                    :disabled="students.total === 0"
                    @click="openBatchModal"
                >
                    <FileDown class="size-4" />
                    <span>Download Class Batch PDF</span>
                </Button>
            </div>
        </div>

        <!-- Filter Bar -->
        <Card class="border shadow-sm">
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
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Term</Label>
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

        <!-- Student Roster Directory -->
        <Card class="border shadow-sm">
            <CardHeader class="p-4 sm:p-6 pb-2 sm:pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b">
                <div>
                    <CardTitle class="text-base sm:text-lg flex items-center gap-2">
                        <span>Students Roster</span>
                        <Badge variant="secondary" class="font-normal text-xs">
                            {{ students.total }} Students
                        </Badge>
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">
                        Select an enrolled student to view and print their terminal assessment sheet.
                    </CardDescription>
                </div>

                <div class="w-full sm:w-72">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="search"
                            placeholder="Search by student or admission..."
                            class="pl-9 h-9 text-xs"
                        />
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <!-- Empty State -->
                <div
                    v-if="students.data.length === 0"
                    class="flex flex-col items-center justify-center p-12 text-center"
                >
                    <div class="rounded-full bg-muted p-4 mb-3">
                        <Users class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <h3 class="text-base font-semibold text-foreground">No students found</h3>
                    <p class="text-xs text-muted-foreground max-w-sm mt-1">
                        {{
                            search
                                ? 'No student matches your search query in this section.'
                                : 'No student enrollments exist for this class and section.'
                        }}
                    </p>
                </div>

                <!-- Table -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4 min-w-[200px]">Candidate Details</th>
                                <th class="py-3 px-4 min-w-[140px]">Class & Section</th>
                                <th class="py-3 px-4 min-w-[130px] text-center">Evaluated Subjects</th>
                                <th class="py-3 px-4 min-w-[110px] text-center">Term Average</th>
                                <th class="py-3 px-4 min-w-[140px] text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="(student, index) in students.data"
                                :key="student.id"
                                class="transition-colors hover:bg-muted/20"
                            >
                                <td class="py-3 px-4 text-center text-xs font-medium text-muted-foreground">
                                    {{ (students.from ? students.from + index : index + 1) }}
                                </td>

                                <td class="py-3 px-4">
                                    <div class="font-medium text-foreground">
                                        {{ student.student_name }}
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5 text-xs text-muted-foreground font-mono">
                                        <span>{{ student.admission_number }}</span>
                                        <span class="capitalize text-[11px] px-1.5 py-0.2 rounded bg-muted font-normal font-sans">
                                            {{ student.gender }}
                                        </span>
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    <span class="font-medium text-foreground">{{ student.class_name }}</span>
                                    <span class="text-muted-foreground ml-1">({{ student.section_name }})</span>
                                </td>

                                <td class="py-3 px-4 text-center text-xs">
                                    <Badge
                                        :variant="student.subjects_evaluated > 0 ? 'secondary' : 'outline'"
                                        class="font-mono text-xs"
                                    >
                                        {{ student.subjects_evaluated }} Subjects
                                    </Badge>
                                </td>

                                <td class="py-3 px-4 text-center font-mono text-xs font-semibold">
                                    <span
                                        v-if="student.average_score !== null"
                                        :class="student.average_score >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                    >
                                        {{ student.average_score }}%
                                    </span>
                                    <span v-else class="text-muted-foreground/40 font-normal">-</span>
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <Link
                                        :href="academicReportCardsShow(student.id, { query: { term_id: selectedTermId } })"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold bg-primary text-primary-foreground hover:bg-primary/90 transition-all shadow-xs"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                        <span>View Report Card</span>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer (15 per page standard) -->
                <div
                    v-if="students.data.length > 0"
                    class="border-t p-4 sm:px-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-xs text-muted-foreground"
                >
                    <div>
                        Showing <span class="font-medium text-foreground">{{ students.from || 0 }}</span> to
                        <span class="font-medium text-foreground">{{ students.to || 0 }}</span> of
                        <span class="font-medium text-foreground">{{ students.total }}</span> students
                    </div>

                    <div class="flex items-center gap-1">
                        <Link
                            v-for="(link, i) in students.links"
                            :key="i"
                            :href="link.url || '#'"
                            class="px-2.5 py-1.5 rounded-md text-xs transition-colors"
                            :class="{
                                'bg-primary text-primary-foreground font-semibold pointer-events-none': link.active,
                                'text-muted-foreground hover:bg-muted hover:text-foreground': !link.active && link.url,
                                'opacity-40 pointer-events-none': !link.url,
                            }"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- BATCH REPORT CARD PDF CONFIGURATION MODAL -->
        <Dialog :open="isBatchModalOpen" @update:open="(val) => isBatchModalOpen = val">
            <DialogContent class="sm:max-w-xl max-h-[90vh] flex flex-col p-0 overflow-hidden gap-0">
                <DialogHeader class="p-5 sm:p-6 pb-4 border-b border-border shrink-0 pr-12">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
                            <FileDown class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-bold">Download Class Batch Report Cards</DialogTitle>
                            <DialogDescription class="text-xs">
                                Compile and download all student report cards for this class into a single consolidated PDF document.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="flex-1 overflow-y-auto min-h-0 p-5 sm:p-6 space-y-5 text-xs">
                    <!-- Fetching Spinner -->
                    <div v-if="isFetchingBatch" class="flex flex-col items-center justify-center py-10 space-y-3">
                        <Loader2 class="size-8 animate-spin text-primary" />
                        <span class="text-xs text-muted-foreground font-medium">Fetching class records and compiling rankings...</span>
                    </div>

                    <template v-else-if="batchData">
                        <!-- Class Scope Overview Pill -->
                        <div class="rounded-lg border border-border bg-muted/30 p-3.5 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-foreground text-sm">
                                    {{ batchData.class_name }} - {{ batchData.section_name }}
                                </span>
                                <Badge variant="secondary" class="font-mono text-xs">
                                    {{ batchData.students.length }} Students
                                </Badge>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-muted-foreground text-[11px]">
                                <span>Session: <strong class="text-foreground">{{ batchData.session_name }}</strong></span>
                                <span>•</span>
                                <span>Term: <strong class="text-foreground">{{ batchData.term_name }}</strong></span>
                                <span>•</span>
                                <span>Exam: <strong class="text-foreground">{{ batchData.exam.name || 'Terminal Exam' }}</strong></span>
                            </div>
                        </div>

                        <!-- Report Card Format / Presentation Style -->
                        <div class="space-y-2">
                            <Label class="text-xs font-bold block">Report Card Layout Style</Label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                                    :class="pdfStyle === 'minimal' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:bg-muted/30'"
                                >
                                    <input type="radio" v-model="pdfStyle" value="minimal" class="mt-0.5 text-primary" />
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-foreground text-xs">Minimalist Style</div>
                                        <div class="text-[11px] text-muted-foreground leading-relaxed">
                                            Clean marks table, total score, class position, remarks, and signature lines on letterhead.
                                        </div>
                                    </div>
                                </label>

                                <label
                                    class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                                    :class="pdfStyle === 'comprehensive' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:bg-muted/30'"
                                >
                                    <input type="radio" v-model="pdfStyle" value="comprehensive" class="mt-0.5 text-primary" />
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-foreground text-xs">Comprehensive Style</div>
                                        <div class="text-[11px] text-muted-foreground leading-relaxed">
                                            Includes full attendance rate breakdown and class benchmark averages.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Typography & Font Pairing Option -->
                        <div class="space-y-2">
                            <Label class="text-xs font-bold block">Typography & Font Styling</Label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <label
                                    class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                    :class="fontPairing === 'academic_serif' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:bg-muted/30'"
                                >
                                    <input type="radio" v-model="fontPairing" value="academic_serif" class="mt-0.5 text-primary" />
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-foreground text-xs flex items-center gap-1.5">
                                            <span>Academic Serif</span>
                                            <Badge variant="outline" class="text-[9px] px-1 py-0 h-4 border-primary/30 text-primary">Rich</Badge>
                                        </div>
                                        <div class="text-[11px] text-muted-foreground leading-relaxed">
                                            Authoritative Times-Roman titles & remarks paired with clean Helvetica tabular scores.
                                        </div>
                                    </div>
                                </label>

                                <label
                                    class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                    :class="fontPairing === 'modern_sans' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:bg-muted/30'"
                                >
                                    <input type="radio" v-model="fontPairing" value="modern_sans" class="mt-0.5 text-primary" />
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-foreground text-xs">Modern Sans</div>
                                        <div class="text-[11px] text-muted-foreground leading-relaxed">
                                            Crisp Helvetica throughout, matching the web portal's contemporary aesthetic.
                                        </div>
                                    </div>
                                </label>

                                <label
                                    class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                    :class="fontPairing === 'classic_formal' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:bg-muted/30'"
                                >
                                    <input type="radio" v-model="fontPairing" value="classic_formal" class="mt-0.5 text-primary" />
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-foreground text-xs">Classic Formal</div>
                                        <div class="text-[11px] text-muted-foreground leading-relaxed">
                                            Full collegiate serif typography across all headers, marks, and remarks.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Institutional Letterhead Option -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label class="text-xs font-bold">Institutional Letterhead Mode</Label>
                                <Badge
                                    v-if="batchData.school.letterhead_base64"
                                    variant="outline"
                                    class="border-emerald-500 text-emerald-600 dark:text-emerald-400 text-[10px]"
                                >
                                    ✓ Letterhead Stored in DB
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="border-amber-500 text-amber-600 dark:text-amber-400 text-[10px]"
                                >
                                    No Letterhead Stored (Upload in School Settings)
                                </Badge>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                                    :class="[
                                        useLetterheadImage ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:bg-muted/30',
                                        !batchData.school.letterhead_base64 ? 'opacity-60 cursor-not-allowed' : ''
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        :value="true"
                                        v-model="useLetterheadImage"
                                        :disabled="!batchData.school.letterhead_base64"
                                        class="mt-0.5 text-primary"
                                    />
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-foreground text-xs">Digital Letterhead</div>
                                        <div class="text-[11px] text-muted-foreground leading-relaxed">
                                            Renders the stored letterhead image as the full-page background.
                                        </div>
                                    </div>
                                </label>

                                <label
                                    class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                                    :class="!useLetterheadImage ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border hover:bg-muted/30'"
                                >
                                    <input
                                        type="radio"
                                        :value="false"
                                        v-model="useLetterheadImage"
                                        class="mt-0.5 text-primary"
                                    />
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-foreground text-xs">Pre-Printed Paper</div>
                                        <div class="text-[11px] text-muted-foreground leading-relaxed">
                                            Omits digital image but maintains margin offsets for feeding office stationery.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </template>
                </div>

                <DialogFooter class="p-4 sm:px-6 border-t border-border shrink-0 bg-muted/20 flex items-center justify-between sm:justify-between w-full">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="isBatchModalOpen = false"
                    >
                        Cancel
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        class="gap-1.5 text-xs font-semibold"
                        :disabled="isFetchingBatch || isGeneratingPdf || !batchData || batchData.students.length === 0"
                        @click="handleGeneratePdf"
                    >
                        <Loader2 v-if="isGeneratingPdf" class="size-4 animate-spin" />
                        <Download v-else class="size-4" />
                        <span>{{ isGeneratingPdf ? 'Compiling PDF...' : 'Generate & Download PDF' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
