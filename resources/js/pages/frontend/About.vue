<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BookOpen,
    Building2,
    CheckCircle2,
    Clock,
    Compass,
    GraduationCap,
    Lightbulb,
    MapPin,
    ShieldCheck,
    Target,
} from '@lucide/vue';
import { computed } from 'vue';
import FrontendLayout from '@/layouts/FrontendLayout.vue';
import type { SchoolSetting } from '@/types/school';

interface DepartmentItem {
    id: number;
    name: string;
    code: string;
    subjects_count?: number;
}

interface Props {
    settings: SchoolSetting;
    departments: DepartmentItem[];
}

const props = defineProps<Props>();

const page = usePage();
const school = computed(() => props.settings || (page.props.school as SchoolSetting | null));
</script>

<template>
    <FrontendLayout>
        <Head :title="`${school?.school_name || 'About Us'} - History & Leadership`" />

        <!-- 1. Page Header -->
        <div class="bg-slate-900 text-white py-12 lg:py-16 border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl space-y-3">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/20">
                        <Building2 class="size-3.5" />
                        <span>Institutional Heritage</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                        About Our Institution
                    </h1>
                    <p class="text-sm sm:text-base text-slate-300 font-serif italic">
                        "{{ school?.motto || 'Knowledge, Character, and Scientific Excellence' }}"
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Heritage Narrative & Mission/Vision -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16">
            <!-- Story Narrative -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
                <div class="lg:col-span-7 space-y-5">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-600">The Institution</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Fostering Academic Distinction & Strong Character
                    </h2>
                    <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                        {{ school?.about_text || 'Established to deliver high-quality, comprehensive secondary education, our institution provides learners with an intellectually stimulating and morally disciplined atmosphere designed for holistic growth.' }}
                    </p>
                    <p class="text-slate-600 leading-relaxed text-sm">
                        With modern science laboratories, dedicated ICT centers, and an expansive sports complex, students are supported in developing scientific inquiry, analytical problem-solving, and leadership skills.
                    </p>

                    <!-- Campus Visiting Hours Box -->
                    <div v-if="school?.office_hours" class="flex items-center gap-3 bg-amber-50 border border-amber-200/80 p-4 rounded-xl text-amber-900">
                        <Clock class="size-5 text-amber-600 shrink-0" />
                        <div class="text-xs">
                            <span class="font-bold">Administrative & Visiting Hours: </span>
                            <span>{{ school.office_hours }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right Card: Mission & Vision -->
                <div class="lg:col-span-5 space-y-4">
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-lg border border-slate-700 space-y-3">
                        <div class="flex items-center gap-2 text-amber-400 font-bold text-xs uppercase tracking-wider">
                            <Target class="size-4" />
                            <span>Our Mission</span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                            {{ school?.mission || 'To provide accessible, high-quality secondary education grounded in scientific curiosity, ethical leadership, and academic discipline.' }}
                        </p>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-3">
                        <div class="flex items-center gap-2 text-slate-900 font-bold text-xs uppercase tracking-wider">
                            <Compass class="size-4 text-amber-600" />
                            <span>Our Vision</span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            {{ school?.vision || 'To be a premier institution recognized for producing innovative scholars, ethical innovators, and disciplined leaders.' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 3. Core Values Grid -->
            <div v-if="school?.core_values && school.core_values.length > 0" class="space-y-6">
                <div class="text-center max-w-xl mx-auto space-y-1">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Guiding Pillars</span>
                    <h3 class="text-2xl font-extrabold text-slate-900">Our Core Values</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div
                        v-for="(val, idx) in school.core_values"
                        :key="idx"
                        class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-xs flex items-center gap-3"
                    >
                        <div class="size-9 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                            <ShieldCheck class="size-5" />
                        </div>
                        <span class="font-bold text-slate-800 text-sm">{{ val }}</span>
                    </div>
                </div>
            </div>

            <!-- 4. Principal's Full Address -->
            <div class="bg-slate-100 rounded-3xl p-8 sm:p-10 border border-slate-200">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
                    <div class="md:col-span-4 flex flex-col items-center text-center space-y-3">
                        <div class="size-32 rounded-2xl bg-white border border-slate-300 shadow-sm overflow-hidden flex items-center justify-center">
                            <img
                                v-if="school?.principal_photo_url"
                                :src="school.principal_photo_url"
                                :alt="school?.principal_name || 'Principal'"
                                class="size-full object-cover"
                            />
                            <GraduationCap v-else class="size-14 text-slate-400" />
                        </div>

                        <div>
                            <h4 class="font-bold text-slate-900 text-sm sm:text-base">
                                {{ school?.principal_name || 'Principal & Head of School' }}
                            </h4>
                            <p class="text-xs text-amber-600 font-medium">Chief Academic Administrator</p>
                        </div>
                    </div>

                    <div class="md:col-span-8 space-y-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Executive Welcome</span>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900">
                            Message from the Head of Institution
                        </h3>
                        <blockquote class="text-xs sm:text-sm text-slate-600 leading-relaxed font-serif italic border-l-2 border-amber-400 pl-4 py-1">
                            "{{ school?.principal_message || 'Welcome to our institution. Every student here is treated with personalized academic mentorship, continuous assessments, and an encouraging atmosphere to attain heights of excellence.' }}"
                        </blockquote>
                    </div>
                </div>
            </div>

            <!-- 5. Academic Departments Directory -->
            <div v-if="departments.length > 0" class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Academic Structure</span>
                        <h3 class="text-2xl font-extrabold text-slate-900 mt-0.5">Academic Departments</h3>
                    </div>
                    <Link
                        href="/academics"
                        class="text-xs font-bold text-slate-700 hover:text-slate-900 inline-flex items-center gap-1"
                    >
                        <span>View Subjects & Classes</span>
                        <ArrowRight class="size-3.5" />
                    </Link>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="dept in departments"
                        :key="dept.id"
                        class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-start gap-3.5"
                    >
                        <div class="size-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700 shrink-0">
                            <BookOpen class="size-5 text-amber-600" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-slate-900 text-sm">{{ dept.name }}</h4>
                                <span class="text-[10px] font-mono font-bold bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">
                                    {{ dept.code }}
                                </span>
                            </div>
                            <p v-if="dept.subjects_count !== undefined" class="text-xs text-slate-500 mt-1">
                                {{ dept.subjects_count }} registered subjects
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </FrontendLayout>
</template>
