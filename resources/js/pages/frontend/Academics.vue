<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    Calendar,
    CheckCircle2,
    Download,
    FileCheck,
    GraduationCap,
    Layers,
    Sparkles,
    UserCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import FrontendLayout from '@/layouts/FrontendLayout.vue';

interface SubjectItem {
    id: number;
    name: string;
    code: string;
    department_id?: number;
}

interface SectionItem {
    id: number;
    name: string;
}

interface ClassItem {
    id: number;
    name: string;
    stage?: string;
    stage_label?: string;
    level_order: number;
    sections?: SectionItem[];
    subjects?: SubjectItem[];
}

interface DepartmentItem {
    id: number;
    name: string;
    code: string;
    subjects_count?: number;
    subjects?: SubjectItem[];
}

interface Props {
    classes: ClassItem[];
    departments: DepartmentItem[];
    activeSession?: { id: number; name: string } | null;
    activeTerm?: { id: number; name: string } | null;
    prospectusUrl?: string | null;
}

const props = defineProps<Props>();

// Group classes by educational stage
const groupedClasses = computed(() => {
    const groups: Record<string, ClassItem[]> = {};

    props.classes.forEach((cls) => {
        const stageName = cls.stage_label || cls.stage || 'General Secondary';
        if (!groups[stageName]) {
            groups[stageName] = [];
        }
        groups[stageName].push(cls);
    });

    return groups;
});
</script>

<template>
    <FrontendLayout>
        <Head title="Academics, Subjects & Classes" />

        <!-- 1. Header Banner -->
        <div class="bg-slate-900 text-white py-12 lg:py-16 border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl space-y-3">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/20">
                        <GraduationCap class="size-3.5" />
                        <span>Subjects & Academic Structure</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                        Academic Programs & Classes
                    </h1>
                    <p class="text-sm sm:text-base text-slate-300">
                        A comprehensive academic program structured to instill critical thinking, STEM competencies, and literary mastery.
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Active Session & Prospectus Download Bar -->
        <div class="bg-white border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="size-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <Calendar class="size-4" />
                    </div>
                    <div class="text-xs">
                        <span class="font-bold text-slate-800">Active Academic Calendar: </span>
                        <span v-if="activeSession" class="text-emerald-700 font-semibold font-mono">{{ activeSession.name }}</span>
                        <span v-if="activeTerm" class="text-slate-600 font-medium"> ({{ activeTerm.name }})</span>
                        <span v-if="!activeSession" class="text-slate-500 italic">Current Session</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        v-if="prospectusUrl"
                        :href="prospectusUrl"
                        target="_blank"
                        download
                        class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-lg text-xs shadow-xs transition-colors"
                    >
                        <Download class="size-3.5" />
                        <span>Download Academic Prospectus (PDF)</span>
                    </a>
                    <Link
                        v-else
                        href="/contact"
                        class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold px-4 py-2 rounded-lg text-xs transition-colors"
                    >
                        <FileCheck class="size-3.5" />
                        <span>Request Admissions Prospectus</span>
                    </Link>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16">
            <!-- 3. Dynamic Educational Stages & Classes -->
            <div class="space-y-12">
                <div class="max-w-2xl space-y-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Dynamic Academic Ladder</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Classes & Learning Stages
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500">
                        Class arms, levels, and subject allocations are rendered directly from our active academic database.
                    </p>
                </div>

                <div v-for="(classList, stageName) in groupedClasses" :key="stageName" class="space-y-4">
                    <div class="flex items-center gap-2.5 pb-2 border-b border-slate-200">
                        <Layers class="size-5 text-amber-600" />
                        <h3 class="text-lg font-bold text-slate-900">{{ stageName }}</h3>
                        <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-medium">
                            {{ classList.length }} {{ classList.length === 1 ? 'Class' : 'Classes' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="cls in classList"
                            :key="cls.id"
                            class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4 hover:shadow-md transition-shadow"
                        >
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-slate-900 text-base">{{ cls.name }}</h4>
                                <span class="text-[10px] font-mono font-bold bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded">
                                    Level {{ cls.level_order }}
                                </span>
                            </div>

                            <!-- Sections / Class Arms -->
                            <div v-if="cls.sections && cls.sections.length > 0" class="space-y-1.5">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Class Arms / Sections</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="sec in cls.sections"
                                        :key="sec.id"
                                        class="text-xs font-semibold bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md"
                                    >
                                        {{ sec.name }}
                                    </span>
                                </div>
                            </div>

                            <!-- Subject Highlights -->
                            <div v-if="cls.subjects && cls.subjects.length > 0" class="space-y-1.5 pt-2 border-t border-slate-100">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">
                                    Subjects ({{ cls.subjects.length }})
                                </span>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="subj in cls.subjects.slice(0, 6)"
                                        :key="subj.id"
                                        class="text-[11px] bg-slate-50 text-slate-600 px-2 py-0.5 rounded border border-slate-100 font-medium"
                                    >
                                        {{ subj.name }}
                                    </span>
                                    <span v-if="cls.subjects.length > 6" class="text-[10px] text-amber-600 font-bold self-center px-1">
                                        +{{ cls.subjects.length - 6 }} more
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Academic Departments & Subjects -->
            <div v-if="departments.length > 0" class="space-y-6 pt-6 border-t border-slate-200">
                <div class="max-w-2xl space-y-1">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Departmental Structure</span>
                    <h2 class="text-2xl font-extrabold text-slate-900">Departments & Subjects Offered</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div
                        v-for="dept in departments"
                        :key="dept.id"
                        class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="size-9 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs">
                                    {{ dept.code }}
                                </div>
                                <h4 class="font-bold text-slate-900 text-base">{{ dept.name }}</h4>
                            </div>
                            <span class="text-xs text-slate-500 font-medium">
                                {{ dept.subjects?.length || dept.subjects_count || 0 }} Subjects
                            </span>
                        </div>

                        <div v-if="dept.subjects && dept.subjects.length > 0" class="flex flex-wrap gap-1.5 pt-2">
                            <span
                                v-for="s in dept.subjects"
                                :key="s.id"
                                class="text-xs bg-slate-50 text-slate-700 px-2.5 py-1 rounded-md border border-slate-100 font-medium"
                            >
                                {{ s.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Admissions Step-by-Step Procedure -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-3xl p-8 sm:p-12 shadow-xl space-y-8">
                <div class="max-w-2xl space-y-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-400">Admissions Guide</span>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-white">
                        Admission & Enrollment Process
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-300">
                        Follow our structured four-step admission procedure for prospective junior and senior secondary scholars.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700 space-y-3">
                        <div class="size-10 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-extrabold text-sm">
                            01
                        </div>
                        <h4 class="font-bold text-white text-sm">Application Inquiries</h4>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Obtain the registration packet at the school administrative desk or register interest through the contact desk.
                        </p>
                    </div>

                    <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700 space-y-3">
                        <div class="size-10 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-extrabold text-sm">
                            02
                        </div>
                        <h4 class="font-bold text-white text-sm">Document Verification</h4>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Submit previous academic report cards, birth certificates, and guardian contact credentials for vetting.
                        </p>
                    </div>

                    <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700 space-y-3">
                        <div class="size-10 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-extrabold text-sm">
                            03
                        </div>
                        <h4 class="font-bold text-white text-sm">Screening & Placement</h4>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Prospective candidates participate in an aptitude assessment to evaluate appropriate academic stage and stream.
                        </p>
                    </div>

                    <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700 space-y-3">
                        <div class="size-10 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-extrabold text-sm">
                            04
                        </div>
                        <h4 class="font-bold text-white text-sm">Enrollment & Portal ID</h4>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Successful candidates receive official admission numbers, class section allocations, and secure portal access credentials.
                        </p>
                    </div>
                </div>

                <div class="pt-4 flex flex-wrap items-center gap-4">
                    <Link
                        href="/contact"
                        class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold px-5 py-2.5 rounded-xl text-xs shadow-md transition-colors"
                    >
                        <span>Connect with Admissions Office</span>
                    </Link>
                </div>
            </div>
        </div>
    </FrontendLayout>
</template>
