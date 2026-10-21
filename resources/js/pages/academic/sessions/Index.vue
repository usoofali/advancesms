<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Calendar,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    Clock,
    Edit2,
    Layers,
    Plus,
    Sparkles,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
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
    DialogClose,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { index as academicSessionsIndex } from '@/routes/academic/sessions';
import type { AcademicSession, AcademicStats, AcademicTerm } from '@/types/academic';

interface Props {
    sessions: AcademicSession[];
    currentSession: AcademicSession | null;
    currentTerm: AcademicTerm | null;
    stats: AcademicStats;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Academic Sessions',
                href: academicSessionsIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateSession = computed(() => isSuperAdmin.value || userPermissions.value.includes('academic_sessions.create'));
const canUpdateSession = computed(() => isSuperAdmin.value || userPermissions.value.includes('academic_sessions.update'));
const canDeleteSession = computed(() => isSuperAdmin.value || userPermissions.value.includes('academic_sessions.delete'));
const canSetCurrentSession = computed(() => isSuperAdmin.value || userPermissions.value.includes('academic_sessions.set_current'));

const canCreateTerm = computed(() => isSuperAdmin.value || userPermissions.value.includes('academic_terms.create'));
const canUpdateTerm = computed(() => isSuperAdmin.value || userPermissions.value.includes('academic_terms.update'));
const canDeleteTerm = computed(() => isSuperAdmin.value || userPermissions.value.includes('academic_terms.delete'));
const canSetCurrentTerm = computed(() => isSuperAdmin.value || userPermissions.value.includes('academic_terms.set_current'));

// Format Date Utility
const formatDate = (dateStr: string | null | undefined): string => {
    if (!dateStr) return 'N/A';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return dateStr;
    }
};

// =========================================================================
// MODAL STATES
// =========================================================================
const isSessionModalOpen = ref(false);
const editingSession = ref<AcademicSession | null>(null);

const sessionForm = useForm({
    name: '',
    start_date: '',
    end_date: '',
    is_current: false,
    generate_terms: true,
});

const openCreateSessionModal = () => {
    editingSession.value = null;
    sessionForm.reset();
    sessionForm.clearErrors();
    sessionForm.name = '';
    sessionForm.start_date = '';
    sessionForm.end_date = '';
    sessionForm.is_current = false;
    sessionForm.generate_terms = true;
    isSessionModalOpen.value = true;
};

const openEditSessionModal = (session: AcademicSession) => {
    editingSession.value = session;
    sessionForm.reset();
    sessionForm.clearErrors();
    sessionForm.name = session.name;
    sessionForm.start_date = session.start_date.substring(0, 10);
    sessionForm.end_date = session.end_date.substring(0, 10);
    sessionForm.is_current = session.is_current;
    sessionForm.generate_terms = false;
    isSessionModalOpen.value = true;
};

const submitSessionForm = () => {
    if (editingSession.value) {
        sessionForm.put(`/academic/sessions/${editingSession.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isSessionModalOpen.value = false;
                toast.success('Academic session updated successfully.');
            },
        });
    } else {
        sessionForm.post('/academic/sessions', {
            preserveScroll: true,
            onSuccess: () => {
                isSessionModalOpen.value = false;
                toast.success('Academic session created successfully.');
            },
        });
    }
};

// =========================================================================
// TERM MODAL STATES
// =========================================================================
const isTermModalOpen = ref(false);
const editingTerm = ref<AcademicTerm | null>(null);
const parentSessionForTerm = ref<AcademicSession | null>(null);

const termForm = useForm({
    academic_session_id: 0,
    name: '',
    term_number: 1,
    start_date: '',
    end_date: '',
    is_current: false,
});

const openCreateTermModal = (session: AcademicSession) => {
    editingTerm.value = null;
    parentSessionForTerm.value = session;
    termForm.reset();
    termForm.clearErrors();
    termForm.academic_session_id = session.id;

    // Suggest next term number
    const existingTerms = session.terms ?? [];
    const usedNumbers = existingTerms.map((t) => t.term_number);
    let nextNum = 1;
    if (usedNumbers.includes(1) && !usedNumbers.includes(2)) nextNum = 2;
    else if (usedNumbers.includes(2) && !usedNumbers.includes(3)) nextNum = 3;

    termForm.term_number = nextNum;
    termForm.name = nextNum === 1 ? 'First Term' : nextNum === 2 ? 'Second Term' : 'Third Term';
    termForm.start_date = session.start_date.substring(0, 10);
    termForm.end_date = session.end_date.substring(0, 10);
    termForm.is_current = false;
    isTermModalOpen.value = true;
};

const openEditTermModal = (term: AcademicTerm, session: AcademicSession) => {
    editingTerm.value = term;
    parentSessionForTerm.value = session;
    termForm.reset();
    termForm.clearErrors();
    termForm.academic_session_id = term.academic_session_id;
    termForm.name = term.name;
    termForm.term_number = term.term_number;
    termForm.start_date = term.start_date.substring(0, 10);
    termForm.end_date = term.end_date.substring(0, 10);
    termForm.is_current = term.is_current;
    isTermModalOpen.value = true;
};

const submitTermForm = () => {
    if (editingTerm.value) {
        termForm.put(`/academic/terms/${editingTerm.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isTermModalOpen.value = false;
                toast.success('Academic term updated successfully.');
            },
        });
    } else {
        termForm.post('/academic/terms', {
            preserveScroll: true,
            onSuccess: () => {
                isTermModalOpen.value = false;
                toast.success('Academic term created successfully.');
            },
        });
    }
};

// =========================================================================
// ACTIVATE ACTIONS (SET CURRENT)
// =========================================================================
const sessionToActivate = ref<AcademicSession | null>(null);
const termToActivate = ref<AcademicTerm | null>(null);
const isActivateModalOpen = ref(false);
const activationType = ref<'session' | 'term'>('session');

const activateForm = useForm({});

const confirmSetCurrentSession = (session: AcademicSession) => {
    sessionToActivate.value = session;
    activationType.value = 'session';
    isActivateModalOpen.value = true;
};

const confirmSetCurrentTerm = (term: AcademicTerm) => {
    termToActivate.value = term;
    activationType.value = 'term';
    isActivateModalOpen.value = true;
};

const executeActivation = () => {
    if (activationType.value === 'session' && sessionToActivate.value) {
        activateForm.post(`/academic/sessions/${sessionToActivate.value.id}/set-current`, {
            preserveScroll: true,
            onSuccess: () => {
                isActivateModalOpen.value = false;
                toast.success(`Academic session ${sessionToActivate.value?.name} is now the active session.`);
            },
        });
    } else if (activationType.value === 'term' && termToActivate.value) {
        activateForm.post(`/academic/terms/${termToActivate.value.id}/set-current`, {
            preserveScroll: true,
            onSuccess: () => {
                isActivateModalOpen.value = false;
                toast.success(`${termToActivate.value?.name} is now the active term.`);
            },
        });
    }
};

// =========================================================================
// DELETE ACTIONS
// =========================================================================
const itemToDelete = ref<{ type: 'session' | 'term'; id: number; name: string } | null>(null);
const isDeleteModalOpen = ref(false);
const deleteForm = useForm({});

const confirmDeleteSession = (session: AcademicSession) => {
    itemToDelete.value = {
        type: 'session',
        id: session.id,
        name: `Session: ${session.name}`,
    };
    isDeleteModalOpen.value = true;
};

const confirmDeleteTerm = (term: AcademicTerm) => {
    itemToDelete.value = {
        type: 'term',
        id: term.id,
        name: `Term: ${term.name}`,
    };
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) return;

    const url = itemToDelete.value.type === 'session'
        ? `/academic/sessions/${itemToDelete.value.id}`
        : `/academic/terms/${itemToDelete.value.id}`;

    deleteForm.delete(url, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            toast.success('Deleted successfully.');
        },
        onError: (errs) => {
            toast.error(errs.error || 'Failed to delete record.');
        },
    });
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <Head title="Academic Sessions & Terms" />

        <!-- Top Header & Action Banner (Responsive Mobile-First) -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Academic Calendar
                    </h1>
                    <Badge variant="outline" class="font-mono text-xs">
                        Sessions & Terms
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    Configure institutional academic sessions, define three-term cycles, and manage the live operational period.
                </p>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <Button
                    v-if="canCreateSession"
                    @click="openCreateSessionModal"
                    class="w-full sm:w-auto gap-1.5 shadow-sm"
                >
                    <Plus class="size-4" />
                    <span>Add Session</span>
                </Button>
            </div>
        </div>

        <!-- Metric KPI Cards (1 Col Mobile, 2 Col Tablet, 4 Col Desktop) -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Active Session Card -->
            <Card class="border-border/60 shadow-xs relative overflow-hidden bg-gradient-to-br from-card via-card to-emerald-500/5">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Current Session
                    </CardTitle>
                    <div class="flex size-7 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <CalendarCheck class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <span class="text-xl sm:text-2xl font-bold text-foreground">
                            {{ stats.active_session_name }}
                        </span>
                        <span
                            v-if="currentSession"
                            class="relative flex size-2.5"
                        >
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-2.5 bg-emerald-500"></span>
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ currentSession ? `${formatDate(currentSession.start_date)} - ${formatDate(currentSession.end_date)}` : 'No active session' }}
                    </p>
                </CardContent>
            </Card>

            <!-- Active Term Card -->
            <Card class="border-border/60 shadow-xs relative overflow-hidden bg-gradient-to-br from-card via-card to-blue-500/5">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Current Term
                    </CardTitle>
                    <div class="flex size-7 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <CalendarClock class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <span class="text-xl sm:text-2xl font-bold text-foreground">
                            {{ currentTerm ? currentTerm.name : 'None Active' }}
                        </span>
                        <span
                            v-if="currentTerm"
                            class="relative flex size-2.5"
                        >
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-2.5 bg-blue-500"></span>
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ currentTerm ? `${formatDate(currentTerm.start_date)} - ${formatDate(currentTerm.end_date)}` : 'Switch to an active term' }}
                    </p>
                </CardContent>
            </Card>

            <!-- Total Sessions -->
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Total Sessions
                    </CardTitle>
                    <div class="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <Calendar class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-xl sm:text-2xl font-bold text-foreground">
                        {{ stats.total_sessions }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Recorded academic years
                    </p>
                </CardContent>
            </Card>

            <!-- Total Terms -->
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Total Terms
                    </CardTitle>
                    <div class="flex size-7 items-center justify-center rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <Layers class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-xl sm:text-2xl font-bold text-foreground">
                        {{ stats.total_terms }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Terminal periods configured
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Academic Sessions List with Nested Terms (Mobile-First Card Layout) -->
        <div class="space-y-6">
            <div
                v-for="session in sessions"
                :key="session.id"
                class="rounded-xl border transition-all duration-200 overflow-hidden shadow-xs"
                :class="[
                    session.is_current
                        ? 'border-emerald-500/50 bg-gradient-to-br from-card via-card to-emerald-500/[0.03] ring-1 ring-emerald-500/20'
                        : 'border-border/80 bg-card hover:border-border'
                ]"
            >
                <!-- Session Header Bar -->
                <div class="p-4 sm:p-6 border-b border-border/60">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Left: Session Title, Status & Dates -->
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-lg sm:text-xl font-bold tracking-tight text-foreground">
                                    {{ session.name }} Academic Session
                                </span>
                                <Badge
                                    v-if="session.is_current"
                                    class="bg-emerald-600 text-white hover:bg-emerald-600 gap-1 text-[11px]"
                                >
                                    <CheckCircle2 class="size-3" />
                                    Active Session
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="text-muted-foreground text-[11px]"
                                >
                                    Inactive
                                </Badge>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1">
                                    <Clock class="size-3.5 text-primary/70" />
                                    {{ formatDate(session.start_date) }} &mdash; {{ formatDate(session.end_date) }}
                                </span>
                                <span v-if="session.enrollments_count !== undefined" class="flex items-center gap-1">
                                    <Users class="size-3.5 text-primary/70" />
                                    {{ session.enrollments_count }} Enrollments
                                </span>
                            </div>
                        </div>

                        <!-- Right: Session Action Controls (Wrap cleanly on mobile) -->
                        <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0">
                            <!-- Set Active Button -->
                            <Button
                                v-if="!session.is_current && canSetCurrentSession"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 text-xs text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                                @click="confirmSetCurrentSession(session)"
                            >
                                <CheckCircle2 class="size-3.5" />
                                <span>Set as Active</span>
                            </Button>

                            <!-- Add Term Button -->
                            <Button
                                v-if="canCreateTerm && (session.terms?.length ?? 0) < 3"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1 text-xs"
                                @click="openCreateTermModal(session)"
                            >
                                <Plus class="size-3.5" />
                                <span>Add Term</span>
                            </Button>

                            <!-- Edit Session Button -->
                            <Button
                                v-if="canUpdateSession"
                                variant="ghost"
                                size="sm"
                                class="size-8 p-0"
                                @click="openEditSessionModal(session)"
                                title="Edit Session"
                            >
                                <Edit2 class="size-3.5" />
                            </Button>

                            <!-- Delete Session Button -->
                            <Button
                                v-if="canDeleteSession && !session.is_current && (session.enrollments_count ?? 0) === 0"
                                variant="ghost"
                                size="sm"
                                class="size-8 p-0 text-destructive hover:bg-destructive/10"
                                @click="confirmDeleteSession(session)"
                                title="Delete Session"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Terms Sub-List Section -->
                <div class="p-4 sm:p-6 bg-muted/20">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            Academic Terms ({{ session.terms?.length ?? 0 }} of 3)
                        </span>
                        <span v-if="!session.terms || session.terms.length === 0" class="text-xs text-muted-foreground italic">
                            No terms configured yet
                        </span>
                    </div>

                    <!-- Terms Cards Grid (1 Col Mobile, 3 Col Desktop) -->
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div
                            v-for="term in session.terms"
                            :key="term.id"
                            class="rounded-lg border p-3.5 sm:p-4 transition-colors"
                            :class="[
                                term.is_current
                                    ? 'border-blue-500/60 bg-blue-500/[0.04] ring-1 ring-blue-500/20'
                                    : 'border-border/70 bg-background hover:border-border'
                            ]"
                        >
                            <div class="flex flex-col h-full justify-between gap-3">
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="flex size-5 items-center justify-center rounded-full bg-primary/10 text-primary text-[10px] font-bold">
                                                {{ term.term_number }}
                                            </span>
                                            <span class="font-semibold text-sm text-foreground">
                                                {{ term.name }}
                                            </span>
                                        </div>

                                        <Badge
                                            v-if="term.is_current"
                                            class="bg-blue-600 text-white hover:bg-blue-600 text-[10px] h-5"
                                        >
                                            Active Term
                                        </Badge>
                                    </div>

                                    <div class="mt-2 text-xs text-muted-foreground flex items-center gap-1">
                                        <CalendarDays class="size-3 text-muted-foreground/70 shrink-0" />
                                        <span>{{ formatDate(term.start_date) }} &mdash; {{ formatDate(term.end_date) }}</span>
                                    </div>
                                </div>

                                <!-- Term Actions -->
                                <div class="flex items-center justify-between pt-2 border-t border-border/50">
                                    <Button
                                        v-if="!term.is_current && canSetCurrentTerm"
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-xs text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/30 gap-1 px-2"
                                        @click="confirmSetCurrentTerm(term)"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        <span>Make Active</span>
                                    </Button>
                                    <span v-else class="text-[11px] font-medium text-blue-600 dark:text-blue-400">
                                        Live Active Term
                                    </span>

                                    <div class="flex items-center gap-1">
                                        <Button
                                            v-if="canUpdateTerm"
                                            variant="ghost"
                                            size="sm"
                                            class="size-7 p-0"
                                            @click="openEditTermModal(term, session)"
                                            title="Edit Term"
                                        >
                                            <Edit2 class="size-3" />
                                        </Button>

                                        <Button
                                            v-if="canDeleteTerm && !term.is_current"
                                            variant="ghost"
                                            size="sm"
                                            class="size-7 p-0 text-destructive hover:bg-destructive/10"
                                            @click="confirmDeleteTerm(term)"
                                            title="Delete Term"
                                        >
                                            <Trash2 class="size-3" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="sessions.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/80 p-8 sm:p-12 text-center bg-card/40"
            >
                <div class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
                    <CalendarDays class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-foreground">
                    No academic sessions configured
                </h3>
                <p class="text-xs sm:text-sm text-muted-foreground max-w-sm mt-1">
                    Get started by creating your institution's initial academic year and term schedule.
                </p>
                <Button
                    v-if="canCreateSession"
                    @click="openCreateSessionModal"
                    class="mt-4 gap-1.5"
                    size="sm"
                >
                    <Plus class="size-4" />
                    <span>Create Academic Session</span>
                </Button>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 1: Create / Edit Academic Session (Mobile Scroll Friendly)          -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isSessionModalOpen">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingSession ? `Edit Academic Session: ${editingSession.name}` : 'New Academic Session' }}
                    </DialogTitle>
                    <DialogDescription>
                        Define the academic year identifier and operational date boundaries.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitSessionForm" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label for="session_name">
                            Session Name <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="session_name"
                            v-model="sessionForm.name"
                            type="text"
                            placeholder="e.g. 2026/2027"
                            required
                        />
                        <p class="text-[11px] text-muted-foreground">Standard format: YYYY/YYYY (e.g. 2025/2026)</p>
                        <InputError :message="sessionForm.errors.name" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="session_start_date">
                                Start Date <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="session_start_date"
                                v-model="sessionForm.start_date"
                                type="date"
                                required
                            />
                            <InputError :message="sessionForm.errors.start_date" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="session_end_date">
                                End Date <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="session_end_date"
                                v-model="sessionForm.end_date"
                                type="date"
                                required
                            />
                            <InputError :message="sessionForm.errors.end_date" />
                        </div>
                    </div>

                    <!-- Options for New Session -->
                    <div v-if="!editingSession" class="space-y-3 pt-2 border-t border-border/60">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="generate_terms"
                                :checked="sessionForm.generate_terms"
                                @update:checked="sessionForm.generate_terms = $event"
                                class="mt-0.5"
                            />
                            <div class="grid gap-0.5 leading-none">
                                <label
                                    for="generate_terms"
                                    class="text-xs font-medium leading-normal cursor-pointer select-none"
                                >
                                    Auto-generate 3 terms (First, Second, Third Term)
                                </label>
                                <p class="text-[11px] text-muted-foreground">
                                    Automatically partitions the session window into standard three-term intervals.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="is_current"
                                :checked="sessionForm.is_current"
                                @update:checked="sessionForm.is_current = $event"
                                class="mt-0.5"
                            />
                            <div class="grid gap-0.5 leading-none">
                                <label
                                    for="is_current"
                                    class="text-xs font-medium leading-normal cursor-pointer select-none"
                                >
                                    Set as current active session immediately
                                </label>
                                <p class="text-[11px] text-muted-foreground">
                                    All current enrollments and portals will switch to this session.
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="flex flex-col-reverse sm:flex-row gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSessionModalOpen = false"
                            class="w-full sm:w-auto"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="sessionForm.processing"
                            class="w-full sm:w-auto"
                        >
                            <Spinner v-if="sessionForm.processing" class="size-4 mr-1.5" />
                            <span>{{ editingSession ? 'Update Session' : 'Create Session' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ========================================================================= -->
        <!-- MODAL 2: Create / Edit Academic Term (Mobile Scroll Friendly)             -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isTermModalOpen">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingTerm ? `Edit Term: ${editingTerm.name}` : `Add Term to ${parentSessionForTerm?.name ?? ''}` }}
                    </DialogTitle>
                    <DialogDescription>
                        Set the terminal period number, dates, and active status.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitTermForm" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label for="term_name">
                            Term Name <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="term_name"
                            v-model="termForm.name"
                            type="text"
                            placeholder="e.g. First Term"
                            required
                        />
                        <InputError :message="termForm.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="term_number">
                            Term Number <span class="text-destructive">*</span>
                        </Label>
                        <select
                            id="term_number"
                            v-model.number="termForm.term_number"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            required
                        >
                            <option :value="1">Term 1 (First Term)</option>
                            <option :value="2">Term 2 (Second Term)</option>
                            <option :value="3">Term 3 (Third Term)</option>
                        </select>
                        <InputError :message="termForm.errors.term_number" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="term_start_date">
                                Start Date <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="term_start_date"
                                v-model="termForm.start_date"
                                type="date"
                                required
                            />
                            <InputError :message="termForm.errors.start_date" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="term_end_date">
                                End Date <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="term_end_date"
                                v-model="termForm.end_date"
                                type="date"
                                required
                            />
                            <InputError :message="termForm.errors.end_date" />
                        </div>
                    </div>

                    <div v-if="!editingTerm" class="pt-2">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="term_is_current"
                                :checked="termForm.is_current"
                                @update:checked="termForm.is_current = $event"
                                class="mt-0.5"
                            />
                            <div class="grid gap-0.5 leading-none">
                                <label
                                    for="term_is_current"
                                    class="text-xs font-medium leading-normal cursor-pointer select-none"
                                >
                                    Set as active term immediately
                                </label>
                                <p class="text-[11px] text-muted-foreground">
                                    Activates this term and activates its parent session.
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="flex flex-col-reverse sm:flex-row gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isTermModalOpen = false"
                            class="w-full sm:w-auto"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="termForm.processing"
                            class="w-full sm:w-auto"
                        >
                            <Spinner v-if="termForm.processing" class="size-4 mr-1.5" />
                            <span>{{ editingTerm ? 'Update Term' : 'Save Term' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ========================================================================= -->
        <!-- MODAL 3: Confirm Activation (Set as Current Session / Term)                -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isActivateModalOpen">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Sparkles class="size-5 text-emerald-600 dark:text-emerald-400" />
                        <span>Confirm Active Period Switch</span>
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            activationType === 'session'
                                ? `Are you sure you want to activate the ${sessionToActivate?.name} Academic Session? This will update the active school calendar across all teacher mark registers, student attendances, and broadsheets.`
                                : `Are you sure you want to activate ${termToActivate?.name}? This will switch the active terminal evaluation window.`
                        }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex flex-col-reverse sm:flex-row gap-2 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isActivateModalOpen = false"
                        class="w-full sm:w-auto"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                        :disabled="activateForm.processing"
                        @click="executeActivation"
                    >
                        <Spinner v-if="activateForm.processing" class="size-4" />
                        <span>Confirm & Activate</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- ========================================================================= -->
        <!-- MODAL 4: Confirm Delete                                                   -->
        <!-- ========================================================================= -->
        <Dialog v-model:open="isDeleteModalOpen">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-5" />
                        <span>Confirm Deletion</span>
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete <strong class="text-foreground">{{ itemToDelete?.name }}</strong>?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex flex-col-reverse sm:flex-row gap-2 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteModalOpen = false"
                        class="w-full sm:w-auto"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        class="w-full sm:w-auto gap-1.5"
                        :disabled="deleteForm.processing"
                        @click="executeDelete"
                    >
                        <Spinner v-if="deleteForm.processing" class="size-4" />
                        <span>Delete Record</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
