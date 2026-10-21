<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BookOpen,
    Building2,
    CheckCircle2,
    Edit2,
    FolderKanban,
    GraduationCap,
    Info,
    Layers,
    Library,
    Plus,
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy as destroyClass, index as academicClassesIndex, store as storeClass, update as updateClass } from '@/routes/academic/classes';
import { index as academicDepartmentsIndex } from '@/routes/academic/departments';
import { destroy as destroySection, store as storeSection, update as updateSection } from '@/routes/academic/sections';
import { index as academicSubjectsIndex } from '@/routes/academic/subjects';
import type { ClassStats, SchoolClass, Section, StageOption } from '@/types/academic';

interface Props {
    classes: SchoolClass[];
    stats: ClassStats;
    stages: StageOption[];
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Classes & Sections',
                href: academicClassesIndex().url,
            },
        ],
    },
});

const page = usePage();

// Permissions
const userPermissions = computed(() => (page.props.auth?.permissions as string[]) ?? []);
const userRoles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isSuperAdmin = computed(() => userPermissions.value.includes('*') || userRoles.value.includes('superadmin'));

const canCreateClass = computed(() => isSuperAdmin.value || userPermissions.value.includes('classes.create'));
const canUpdateClass = computed(() => isSuperAdmin.value || userPermissions.value.includes('classes.update'));
const canDeleteClass = computed(() => isSuperAdmin.value || userPermissions.value.includes('classes.delete'));

const canCreateSection = computed(() => isSuperAdmin.value || userPermissions.value.includes('sections.create'));
const canUpdateSection = computed(() => isSuperAdmin.value || userPermissions.value.includes('sections.update'));
const canDeleteSection = computed(() => isSuperAdmin.value || userPermissions.value.includes('sections.delete'));

// Active stage filter
const selectedStageFilter = ref<string>('all');

const availableFilterStages = computed(() => {
    // Unique stages currently used across the classes
    const usedStages = Array.from(new Set(props.classes.map((c) => c.stage)));
    return props.stages.filter((s) => usedStages.includes(s.value));
});

const filteredClasses = computed(() => {
    if (selectedStageFilter.value === 'all') return props.classes;
    return props.classes.filter((c) => c.stage === selectedStageFilter.value);
});

// =========================================================================
// CLASS MODAL STATES
// =========================================================================
const isClassModalOpen = ref(false);
const editingClass = ref<SchoolClass | null>(null);
const selectedStagePreset = ref<string>('primary');
const customStageName = ref<string>('');

const classForm = useForm({
    name: '',
    stage: 'primary',
    level_order: 1,
    position_mode: 'after' as 'after' | 'before' | 'start' | 'end' | 'custom',
    relative_class_id: null as number | null,
    create_sections: false,
    initial_sections: 'A, B',
    section_capacity: 50,
});

const availableRelativeClasses = computed(() => {
    if (editingClass.value) {
        return props.classes.filter((c) => c.id !== editingClass.value?.id);
    }
    return props.classes;
});

const recalculateLevelOrder = () => {
    if (classForm.position_mode === 'start') {
        classForm.level_order = 1;
    } else if (classForm.position_mode === 'end') {
        const others = availableRelativeClasses.value;
        const maxLevel = others.length > 0 ? Math.max(...others.map((c) => c.level_order)) : 0;
        classForm.level_order = maxLevel + 1;
    } else if (classForm.position_mode === 'before' && classForm.relative_class_id) {
        const target = props.classes.find((c) => c.id === classForm.relative_class_id);
        if (target) {
            classForm.level_order = target.level_order;
        }
    } else if (classForm.position_mode === 'after' && classForm.relative_class_id) {
        const target = props.classes.find((c) => c.id === classForm.relative_class_id);
        if (target) {
            classForm.level_order = target.level_order + 1;
        }
    }
};

const onPositionModeChange = () => {
    if ((classForm.position_mode === 'before' || classForm.position_mode === 'after') && !classForm.relative_class_id) {
        if (availableRelativeClasses.value.length > 0) {
            classForm.relative_class_id = availableRelativeClasses.value[availableRelativeClasses.value.length - 1].id;
        }
    }
    recalculateLevelOrder();
};

const onRelativeClassChange = () => {
    recalculateLevelOrder();
};

const placementSummaryText = computed(() => {
    if (availableRelativeClasses.value.length === 0) {
        return 'First academic class in the school hierarchy (Level 1).';
    }

    if (classForm.position_mode === 'start') {
        return 'Will become Level 1 (lowest class in the school). All existing classes will automatically shift up.';
    }

    if (classForm.position_mode === 'end') {
        const maxLevel = Math.max(...availableRelativeClasses.value.map((c) => c.level_order));
        return `Will be placed at the very end as Level ${maxLevel + 1} (highest class in the school).`;
    }

    const target = props.classes.find((c) => c.id === classForm.relative_class_id);
    const targetName = target ? target.name : 'selected class';

    if (classForm.position_mode === 'before') {
        return `Will be placed directly before ${targetName} (lower order). ${targetName} and subsequent classes will automatically shift up.`;
    }

    if (classForm.position_mode === 'after') {
        return `Will be placed directly after ${targetName} (higher order). Classes above it will automatically shift up.`;
    }

    return `Custom sequence level: ${classForm.level_order}. Conflicting classes will automatically shift up.`;
});

const onStagePresetChange = (preset: string) => {
    selectedStagePreset.value = preset;
    if (preset !== 'custom') {
        classForm.stage = preset;
    } else {
        classForm.stage = customStageName.value.trim() || 'custom';
    }
};

const onCustomStageInput = () => {
    if (selectedStagePreset.value === 'custom') {
        classForm.stage = customStageName.value.trim().toLowerCase().replace(/\s+/g, '_') || 'custom';
    }
};

const openCreateClassModal = () => {
    editingClass.value = null;
    classForm.reset();
    classForm.clearErrors();
    selectedStagePreset.value = 'primary';
    customStageName.value = '';
    classForm.name = '';
    classForm.stage = 'primary';
    if (availableRelativeClasses.value.length > 0) {
        classForm.position_mode = 'after';
        classForm.relative_class_id = availableRelativeClasses.value[availableRelativeClasses.value.length - 1].id;
    } else {
        classForm.position_mode = 'start';
        classForm.relative_class_id = null;
    }
    recalculateLevelOrder();
    classForm.create_sections = false;
    classForm.initial_sections = 'A, B';
    classForm.section_capacity = 50;
    isClassModalOpen.value = true;
};

const openEditClassModal = (cls: SchoolClass) => {
    editingClass.value = cls;
    classForm.clearErrors();
    classForm.name = cls.name;
    classForm.stage = cls.stage;
    classForm.level_order = cls.level_order;
    classForm.position_mode = 'custom';
    classForm.relative_class_id = null;
    classForm.create_sections = false;

    // Check if stage is in presets
    const standardPresets = ['kindergarten', 'nursery', 'primary', 'junior_secondary', 'senior_secondary'];
    if (standardPresets.includes(cls.stage)) {
        selectedStagePreset.value = cls.stage;
        customStageName.value = '';
    } else {
        selectedStagePreset.value = 'custom';
        customStageName.value = cls.stage.replace(/_/g, ' ');
    }

    isClassModalOpen.value = true;
};

const submitClassForm = () => {
    if (selectedStagePreset.value === 'custom') {
        if (!customStageName.value.trim()) {
            toast.error('Please enter a custom educational stage name.');
            return;
        }
        classForm.stage = customStageName.value.trim().toLowerCase().replace(/\s+/g, '_');
    }

    if (editingClass.value) {
        classForm.put(updateClass.url({ class: editingClass.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                isClassModalOpen.value = false;
                toast.success(`Academic class '${classForm.name}' updated successfully.`);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    } else {
        classForm.post(storeClass.url(), {
            preserveScroll: true,
            onSuccess: () => {
                isClassModalOpen.value = false;
                toast.success(`Academic class '${classForm.name}' registered successfully.`);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    }
};

// =========================================================================
// SECTION MODAL STATES
// =========================================================================
const isSectionModalOpen = ref(false);
const editingSection = ref<Section | null>(null);
const currentClassForSection = ref<SchoolClass | null>(null);

const sectionForm = useForm({
    class_id: 0,
    name: '',
    capacity: 50,
});

const openCreateSectionModal = (cls: SchoolClass) => {
    editingSection.value = null;
    currentClassForSection.value = cls;
    sectionForm.reset();
    sectionForm.clearErrors();
    sectionForm.class_id = cls.id;
    sectionForm.name = '';
    sectionForm.capacity = 50;
    isSectionModalOpen.value = true;
};

const openEditSectionModal = (cls: SchoolClass, sec: Section) => {
    editingSection.value = sec;
    currentClassForSection.value = cls;
    sectionForm.clearErrors();
    sectionForm.class_id = cls.id;
    sectionForm.name = sec.name;
    sectionForm.capacity = sec.capacity;
    isSectionModalOpen.value = true;
};

const submitSectionForm = () => {
    if (editingSection.value) {
        sectionForm.put(updateSection.url({ section: editingSection.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                isSectionModalOpen.value = false;
                toast.success('Section updated successfully.');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    } else {
        sectionForm.post(storeSection.url(), {
            preserveScroll: true,
            onSuccess: () => {
                isSectionModalOpen.value = false;
                toast.success('Section created successfully.');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    }
};

// =========================================================================
// DELETE MODAL STATES
// =========================================================================
const isDeleteModalOpen = ref(false);
const deleteTargetType = ref<'class' | 'section'>('class');
const deleteTargetId = ref<number | null>(null);
const deleteTargetName = ref<string>('');
const deleteForm = useForm({});

const openDeleteClassModal = (cls: SchoolClass) => {
    deleteTargetType.value = 'class';
    deleteTargetId.value = cls.id;
    deleteTargetName.value = `Class: ${cls.name}`;
    isDeleteModalOpen.value = true;
};

const openDeleteSectionModal = (sec: Section) => {
    deleteTargetType.value = 'section';
    deleteTargetId.value = sec.id;
    deleteTargetName.value = `Section: ${sec.full_name || sec.name}`;
    isDeleteModalOpen.value = true;
};

const confirmDelete = () => {
    if (!deleteTargetId.value) return;

    if (deleteTargetType.value === 'class') {
        deleteForm.delete(destroyClass.url({ class: deleteTargetId.value }), {
            preserveScroll: true,
            onSuccess: () => {
                isDeleteModalOpen.value = false;
                toast.success(`${deleteTargetName.value} deleted successfully.`);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    } else {
        deleteForm.delete(destroySection.url({ section: deleteTargetId.value }), {
            preserveScroll: true,
            onSuccess: () => {
                isDeleteModalOpen.value = false;
                toast.success(`${deleteTargetName.value} deleted successfully.`);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) toast.error(firstError);
            },
        });
    }
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <Head title="Academic Structure - Classes & Sections" />

        <!-- Header & Action Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Classes & Sections
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Organize educational levels (Kindergarten, Nursery, Primary, Secondary) and configure classroom sections.
                </p>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <Button
                    v-if="canCreateClass"
                    @click="openCreateClassModal"
                    class="gap-2 w-full sm:w-auto"
                >
                    <Plus class="size-4" />
                    <span>Add Class</span>
                </Button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
            <!-- Total Classes -->
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Total Classes</CardDescription>
                    <CardTitle class="text-2xl font-bold text-foreground">
                        {{ stats.total_classes }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">
                        Configured grade levels
                    </p>
                </CardContent>
            </Card>

            <!-- Total Sections -->
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Total Sections</CardDescription>
                    <CardTitle class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ stats.total_sections }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">
                        Active classroom sections
                    </p>
                </CardContent>
            </Card>

            <!-- Educational Stages -->
            <Card class="border-border/60 shadow-xs col-span-2 sm:col-span-1">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Educational Stages</CardDescription>
                    <CardTitle class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ availableFilterStages.length || 1 }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground truncate" :title="availableFilterStages.map(s => s.label).join(' & ')">
                        {{ availableFilterStages.map(s => s.label.replace(/\s*\(.*?\)/, '')).join(' & ') || 'Configured tiers' }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter Bar: Stage Filter -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 border-b border-border pb-3">
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                <Button
                    variant="ghost"
                    size="sm"
                    :class="[
                        'h-8 px-3 text-xs font-medium rounded-full shrink-0',
                        selectedStageFilter === 'all'
                            ? 'bg-secondary text-secondary-foreground shadow-xs font-semibold'
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                    @click="selectedStageFilter = 'all'"
                >
                    All Classes ({{ classes.length }})
                </Button>

                <Button
                    v-for="stg in availableFilterStages"
                    :key="stg.value"
                    variant="ghost"
                    size="sm"
                    :class="[
                        'h-8 px-3 text-xs font-medium rounded-full shrink-0',
                        selectedStageFilter === stg.value
                            ? 'bg-secondary text-secondary-foreground shadow-xs font-semibold'
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                    @click="selectedStageFilter = stg.value"
                >
                    {{ stg.label }} ({{ classes.filter(c => c.stage === stg.value).length }})
                </Button>
            </div>
        </div>

        <!-- Classes List & Nested Sections (Mobile-First Cards) -->
        <div class="space-y-4">
            <Card
                v-for="cls in filteredClasses"
                :key="cls.id"
                class="border-border/70 shadow-xs hover:border-border transition-all overflow-hidden"
            >
                <CardHeader class="p-4 sm:p-5 bg-card border-b border-border/50">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Class Title, Stage & Badges -->
                        <div class="flex items-start sm:items-center gap-3">
                            <div class="size-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0 border border-primary/20">
                                {{ cls.level_order }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <CardTitle class="text-lg font-bold text-foreground">
                                        {{ cls.name }}
                                    </CardTitle>
                                    <Badge
                                        variant="secondary"
                                        class="text-xs font-normal"
                                    >
                                        {{ cls.stage_label || cls.stage.replace(/_/g, ' ') }}
                                    </Badge>
                                </div>
                                <CardDescription class="text-xs mt-0.5 flex items-center gap-3 text-muted-foreground flex-wrap">
                                    <span>Progression Order: {{ cls.level_order }}</span>
                                    <span>&bull;</span>
                                    <span>{{ cls.sections?.length ?? 0 }} Sections Configured</span>
                                    <span>&bull;</span>
                                    <span>{{ cls.enrollments_count ?? 0 }} Students Enrolled</span>
                                </CardDescription>
                            </div>
                        </div>

                        <!-- Class Actions -->
                        <div class="flex items-center gap-2 self-end sm:self-auto">
                            <Button
                                v-if="canCreateSection"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 text-xs font-medium"
                                @click="openCreateSectionModal(cls)"
                            >
                                <Plus class="size-3.5" />
                                <span>Add Section</span>
                            </Button>

                            <Button
                                v-if="canUpdateClass"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                @click="openEditClassModal(cls)"
                                title="Edit Class"
                            >
                                <Edit2 class="size-3.5" />
                            </Button>

                            <Button
                                v-if="canDeleteClass"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-destructive"
                                @click="openDeleteClassModal(cls)"
                                title="Delete Class"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-4 sm:p-5 bg-muted/10">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between text-xs text-muted-foreground font-medium">
                            <span>Classroom Sections</span>
                            <span v-if="!cls.sections || cls.sections.length === 0" class="italic">
                                No sections defined for this class
                            </span>
                        </div>

                        <!-- Sections Grid -->
                        <div
                            v-if="cls.sections && cls.sections.length > 0"
                            class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div
                                v-for="sec in cls.sections"
                                :key="sec.id"
                                class="flex items-center justify-between p-3 rounded-lg border border-border/60 bg-card hover:bg-muted/20 transition-colors shadow-2xs"
                            >
                                <div class="space-y-0.5 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <Layers class="size-3.5 text-primary shrink-0" />
                                        <span class="font-bold text-sm text-foreground truncate">
                                            Section {{ sec.name }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted-foreground flex items-center gap-2">
                                        <span>Cap: {{ sec.capacity }}</span>
                                        <span>&bull;</span>
                                        <span :class="{'text-emerald-600 dark:text-emerald-400 font-medium': (sec.enrollments_count ?? 0) > 0}">
                                            {{ sec.enrollments_count ?? 0 }} enrolled
                                        </span>
                                    </div>
                                </div>

                                <!-- Section Actions -->
                                <div class="flex items-center gap-1 shrink-0 ml-2">
                                    <Button
                                        v-if="canUpdateSection"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-muted-foreground hover:text-foreground"
                                        @click="openEditSectionModal(cls, sec)"
                                        title="Edit Section"
                                    >
                                        <Edit2 class="size-3" />
                                    </Button>

                                    <Button
                                        v-if="canDeleteSection"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-muted-foreground hover:text-destructive"
                                        @click="openDeleteSectionModal(sec)"
                                        title="Delete Section"
                                    >
                                        <Trash2 class="size-3" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Sections Prompt -->
                        <div
                            v-else
                            class="flex flex-col items-center justify-center p-6 text-center border border-dashed border-border rounded-lg bg-card/50"
                        >
                            <Layers class="size-8 text-muted-foreground/50 mb-2" />
                            <p class="text-xs font-medium text-foreground">No sections created yet</p>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Add sections (e.g. A, B, Red, Green, Diamond, 1) to allocate students.
                            </p>
                            <Button
                                v-if="canCreateSection"
                                variant="outline"
                                size="sm"
                                class="mt-3 h-7 text-xs gap-1.5"
                                @click="openCreateSectionModal(cls)"
                            >
                                <Plus class="size-3" />
                                <span>Create First Section</span>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div
                v-if="filteredClasses.length === 0"
                class="flex flex-col items-center justify-center p-12 text-center border border-dashed border-border rounded-xl bg-card"
            >
                <GraduationCap class="size-12 text-muted-foreground/40 mb-3" />
                <h3 class="text-base font-semibold text-foreground">No Academic Classes Found</h3>
                <p class="text-sm text-muted-foreground max-w-sm mt-1">
                    No classes registered under this filter. Register a class to start structuring your school.
                </p>
                <Button
                    v-if="canCreateClass"
                    class="mt-4 gap-2"
                    @click="openCreateClassModal"
                >
                    <Plus class="size-4" />
                    <span>Register New Class</span>
                </Button>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- CREATE / EDIT CLASS MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isClassModalOpen" @update:open="(val) => isClassModalOpen = val">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingClass ? 'Edit Academic Class' : 'Register Academic Class' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingClass ? 'Update class details and educational stage.' : 'Add a new class level to your school academic structure.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitClassForm" class="space-y-4 py-2">
                    <!-- Class Name -->
                    <div class="space-y-1.5">
                        <Label for="class-name">Class Name *</Label>
                        <Input
                            id="class-name"
                            v-model="classForm.name"
                            placeholder="e.g. Primary 1, Nursery 1, JSS 1, SS 1, Grade 1"
                            required
                        />
                        <p class="text-xs text-muted-foreground">The formal display title of the class.</p>
                        <InputError :message="classForm.errors.name" />
                    </div>

                    <!-- Educational Stage Preset Selection -->
                    <div class="space-y-1.5">
                        <Label for="stage-preset">Educational Stage *</Label>
                        <select
                            id="stage-preset"
                            v-model="selectedStagePreset"
                            @change="onStagePresetChange(selectedStagePreset)"
                            class="w-full h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="kindergarten">Kindergarten / Pre-School</option>
                            <option value="nursery">Nursery / Creche</option>
                            <option value="primary">Primary School</option>
                            <option value="junior_secondary">Junior Secondary (JSS)</option>
                            <option value="senior_secondary">Senior Secondary (SS)</option>
                            <option value="custom">Custom Educational Stage...</option>
                        </select>
                        <InputError :message="classForm.errors.stage" />
                    </div>

                    <!-- Custom Stage Text Input (if custom selected) -->
                    <div v-if="selectedStagePreset === 'custom'" class="space-y-1.5 pl-3 border-l-2 border-primary/50">
                        <Label for="custom-stage-name">Custom Stage Name *</Label>
                        <Input
                            id="custom-stage-name"
                            v-model="customStageName"
                            @input="onCustomStageInput"
                            placeholder="e.g. Basic Education, Vocational, Sixth Form"
                            required
                        />
                        <p class="text-xs text-muted-foreground">Enter the custom stage or category name for this class.</p>
                    </div>

                    <!-- Placement & Progression Order -->
                    <div class="space-y-3 p-3.5 rounded-lg border border-border bg-muted/20">
                        <div class="space-y-0.5">
                            <Label class="text-sm font-semibold text-foreground">
                                Class Level & Progression Order *
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Determine where this class ranks in the school hierarchy (from lowest to highest).
                            </p>
                        </div>

                        <!-- Relative Placement Options (when other classes exist) -->
                        <div v-if="availableRelativeClasses.length > 0" class="space-y-2.5">
                            <div class="space-y-1.5">
                                <Label for="position-mode" class="text-xs text-muted-foreground font-medium">Positioning Mode</Label>
                                <select
                                    id="position-mode"
                                    v-model="classForm.position_mode"
                                    @change="onPositionModeChange"
                                    class="w-full h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="after">Higher than (comes after)...</option>
                                    <option value="before">Lower than (comes before)...</option>
                                    <option value="start">At the very beginning (Lowest class in school)</option>
                                    <option value="end">At the very end (Highest class in school)</option>
                                    <option value="custom">Set exact number manually</option>
                                </select>
                            </div>

                            <!-- Relative class target selector -->
                            <div v-if="classForm.position_mode === 'before' || classForm.position_mode === 'after'" class="space-y-1.5">
                                <Label for="relative-class" class="text-xs text-muted-foreground font-medium">
                                    {{ classForm.position_mode === 'before' ? 'Select class this comes BEFORE (Lower than):' : 'Select class this comes AFTER (Higher than):' }}
                                </Label>
                                <select
                                    id="relative-class"
                                    v-model="classForm.relative_class_id"
                                    @change="onRelativeClassChange"
                                    class="w-full h-9 px-3 py-1 text-sm bg-background border border-input rounded-md text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option v-for="c in availableRelativeClasses" :key="c.id" :value="c.id">
                                        {{ c.name }} (Level {{ c.level_order }} · {{ c.stage_label }})
                                    </option>
                                </select>
                            </div>

                            <!-- Dynamic Summary Preview Banner -->
                            <div class="p-2.5 rounded-md bg-primary/10 border border-primary/20 text-xs text-primary flex items-start gap-2">
                                <Info class="size-4 shrink-0 mt-0.5 text-primary" />
                                <span>{{ placementSummaryText }}</span>
                            </div>
                        </div>

                        <!-- Manual Number Input (Visible when custom mode selected or when no other classes exist) -->
                        <div v-if="classForm.position_mode === 'custom' || availableRelativeClasses.length === 0" class="space-y-1.5 pt-1">
                            <div class="flex items-center justify-between">
                                <Label for="level-order" class="text-xs font-medium">Numeric Sequence Order</Label>
                                <span class="text-[11px] text-muted-foreground font-mono">1 = Lowest</span>
                            </div>
                            <Input
                                id="level-order"
                                type="number"
                                min="1"
                                max="100"
                                v-model.number="classForm.level_order"
                                required
                            />
                            <p class="text-xs text-muted-foreground">
                                Existing classes at or above this number will automatically shift up.
                            </p>
                        </div>
                        <InputError :message="classForm.errors.level_order" />
                    </div>

                    <!-- Dynamic Initial Sections (Only When Creating) -->
                    <div v-if="!editingClass" class="space-y-3 pt-2 border-t border-border">
                        <div class="flex items-start gap-2.5">
                            <Checkbox
                                id="create-sections"
                                :checked="classForm.create_sections"
                                @update:checked="(val: boolean) => classForm.create_sections = Boolean(val)"
                            />
                            <label for="create-sections" class="grid gap-1 leading-none cursor-pointer select-none">
                                <span class="text-sm font-medium">
                                    Create initial sections for this class
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    Optionally generate initial classroom sections right away.
                                </span>
                            </label>
                        </div>

                        <!-- Initial Sections Config Fields -->
                        <div v-if="classForm.create_sections" class="pl-6 space-y-3 pt-1">
                            <div class="space-y-1.5">
                                <Label for="initial-sections-input">Section Names (comma-separated)</Label>
                                <Input
                                    id="initial-sections-input"
                                    v-model="classForm.initial_sections"
                                    placeholder="e.g. A, B, C, D or Red, Green, Blue or 1, 2"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Enter names separated by commas (alphabets, colors, numbers, or names).
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="section-capacity">Capacity per Section</Label>
                                <Input
                                    id="section-capacity"
                                    type="number"
                                    min="1"
                                    max="500"
                                    v-model.number="classForm.section_capacity"
                                    placeholder="e.g. 50"
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isClassModalOpen = false"
                            :disabled="classForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="classForm.processing"
                            class="gap-2"
                        >
                            <Spinner v-if="classForm.processing" class="size-4" />
                            <span>{{ editingClass ? 'Save Changes' : 'Register Class' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ================================================================= -->
        <!-- CREATE / EDIT SECTION MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isSectionModalOpen" @update:open="(val) => isSectionModalOpen = val">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingSection ? 'Edit Section' : 'Create Section' }}</DialogTitle>
                    <DialogDescription>
                        Configure classroom section and student capacity for {{ currentClassForSection?.name }}.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitSectionForm" class="space-y-4 py-2">
                    <!-- Section Name -->
                    <div class="space-y-1.5">
                        <Label for="section-name">Section Name *</Label>
                        <Input
                            id="section-name"
                            v-model="sectionForm.name"
                            placeholder="e.g. A, Red, Diamond, 1, Blue"
                            required
                            maxlength="50"
                        />
                        <p class="text-xs text-muted-foreground">
                            Schools can name sections using alphabets (A, B), colors (Red, Blue), numbers (1, 2), or custom names.
                        </p>
                        <InputError :message="sectionForm.errors.name" />
                    </div>

                    <!-- Capacity -->
                    <div class="space-y-1.5">
                        <Label for="section-capacity">Student Capacity *</Label>
                        <Input
                            id="section-capacity"
                            type="number"
                            min="1"
                            max="500"
                            v-model.number="sectionForm.capacity"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Maximum number of students permitted in this classroom section.
                        </p>
                        <InputError :message="sectionForm.errors.capacity" />
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSectionModalOpen = false"
                            :disabled="sectionForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="sectionForm.processing"
                            class="gap-2"
                        >
                            <Spinner v-if="sectionForm.processing" class="size-4" />
                            <span>{{ editingSection ? 'Update Section' : 'Save Section' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ================================================================= -->
        <!-- DELETE CONFIRMATION MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isDeleteModalOpen" @update:open="(val) => isDeleteModalOpen = val">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <div class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-5" />
                        <DialogTitle>Confirm Deletion</DialogTitle>
                    </div>
                    <DialogDescription>
                        Are you sure you want to permanently delete <span class="font-semibold text-foreground">{{ deleteTargetName }}</span>?
                    </DialogDescription>
                </DialogHeader>

                <p class="text-xs text-muted-foreground">
                    This action cannot be undone. Any record with active student enrollments, assigned subjects, or teacher allocations cannot be deleted.
                </p>

                <DialogFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDeleteModalOpen = false"
                        :disabled="deleteForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="confirmDelete"
                        class="gap-2"
                    >
                        <Spinner v-if="deleteForm.processing" class="size-4" />
                        <span>Delete Permanently</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
