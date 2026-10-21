<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    CheckCircle2,
    GraduationCap,
    Info,
    RotateCcw,
    TrendingUp,
    UserCheck,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { index as academicPromotionsIndex, store as storePromotion } from '@/routes/academic/promotions';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface StudentUser {
    id: number;
    name: string;
}

interface Student {
    id: number;
    user_id: number;
    admission_number: string;
    user?: StudentUser;
    enrollments?: Enrollment[];
}

interface Section {
    id: number;
    class_id: number;
    name: string;
    capacity: number;
}

interface SchoolClass {
    id: number;
    name: string;
    stage: string;
    level_order: number;
    sections?: Section[];
}

interface AcademicSession {
    id: number;
    name: string;
    is_current: boolean;
}

interface Enrollment {
    id: number;
    student_id: number;
    academic_session_id: number;
    class_id: number;
    section_id: number;
    status: string;
    student?: Student;
    section?: Section;
    school_class?: SchoolClass;
    academic_session?: AcademicSession;
}

interface Stats {
    total_in_session: number;
    active_count: number;
    promoted_count: number;
    repeated_count: number;
    graduated_count: number;
}

interface StudentPerformance {
    term1_avg: number | null;
    term2_avg: number | null;
    term3_avg: number | null;
    annual_avg: number | null;
    grade: string | null;
    terms_sat_count: number;
}

const props = defineProps<{
    sessions: AcademicSession[];
    selectedSessionId: number;
    classes: SchoolClass[];
    selectedClassId: number;
    selectedSectionId: number;
    enrollments: Enrollment[];
    studentPerformanceMap: Record<number, StudentPerformance>;
    stats: Stats;
}>();

const page = usePage();
const permissions = computed<string[]>(() => (page.props.auth as any)?.permissions ?? []);
const isSuperAdmin = computed<boolean>(() => (page.props.auth as any)?.is_superadmin ?? false);
const canPromote = computed<boolean>(() => isSuperAdmin.value || permissions.value.includes('enrollments.promote'));

const currentSession = computed(() =>
    props.sessions.find((s) => s.id === props.selectedSessionId) ?? props.sessions[0],
);

// Promotion Form
const promotionForm = useForm({
    source_session_id: props.selectedSessionId,
    target_session_id: '' as string | number,
    action: 'promote', // 'promote' | 'repeat' | 'graduate'
    target_class_id: '' as string | number,
    target_section_id: '' as string | number,
    student_ids: [] as number[],
});

// Source Class & Section Filter
const promoSourceClassId = ref<string>(props.selectedClassId ? String(props.selectedClassId) : '');
const promoSourceSectionId = ref<string>(props.selectedSectionId ? String(props.selectedSectionId) : '');

watch(
    () => props.selectedClassId,
    (val) => {
        promoSourceClassId.value = val ? String(val) : '';
    }
);

watch(
    () => props.selectedSectionId,
    (val) => {
        promoSourceSectionId.value = val ? String(val) : '';
    }
);

const onSourceClassChange = () => {
    promoSourceSectionId.value = '';
    promotionForm.student_ids = [];
    router.get(
        academicPromotionsIndex.url({
            query: {
                session_id: props.selectedSessionId,
                class_id: promoSourceClassId.value || undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true }
    );
};

const onSourceSectionChange = () => {
    promotionForm.student_ids = [];
    router.get(
        academicPromotionsIndex.url({
            query: {
                session_id: props.selectedSessionId,
                class_id: promoSourceClassId.value || undefined,
                section_id: promoSourceSectionId.value || undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true }
    );
};

const availableSourceSections = computed(() => {
    if (!promoSourceClassId.value) return [];
    const cls = props.classes.find((c) => c.id === Number(promoSourceClassId.value));
    return cls?.sections ?? [];
});

const candidateTab = ref<'active' | 'processed' | 'all'>('active');

const getProgressionDestination = (enrollment: Enrollment): Enrollment | null => {
    if (!enrollment.student?.enrollments) return null;
    return enrollment.student.enrollments.find(
        (target) => target.academic_session_id !== enrollment.academic_session_id && target.id > enrollment.id
    ) ?? null;
};

// All Candidates Enrolled in Source Class & Session (Filtered server-side)
const allSourceCandidates = computed(() => {
    return props.enrollments;
});

const activeCandidates = computed(() => {
    return allSourceCandidates.value.filter((e) => e.status === 'active');
});

const processedCandidates = computed(() => {
    return allSourceCandidates.value.filter((e) => e.status !== 'active');
});

const displayedCandidates = computed(() => {
    if (candidateTab.value === 'active') return activeCandidates.value;
    if (candidateTab.value === 'processed') return processedCandidates.value;
    return allSourceCandidates.value;
});

// Pass Benchmark & Performance Decision Support
const passBenchmark = ref<number>(50);

const getPerformance = (studentId: number): StudentPerformance | undefined => {
    return props.studentPerformanceMap?.[studentId];
};

const isEligibleForPromotion = (studentId: number): boolean => {
    const perf = getPerformance(studentId);
    if (!perf || perf.annual_avg === null) return true;
    return perf.annual_avg >= passBenchmark.value;
};

const selectRecommended = () => {
    if (promotionForm.action === 'repeat') {
        promotionForm.student_ids = activeCandidates.value
            .filter((c) => !isEligibleForPromotion(c.student_id))
            .map((c) => c.student_id);
    } else {
        promotionForm.student_ids = activeCandidates.value
            .filter((c) => isEligibleForPromotion(c.student_id))
            .map((c) => c.student_id);
    }
};

const recommendedCount = computed(() => {
    if (promotionForm.action === 'repeat') {
        return activeCandidates.value.filter((c) => !isEligibleForPromotion(c.student_id)).length;
    }
    return activeCandidates.value.filter((c) => isEligibleForPromotion(c.student_id)).length;
});

const isAllSelected = computed(() => {
    if (activeCandidates.value.length === 0) return false;
    return activeCandidates.value.every((e) => promotionForm.student_ids.includes(e.student_id));
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        promotionForm.student_ids = [];
    } else {
        promotionForm.student_ids = activeCandidates.value.map((e) => e.student_id);
    }
};

const toggleCandidate = (candidate: Enrollment) => {
    if (candidate.status !== 'active') return;
    const idx = promotionForm.student_ids.indexOf(candidate.student_id);
    if (idx > -1) {
        promotionForm.student_ids.splice(idx, 1);
    } else {
        promotionForm.student_ids.push(candidate.student_id);
    }
};

const switchSession = (sessionId: number | string) => {
    router.get(
        academicPromotionsIndex.url({ query: { session_id: sessionId } }),
        {},
        { preserveState: false, preserveScroll: true },
    );
};

const availableTargetSections = computed(() => {
    if (!promotionForm.target_class_id) return [];
    const cls = props.classes.find((c) => c.id === Number(promotionForm.target_class_id));
    return cls?.sections ?? [];
});

watch(
    () => promotionForm.target_class_id,
    () => {
        promotionForm.target_section_id = '';
    },
);

// Auto-suggest next class when source class changes in 'promote' mode
watch(promoSourceClassId, (sourceId) => {
    if (promotionForm.action === 'promote' && sourceId) {
        const currentClass = props.classes.find((c) => c.id === Number(sourceId));
        if (currentClass) {
            const nextClass = props.classes.find((c) => c.level_order === currentClass.level_order + 1);
            if (nextClass) {
                promotionForm.target_class_id = nextClass.id;
            }
        }
    } else if (promotionForm.action === 'repeat' && sourceId) {
        promotionForm.target_class_id = Number(sourceId);
    }
}, { immediate: true });

watch(
    () => promotionForm.action,
    (action) => {
        if (action === 'repeat' && promoSourceClassId.value) {
            promotionForm.target_class_id = Number(promoSourceClassId.value);
        } else if (action === 'promote' && promoSourceClassId.value) {
            const currentClass = props.classes.find((c) => c.id === Number(promoSourceClassId.value));
            if (currentClass) {
                const nextClass = props.classes.find((c) => c.level_order === currentClass.level_order + 1);
                if (nextClass) {
                    promotionForm.target_class_id = nextClass.id;
                }
            }
        } else if (action === 'graduate') {
            promotionForm.target_class_id = '';
            promotionForm.target_section_id = '';
        }
    },
);

const isConfirmModalOpen = ref(false);

const openConfirmModal = () => {
    isConfirmModalOpen.value = true;
};

const sourceSessionName = computed(() => {
    return props.sessions.find((s) => s.id === Number(promotionForm.source_session_id))?.name ?? 'Current Session';
});

const targetSessionName = computed(() => {
    return props.sessions.find((s) => s.id === Number(promotionForm.target_session_id))?.name ?? 'Target Session';
});

const sourceClassName = computed(() => {
    return props.classes.find((c) => c.id === Number(promoSourceClassId.value))?.name ?? 'Selected Class';
});

const sourceSectionName = computed(() => {
    if (!promoSourceSectionId.value) return 'All Sections';
    return availableSourceSections.value.find((s) => s.id === Number(promoSourceSectionId.value))?.name ?? 'Section';
});

const targetClassName = computed(() => {
    if (promotionForm.action === 'graduate') return 'Alumni / Graduated';
    return props.classes.find((c) => c.id === Number(promotionForm.target_class_id))?.name ?? 'Target Class';
});

const targetSectionName = computed(() => {
    if (promotionForm.action === 'graduate') return 'N/A';
    return availableTargetSections.value.find((s) => s.id === Number(promotionForm.target_section_id))?.name ?? 'Target Section';
});

const selectedCandidateDetails = computed(() => {
    return allSourceCandidates.value.filter((e) => promotionForm.student_ids.includes(e.student_id));
});

watch(
    () => promotionForm.source_session_id,
    (newSessionId) => {
        if (newSessionId && Number(newSessionId) !== props.selectedSessionId) {
            switchSession(newSessionId);
        }
    },
);

const confirmAndExecutePromotion = () => {
    promotionForm.post(storePromotion.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isConfirmModalOpen.value = false;
            promotionForm.student_ids = [];
            toast.success('Batch progression completed successfully.');
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            toast.error(firstError || 'Failed to execute promotion batch. Please check selection and requirements.');
        },
    });
};
</script>

<template>
    <Head title="Student Promotion & Progression Engine" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Student Promotion
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Bulk promote, repeat, or graduate students across academic sessions and class grades.
                </p>
            </div>

            <!-- Academic Session Switcher -->
            <div class="flex items-center gap-2 self-start sm:self-auto rounded-lg border border-border/70 bg-card p-1.5 shadow-xs">
                <span class="text-xs font-semibold text-muted-foreground px-2">Viewing Session:</span>
                <select
                    :value="selectedSessionId"
                    @change="(e) => switchSession(Number((e.target as HTMLSelectElement).value))"
                    class="h-8 rounded-md border border-input bg-background px-3 py-1 text-xs font-semibold text-foreground shadow-xs focus:ring-1 focus:ring-primary"
                >
                    <option v-for="s in sessions" :key="s.id" :value="s.id">
                        {{ s.name }} {{ s.is_current ? '(Current Active)' : '' }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Session Students</CardDescription>
                    <CardTitle class="text-2xl font-bold text-foreground">
                        {{ stats.total_in_session }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">Enrolled in {{ currentSession?.name }}</p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Active Enrolled</CardDescription>
                    <CardTitle class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ stats.active_count }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">Currently attending</p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Promoted Ahead</CardDescription>
                    <CardTitle class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ stats.promoted_count }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">Advanced to higher grade</p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Repeated / Graduated</CardDescription>
                    <CardTitle class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ stats.repeated_count }} / {{ stats.graduated_count }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">Retained vs Alumni</p>
                </CardContent>
            </Card>
        </div>

        <!-- PROMOTION ENGINE WORKFLOW -->
        <Card class="border-border/60 shadow-xs">
            <CardHeader class="pb-3 border-b border-border/40">
                <div class="flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-primary" />
                    <div>
                        <CardTitle class="text-base font-semibold">Institutional Progression Configuration</CardTitle>
                        <CardDescription class="text-xs">
                            Follow steps 1 through 3 to execute bulk promotion into the target academic session.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-4 sm:p-6 space-y-6">
                <!-- Action Type Selector Pills -->
                <div class="space-y-2">
                    <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Progression Action
                    </Label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button
                            type="button"
                            @click="promotionForm.action = 'promote'"
                            :class="[
                                'flex items-center gap-3 p-3 rounded-lg border text-left transition-all',
                                promotionForm.action === 'promote'
                                    ? 'border-primary bg-primary/10 text-primary font-semibold ring-2 ring-primary/20'
                                    : 'border-border hover:bg-muted/50 text-muted-foreground'
                            ]"
                        >
                            <TrendingUp class="size-5 shrink-0" />
                            <div>
                                <div class="text-sm">Promote to Next Class</div>
                                <div class="text-xs font-normal text-muted-foreground">Advance eligible students</div>
                            </div>
                        </button>

                        <button
                            type="button"
                            @click="promotionForm.action = 'repeat'"
                            :class="[
                                'flex items-center gap-3 p-3 rounded-lg border text-left transition-all',
                                promotionForm.action === 'repeat'
                                    ? 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-amber-400 font-semibold ring-2 ring-amber-500/20'
                                    : 'border-border hover:bg-muted/50 text-muted-foreground'
                            ]"
                        >
                            <RotateCcw class="size-5 shrink-0" />
                            <div>
                                <div class="text-sm">Mark to Repeat</div>
                                <div class="text-xs font-normal text-muted-foreground">Retain in current level</div>
                            </div>
                        </button>

                        <button
                            type="button"
                            @click="promotionForm.action = 'graduate'"
                            :class="[
                                'flex items-center gap-3 p-3 rounded-lg border text-left transition-all',
                                promotionForm.action === 'graduate'
                                    ? 'border-purple-500 bg-purple-500/10 text-purple-700 dark:text-purple-400 font-semibold ring-2 ring-purple-500/20'
                                    : 'border-border hover:bg-muted/50 text-muted-foreground'
                            ]"
                        >
                            <Award class="size-5 shrink-0" />
                            <div>
                                <div class="text-sm">Graduate (Alumni)</div>
                                <div class="text-xs font-normal text-muted-foreground">Final grade completion</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Step 1 & Step 2 Context Selectors -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-2 border-t border-border/40">
                    <!-- Step 1: Source Context -->
                    <div class="space-y-3 p-4 rounded-lg bg-muted/20 border border-border/60">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-primary/20 text-xs font-bold text-primary">1</span>
                            <span class="text-sm font-semibold">Select Source Students</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground font-medium">Source Academic Session</Label>
                                <select
                                    v-model="promotionForm.source_session_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                                >
                                    <option v-for="s in sessions" :key="s.id" :value="s.id">
                                        {{ s.name }} {{ s.is_current ? '(Current Active)' : '' }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground font-medium">Source Class <span class="text-destructive">*</span></Label>
                                <select
                                    v-model="promoSourceClassId"
                                    @change="onSourceClassChange"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                                >
                                    <option value="">Select Class to promote...</option>
                                    <option v-for="c in classes" :key="c.id" :value="c.id">
                                        {{ c.name }} (Level {{ c.level_order }})
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-1 sm:col-span-2">
                                <Label class="text-xs text-muted-foreground font-medium">Source Section (Optional Filter)</Label>
                                <select
                                    v-model="promoSourceSectionId"
                                    @change="onSourceSectionChange"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                                    :disabled="!promoSourceClassId"
                                >
                                    <option value="">All Sections in {{ classes.find(c => c.id === Number(promoSourceClassId))?.name || 'Class' }}</option>
                                    <option v-for="sec in availableSourceSections" :key="sec.id" :value="sec.id">
                                        Section {{ sec.name }} (Capacity: {{ sec.capacity }})
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Target Context -->
                    <div class="space-y-3 p-4 rounded-lg bg-muted/20 border border-border/60">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-primary/20 text-xs font-bold text-primary">2</span>
                            <span class="text-sm font-semibold">Select Destination Target</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1" :class="promotionForm.action === 'graduate' ? 'sm:col-span-2' : ''">
                                <Label class="text-xs text-muted-foreground font-medium">Target Academic Session <span class="text-destructive">*</span></Label>
                                <select
                                    v-model="promotionForm.target_session_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                                >
                                    <option value="">Select Target Session...</option>
                                    <option
                                        v-for="s in sessions.filter((s) => s.id !== Number(promotionForm.source_session_id))"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }} {{ s.is_current ? '(Current Active)' : '' }}
                                    </option>
                                </select>
                            </div>

                            <template v-if="promotionForm.action !== 'graduate'">
                                <div class="space-y-1">
                                    <Label class="text-xs text-muted-foreground font-medium">Target Class <span class="text-destructive">*</span></Label>
                                    <select
                                        v-model="promotionForm.target_class_id"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                                    >
                                        <option value="">Select Target Class...</option>
                                        <option v-for="c in classes" :key="c.id" :value="c.id">
                                            {{ c.name }} (Level {{ c.level_order }})
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-1 sm:col-span-2">
                                    <Label class="text-xs text-muted-foreground font-medium">Target Section <span class="text-destructive">*</span></Label>
                                    <select
                                        v-model="promotionForm.target_section_id"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs font-medium"
                                        :disabled="!promotionForm.target_class_id"
                                    >
                                        <option value="">Select Target Section...</option>
                                        <option v-for="s in availableTargetSections" :key="s.id" :value="s.id">
                                            Section {{ s.name }} (Capacity: {{ s.capacity }})
                                        </option>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Candidate Selection Grid -->
                <div class="space-y-3 pt-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="flex size-6 items-center justify-center rounded-full bg-primary/20 text-xs font-bold text-primary">3</span>
                                <h3 class="text-sm font-semibold">
                                    Select Students to Advance ({{ promotionForm.student_ids.length }} of {{ activeCandidates.length }} eligible selected)
                                </h3>
                            </div>
                            <p class="text-xs text-muted-foreground ml-8">
                                Check the students who met institutional pass requirements or repeat criteria.
                            </p>
                        </div>

                        <!-- Candidate Filter Tabs & Benchmark Controls -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Benchmark Input -->
                            <div class="flex items-center gap-1.5 text-xs bg-muted/40 border border-border/60 rounded-md px-2.5 py-1">
                                <span class="text-muted-foreground font-medium">Pass Benchmark:</span>
                                <input
                                    v-model.number="passBenchmark"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="1"
                                    class="w-12 h-6 text-center font-bold text-xs rounded border border-input bg-background focus:ring-1 focus:ring-primary"
                                />
                                <span class="text-muted-foreground">%</span>
                            </div>

                            <div class="inline-flex rounded-lg border border-border bg-muted/40 p-1 text-xs">
                                <button
                                    type="button"
                                    @click="candidateTab = 'active'"
                                    :class="[
                                        'px-2.5 py-1 rounded-md font-medium transition-all',
                                        candidateTab === 'active'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    ]"
                                >
                                    Eligible ({{ activeCandidates.length }})
                                </button>
                                <button
                                    type="button"
                                    @click="candidateTab = 'processed'"
                                    :class="[
                                        'px-2.5 py-1 rounded-md font-medium transition-all',
                                        candidateTab === 'processed'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    ]"
                                >
                                    Processed ({{ processedCandidates.length }})
                                </button>
                                <button
                                    type="button"
                                    @click="candidateTab = 'all'"
                                    :class="[
                                        'px-2.5 py-1 rounded-md font-medium transition-all',
                                        candidateTab === 'all'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    ]"
                                >
                                    All ({{ allSourceCandidates.length }})
                                </button>
                            </div>

                            <!-- Smart Selection Button -->
                            <Button
                                v-if="activeCandidates.length > 0"
                                type="button"
                                variant="secondary"
                                size="sm"
                                class="h-8 text-xs font-semibold gap-1.5"
                                @click="selectRecommended"
                            >
                                <CheckCircle2 class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span>{{ promotionForm.action === 'repeat' ? `Select Repeat Candidates (${recommendedCount})` : `Select Passing (${recommendedCount})` }}</span>
                            </Button>

                            <Button
                                v-if="activeCandidates.length > 0"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs"
                                @click="toggleSelectAll"
                            >
                                {{ isAllSelected ? 'Deselect All' : 'Select All' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Candidates Table -->
                    <div class="border border-border/60 rounded-md overflow-hidden">
                        <div class="max-h-96 overflow-y-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-muted/50 border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase sticky top-0 z-10">
                                    <tr>
                                        <th class="w-10 px-3 py-2.5 text-center">
                                            <Checkbox
                                                :checked="isAllSelected"
                                                :disabled="activeCandidates.length === 0"
                                                @update:checked="toggleSelectAll"
                                            />
                                        </th>
                                        <th class="px-3 py-2.5 text-xs">Admission #</th>
                                        <th class="px-3 py-2.5 text-xs">Student Name</th>
                                        <th class="px-2 py-2.5 text-center text-xs">Term 1</th>
                                        <th class="px-2 py-2.5 text-center text-xs">Term 2</th>
                                        <th class="px-2 py-2.5 text-center text-xs">Term 3</th>
                                        <th class="px-3 py-2.5 text-center text-xs">Annual Mean</th>
                                        <th class="px-3 py-2.5 text-center text-xs">Recommendation</th>
                                        <th class="px-3 py-2.5 text-xs">Status</th>
                                        <th class="px-3 py-2.5 text-xs">Progression Outcome</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr
                                        v-for="candidate in displayedCandidates"
                                        :key="candidate.id"
                                        :class="[
                                            'transition-colors',
                                            candidate.status === 'active'
                                                ? 'hover:bg-muted/30 cursor-pointer'
                                                : 'bg-muted/10 opacity-90 cursor-default'
                                        ]"
                                        @click="candidate.status === 'active' ? toggleCandidate(candidate) : null"
                                    >
                                        <td class="w-10 px-3 py-2.5 text-center" @click.stop>
                                            <Checkbox
                                                v-if="candidate.status === 'active'"
                                                :checked="promotionForm.student_ids.includes(candidate.student_id)"
                                                @update:checked="() => toggleCandidate(candidate)"
                                            />
                                            <div v-else class="flex justify-center" :title="'Status: ' + candidate.status">
                                                <CheckCircle2 class="size-4 text-emerald-600 dark:text-emerald-400" />
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 font-mono font-medium text-xs text-primary">
                                            {{ candidate.student?.admission_number }}
                                        </td>
                                        <td class="px-3 py-2.5 font-medium text-foreground text-xs">
                                            <div class="flex items-center gap-1.5">
                                                <span>{{ candidate.student?.user?.name }}</span>
                                                <Badge variant="outline" class="text-[10px] font-normal px-1 py-0">
                                                    Sec {{ candidate.section?.name }}
                                                </Badge>
                                            </div>
                                        </td>
                                        <!-- Term 1 -->
                                        <td class="px-2 py-2.5 text-center font-mono text-xs">
                                            <span
                                                v-if="getPerformance(candidate.student_id)?.term1_avg !== null && getPerformance(candidate.student_id)?.term1_avg !== undefined"
                                                :class="(getPerformance(candidate.student_id)?.term1_avg ?? 0) >= passBenchmark ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-rose-600 dark:text-rose-400 font-semibold'"
                                            >
                                                {{ getPerformance(candidate.student_id)?.term1_avg }}%
                                            </span>
                                            <span v-else class="text-muted-foreground/60">—</span>
                                        </td>
                                        <!-- Term 2 -->
                                        <td class="px-2 py-2.5 text-center font-mono text-xs">
                                            <span
                                                v-if="getPerformance(candidate.student_id)?.term2_avg !== null && getPerformance(candidate.student_id)?.term2_avg !== undefined"
                                                :class="(getPerformance(candidate.student_id)?.term2_avg ?? 0) >= passBenchmark ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-rose-600 dark:text-rose-400 font-semibold'"
                                            >
                                                {{ getPerformance(candidate.student_id)?.term2_avg }}%
                                            </span>
                                            <span v-else class="text-muted-foreground/60">—</span>
                                        </td>
                                        <!-- Term 3 -->
                                        <td class="px-2 py-2.5 text-center font-mono text-xs">
                                            <span
                                                v-if="getPerformance(candidate.student_id)?.term3_avg !== null && getPerformance(candidate.student_id)?.term3_avg !== undefined"
                                                :class="(getPerformance(candidate.student_id)?.term3_avg ?? 0) >= passBenchmark ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-rose-600 dark:text-rose-400 font-semibold'"
                                            >
                                                {{ getPerformance(candidate.student_id)?.term3_avg }}%
                                            </span>
                                            <span v-else class="text-muted-foreground/60">—</span>
                                        </td>
                                        <!-- Annual Mean & Grade -->
                                        <td class="px-3 py-2.5 text-center font-mono text-xs">
                                            <div
                                                v-if="getPerformance(candidate.student_id)?.annual_avg !== null && getPerformance(candidate.student_id)?.annual_avg !== undefined"
                                                class="inline-flex items-center gap-1 font-bold"
                                            >
                                                <span :class="(getPerformance(candidate.student_id)?.annual_avg ?? 0) >= passBenchmark ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                                    {{ getPerformance(candidate.student_id)?.annual_avg }}%
                                                </span>
                                                <Badge variant="outline" class="text-[10px] px-1 py-0 font-mono font-bold">
                                                    {{ getPerformance(candidate.student_id)?.grade || '—' }}
                                                </Badge>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground/60 italic">No marks</span>
                                        </td>
                                        <!-- Decision Recommendation Badge -->
                                        <td class="px-3 py-2.5 text-center text-xs">
                                            <template v-if="getPerformance(candidate.student_id)?.annual_avg !== null && getPerformance(candidate.student_id)?.annual_avg !== undefined">
                                                <Badge
                                                    v-if="isEligibleForPromotion(candidate.student_id)"
                                                    class="bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300/50 text-[10px] py-0 font-semibold"
                                                >
                                                    ● Promote
                                                </Badge>
                                                <Badge
                                                    v-else
                                                    class="bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300/50 text-[10px] py-0 font-semibold"
                                                >
                                                    ● Repeat
                                                </Badge>
                                            </template>
                                            <span v-else class="text-[10px] text-muted-foreground italic">No Marks</span>
                                        </td>
                                        <!-- Status in Session -->
                                        <td class="px-3 py-2.5 text-xs">
                                            <Badge
                                                :class="[
                                                    'text-xs capitalize font-medium',
                                                    candidate.status === 'promoted' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300/50' : '',
                                                    candidate.status === 'repeated' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300/50' : '',
                                                    candidate.status === 'graduated' ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-300/50' : '',
                                                    candidate.status === 'active' ? 'bg-secondary text-secondary-foreground' : ''
                                                ]"
                                            >
                                                {{ candidate.status }}
                                            </Badge>
                                        </td>
                                        <!-- Progression Outcome -->
                                        <td class="px-3 py-2.5 text-xs">
                                            <div v-if="getProgressionDestination(candidate)" class="flex items-center gap-1.5 font-medium text-emerald-700 dark:text-emerald-400">
                                                <TrendingUp class="size-3.5 shrink-0" />
                                                <span>
                                                    Enrolled in {{ getProgressionDestination(candidate)?.school_class?.name }}
                                                    (Sec {{ getProgressionDestination(candidate)?.section?.name }})
                                                    for {{ getProgressionDestination(candidate)?.academic_session?.name }}
                                                </span>
                                            </div>
                                            <span v-else-if="candidate.status === 'graduated'" class="font-medium text-purple-600 dark:text-purple-400">
                                                Graduated Alumni
                                            </span>
                                            <span v-else class="text-muted-foreground italic">
                                                Pending progression
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="displayedCandidates.length === 0">
                                        <td colspan="10" class="px-6 py-8 text-center text-muted-foreground">
                                            <div v-if="!promoSourceClassId" class="text-xs">
                                                Please select a source class above to load student candidates.
                                            </div>
                                            <div v-else-if="candidateTab === 'active'" class="text-xs">
                                                All students in this class have already been processed for promotion or repeat.
                                                <button
                                                    type="button"
                                                    class="underline text-primary ml-1"
                                                    @click="candidateTab = 'processed'"
                                                >
                                                    View processed students
                                                </button>
                                            </div>
                                            <div v-else-if="candidateTab === 'processed'" class="text-xs">
                                                No processed students in this class yet.
                                            </div>
                                            <div v-else class="text-xs">
                                                No students enrolled in this source class for session {{ currentSession?.name }}.
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Execute Button & Validation Alert -->
                <div class="pt-4 border-t border-border/40 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-xs text-muted-foreground">
                        Target action:
                        <span class="font-bold text-foreground capitalize">{{ promotionForm.action }}</span>
                        <span v-if="promotionForm.action !== 'graduate' && promotionForm.target_class_id">
                            to {{ classes.find(c => c.id === Number(promotionForm.target_class_id))?.name }}
                        </span>
                    </div>

                    <Button
                        type="button"
                        size="lg"
                        class="w-full sm:w-auto gap-2"
                        :disabled="
                            promotionForm.processing ||
                            promotionForm.student_ids.length === 0 ||
                            !promotionForm.target_session_id ||
                            (promotionForm.action !== 'graduate' && (!promotionForm.target_class_id || !promotionForm.target_section_id))
                        "
                        @click="openConfirmModal"
                    >
                        <Spinner v-if="promotionForm.processing" class="h-4 w-4" />
                        <span>Execute Progression ({{ promotionForm.student_ids.length }} Selected)</span>
                        <ArrowRight class="h-4 w-4" />
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- ================================================================= -->
        <!-- CONFIRM STUDENT PROGRESSION MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isConfirmModalOpen" @update:open="(val) => isConfirmModalOpen = val">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader>
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
                            <GraduationCap class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-bold">Confirm Student Progression</DialogTitle>
                            <DialogDescription>
                                Review the progression summary below before applying this academic change.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <!-- Action Overview Banner -->
                    <div class="rounded-lg border border-border bg-muted/30 p-3.5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-muted-foreground">Progression Type</span>
                            <Badge
                                :class="[
                                    'capitalize font-semibold text-xs px-2.5 py-0.5',
                                    promotionForm.action === 'promote' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30' : '',
                                    promotionForm.action === 'repeat' ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30' : '',
                                    promotionForm.action === 'graduate' ? 'bg-sky-500/15 text-sky-700 dark:text-sky-400 border-sky-500/30' : '',
                                ]"
                            >
                                {{ promotionForm.action === 'promote' ? 'Promote to Next Grade' : promotionForm.action === 'repeat' ? 'Repeat Current Grade' : 'Graduate Students (Alumni)' }}
                            </Badge>
                        </div>

                        <!-- Source -> Target Transition Flow -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1 text-xs">
                            <div class="space-y-1 rounded-md bg-background/80 p-3 border border-border/60 shadow-2xs">
                                <p class="font-semibold text-muted-foreground uppercase tracking-wider text-[10px]">From (Source)</p>
                                <p class="font-bold text-foreground text-sm">{{ sourceClassName }}</p>
                                <p class="text-xs text-muted-foreground">Section: <span class="font-medium text-foreground">{{ sourceSectionName }}</span></p>
                                <p class="text-[11px] text-muted-foreground pt-0.5">Session: {{ sourceSessionName }}</p>
                            </div>
                            <div class="space-y-1 rounded-md bg-background/80 p-3 border border-border/60 shadow-2xs">
                                <p class="font-semibold text-muted-foreground uppercase tracking-wider text-[10px]">To (Destination)</p>
                                <p class="font-bold text-foreground text-sm">
                                    <template v-if="promotionForm.action === 'graduate'">
                                        Graduated / Alumni
                                    </template>
                                    <template v-else>
                                        {{ targetClassName }}
                                    </template>
                                </p>
                                <p v-if="promotionForm.action !== 'graduate'" class="text-xs text-muted-foreground">
                                    Section: <span class="font-medium text-foreground">{{ targetSectionName }}</span>
                                </p>
                                <p class="text-[11px] text-muted-foreground pt-0.5">Session: {{ targetSessionName }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Students List -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-foreground">
                                Selected Candidates ({{ selectedCandidateDetails.length }} students)
                            </span>
                            <span class="text-muted-foreground text-[11px]">Will be processed in this batch</span>
                        </div>
                        <div class="max-h-36 overflow-y-auto rounded-md border border-border divide-y divide-border/60 bg-muted/15 p-1">
                            <div
                                v-for="cand in selectedCandidateDetails"
                                :key="cand.id"
                                class="px-2.5 py-1.5 flex items-center justify-between text-xs hover:bg-muted/40 rounded transition-colors"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-medium text-foreground truncate">
                                        {{ cand.student?.user?.name ?? 'Student' }}
                                    </span>
                                    <Badge variant="outline" class="text-[10px] px-1.5 py-0 font-normal shrink-0">
                                        {{ cand.school_class?.name ?? 'Class' }} - Sec {{ cand.section?.name ?? '' }}
                                    </Badge>
                                </div>
                                <span class="font-mono text-[11px] text-muted-foreground shrink-0 ml-2">
                                    {{ cand.student?.admission_number }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation Notice -->
                    <div class="rounded-md bg-amber-500/10 border border-amber-500/20 p-3 flex items-start gap-2.5 text-xs text-amber-900 dark:text-amber-200">
                        <Info class="size-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                        <div>
                            <p class="font-medium">Academic Record Notice</p>
                            <p class="text-[11px] opacity-90 mt-0.5 leading-relaxed">
                                Once confirmed, new academic enrollments will be recorded for {{ targetSessionName }}.
                                Previous enrollment records will remain preserved in historical academic archives.
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0 pt-3 border-t border-border">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="promotionForm.processing"
                        @click="isConfirmModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="promotionForm.processing"
                        @click="confirmAndExecutePromotion"
                        class="gap-2"
                    >
                        <Spinner v-if="promotionForm.processing" class="size-4" />
                        <span>Confirm & Execute</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
