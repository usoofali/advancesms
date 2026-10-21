<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BookOpen,
    Calendar,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    FileText,
    GraduationCap,
    ImageIcon,
    Megaphone,
    Sparkles,
    Users,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import FrontendLayout from '@/layouts/FrontendLayout.vue';
import type { FrontendAnnouncement, FrontendGallery, FrontendSlider } from '@/types/frontend';
import type { SchoolSetting } from '@/types/school';

interface Props {
    sliders: FrontendSlider[];
    announcements: FrontendAnnouncement[];
    stats: {
        students: number;
        teachers: number;
        classes: number;
        subjects: number;
    };
    principal: {
        name?: string | null;
        message?: string | null;
        photo_url?: string | null;
        stamp_url?: string | null;
    };
    galleryPreview: FrontendGallery[];
    coreValues: string[];
}

const props = defineProps<Props>();

const page = usePage();
const school = computed(() => page.props.school as SchoolSetting | null);

// Slider carousel state
const activeSlideIndex = ref(0);
let slideInterval: ReturnType<typeof setInterval> | null = null;

const currentSlide = computed(() => {
    if (props.sliders.length === 0) {
        return null;
    }
    return props.sliders[activeSlideIndex.value] ?? props.sliders[0];
});

const nextSlide = () => {
    if (props.sliders.length > 1) {
        activeSlideIndex.value = (activeSlideIndex.value + 1) % props.sliders.length;
    }
};

const prevSlide = () => {
    if (props.sliders.length > 1) {
        activeSlideIndex.value = (activeSlideIndex.value - 1 + props.sliders.length) % props.sliders.length;
    }
};

onMounted(() => {
    if (props.sliders.length > 1) {
        slideInterval = setInterval(nextSlide, 7000);
    }
});

onUnmounted(() => {
    if (slideInterval) {
        clearInterval(slideInterval);
    }
});
</script>

<template>
    <FrontendLayout>
        <Head :title="school?.school_name ? `${school.school_name} - Home` : 'Welcome to our Institution'" />

        <!-- 1. Hero Section / Interactive Slider -->
        <section class="relative bg-slate-900 text-white overflow-hidden min-h-[500px] lg:min-h-[580px] flex items-center">
            <!-- Background Image / Decorative Gradient Overlay -->
            <div class="absolute inset-0 z-0 overflow-hidden">
                <img
                    v-if="currentSlide?.image_url"
                    :src="currentSlide.image_url"
                    :alt="currentSlide.title"
                    class="w-full h-full object-cover opacity-75 sm:opacity-85 scale-105 transition-transform duration-1000"
                />
                <div class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-900/70 to-slate-900/25" />
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent sm:hidden" />
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.2),transparent_50%)]" />
            </div>

            <!-- Content -->
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
                <div class="max-w-3xl space-y-6">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-400 text-xs font-semibold tracking-wide">
                        <Sparkles class="size-3.5" />
                        <span>{{ currentSlide?.badge || `Welcome to ${school?.school_name || 'Our Academy'}` }}</span>
                    </div>

                    <!-- Slide Headline -->
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
                        {{ currentSlide?.title || 'Empowering Future Leaders Through Science, Character & Innovation' }}
                    </h1>

                    <!-- Subtitle -->
                    <p class="text-base sm:text-lg text-slate-300 leading-relaxed font-light">
                        {{ currentSlide?.subtitle || school?.about_text || 'An accredited institution committed to academic rigor, discipline, and technological literacy for the next generation of global pioneers.' }}
                    </p>

                    <!-- CTAs -->
                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <Link
                            :href="currentSlide?.primary_button_url || '/academics'"
                            class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-6 py-3 rounded-xl shadow-lg shadow-amber-500/20 transition-all hover:scale-102 text-sm"
                        >
                            <span>{{ currentSlide?.primary_button_text || 'Explore Academics' }}</span>
                            <ArrowRight class="size-4" />
                        </Link>

                        <Link
                            :href="currentSlide?.secondary_button_url || '/contact'"
                            class="inline-flex items-center gap-2 bg-slate-800/80 hover:bg-slate-700 text-white font-semibold px-6 py-3 rounded-xl border border-slate-700 backdrop-blur-xs transition-colors text-sm"
                        >
                            <span>{{ currentSlide?.secondary_button_text || 'Contact Admissions' }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Slider Navigation Controls (if multiple slides) -->
                <div v-if="sliders.length > 1" class="flex items-center gap-3 pt-10">
                    <button
                        type="button"
                        @click="prevSlide"
                        class="p-2 rounded-full bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition-colors"
                        aria-label="Previous slide"
                    >
                        <ChevronLeft class="size-4" />
                    </button>

                    <div class="flex items-center gap-2">
                        <button
                            v-for="(_, index) in sliders"
                            :key="index"
                            type="button"
                            @click="activeSlideIndex = index"
                            :class="[
                                activeSlideIndex === index ? 'w-8 bg-amber-400' : 'w-2 bg-slate-600 hover:bg-slate-500',
                                'h-2 rounded-full transition-all duration-300'
                            ]"
                            :aria-label="`Slide ${index + 1}`"
                        />
                    </div>

                    <button
                        type="button"
                        @click="nextSlide"
                        class="p-2 rounded-full bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition-colors"
                        aria-label="Next slide"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                </div>
            </div>
        </section>

        <!-- 2. Dynamic Metric Stats Counter Strip -->
        <section class="relative z-20 -mt-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 bg-white rounded-2xl p-6 shadow-xl border border-slate-100">
                <div class="flex items-center gap-4 p-2">
                    <div class="size-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <Users class="size-6" />
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                            {{ stats.students }}
                        </div>
                        <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Enrolled Students
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-2">
                    <div class="size-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <GraduationCap class="size-6" />
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                            {{ stats.teachers }}
                        </div>
                        <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Qualified Teachers
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-2">
                    <div class="size-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <BookOpen class="size-6" />
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                            {{ stats.classes }}
                        </div>
                        <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Academic Classes
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-2">
                    <div class="size-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                        <Award class="size-6" />
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                            {{ stats.subjects }}
                        </div>
                        <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Offered Subjects
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Principal's Welcome & Institutional Story -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <!-- Left: Head of School Profile Card -->
                <div class="lg:col-span-5">
                    <div class="relative bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-3xl p-8 shadow-xl overflow-hidden border border-slate-700">
                        <div class="flex flex-col items-center text-center space-y-4">
                            <!-- Principal Photo -->
                            <div class="relative size-36 rounded-2xl bg-slate-800 border-2 border-amber-400 overflow-hidden shadow-md">
                                <img
                                    v-if="principal.photo_url"
                                    :src="principal.photo_url"
                                    :alt="principal.name || 'Head of School'"
                                    class="size-full object-cover"
                                />
                                <div v-else class="size-full flex items-center justify-center text-slate-500 bg-slate-800">
                                    <GraduationCap class="size-16 text-amber-400" />
                                </div>
                            </div>

                            <div>
                                <h3 class="text-lg font-bold text-white">
                                    {{ principal.name || school?.principal_name || 'Dr. Ibrahim Gusau' }}
                                </h3>
                                <p class="text-xs text-amber-400 font-medium">
                                    Principal / Head of Institution
                                </p>
                            </div>

                            <p class="text-xs text-slate-300 font-serif italic leading-relaxed">
                                "{{ principal.message || 'We strive tirelessly to nurture an environment where academic rigor meets moral uprightness, enabling every scholar to shine with purpose.' }}"
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right: About Narrative & Core Pillars -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="space-y-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600 bg-amber-50 px-3 py-1 rounded-full border border-amber-200">
                            Our Mission & Philosophy
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Building a Foundation for Lifetime Achievement
                        </h2>
                    </div>

                    <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                        {{ school?.about_text || 'Established to champion holistic secondary education, our academic structure integrates competitive science, humanities, and technical expertise to develop well-rounded, disciplined global citizens.' }}
                    </p>

                    <div v-if="school?.mission" class="bg-slate-50 rounded-2xl p-5 border border-slate-200">
                        <h4 class="text-xs font-bold uppercase text-slate-700 tracking-wider mb-1">Our Mission</h4>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">{{ school.mission }}</p>
                    </div>

                    <!-- Core Values Bullets -->
                    <div v-if="coreValues.length > 0" class="space-y-3 pt-2">
                        <h4 class="text-xs font-bold uppercase text-slate-800 tracking-wider">Institutional Values</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div
                                v-for="value in coreValues"
                                :key="value"
                                class="flex items-center gap-2.5 text-xs sm:text-sm font-semibold text-slate-800 bg-white p-3 rounded-xl border border-slate-200 shadow-xs"
                            >
                                <CheckCircle2 class="size-4 text-emerald-500 shrink-0" />
                                <span>{{ value }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <Link
                            href="/about"
                            class="inline-flex items-center gap-2 text-sm font-bold text-slate-900 hover:text-amber-600 transition-colors"
                        >
                            <span>Read Complete Institutional Story</span>
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. Latest Announcements & Circulars -->
        <section class="bg-slate-100/70 py-16 border-y border-slate-200/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
                    <div>
                        <div class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-amber-600 mb-2">
                            <Megaphone class="size-4" />
                            <span>Official Noticeboard</span>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                            Latest Announcements & Circulars
                        </h2>
                    </div>

                    <Link
                        href="/announcements"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 hover:text-slate-900 bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-xs transition-colors"
                    >
                        <span>View All Notices</span>
                        <ChevronRight class="size-3.5" />
                    </Link>
                </div>

                <div v-if="announcements.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        v-for="item in announcements"
                        :key="item.id"
                        class="group flex flex-col bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-all hover:-translate-y-1"
                    >
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="inline-block bg-slate-100 text-slate-700 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border border-slate-200">
                                {{ item.category }}
                            </span>
                            <span v-if="item.is_pinned" class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                Pinned
                            </span>
                        </div>

                        <h3 class="font-bold text-slate-900 text-sm group-hover:text-amber-600 transition-colors line-clamp-2 mb-2">
                            {{ item.title }}
                        </h3>

                        <p class="text-xs text-slate-500 line-clamp-3 mb-4 flex-1">
                            {{ item.summary || item.content }}
                        </p>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                            <span class="flex items-center gap-1">
                                <Calendar class="size-3" />
                                {{ item.published_at ? new Date(item.published_at).toLocaleDateString() : 'Recent' }}
                            </span>

                            <Link
                                :href="`/announcements/${item.slug}`"
                                class="text-amber-600 font-bold hover:text-amber-700 inline-flex items-center gap-0.5"
                            >
                                <span>Read</span>
                                <ChevronRight class="size-3" />
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200">
                    <FileText class="size-10 text-slate-300 mx-auto mb-2" />
                    <p class="text-xs text-slate-500 font-medium">No published notices at the moment.</p>
                </div>
            </div>
        </section>

        <!-- 5. Campus Photo Gallery Preview -->
        <section v-if="galleryPreview.length > 0" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Campus Life</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mt-1">
                        Moments, Facilities & Achievements
                    </h2>
                </div>

                <Link
                    href="/gallery"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg transition-colors"
                >
                    <ImageIcon class="size-3.5" />
                    <span>View Entire Album</span>
                </Link>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div
                    v-for="photo in galleryPreview"
                    :key="photo.id"
                    class="group relative aspect-square rounded-xl overflow-hidden bg-slate-100 border border-slate-200"
                >
                    <img
                        :src="photo.image_url || photo.image_path"
                        :alt="photo.title"
                        class="size-full object-cover group-hover:scale-110 transition-transform duration-500"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity p-2 flex items-end">
                        <span class="text-[10px] font-medium text-white line-clamp-1">{{ photo.title }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. Prospective Admissions CTA Banner -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 rounded-3xl p-8 sm:p-12 text-white shadow-xl relative overflow-hidden">
                <div class="max-w-2xl space-y-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-400">Admissions & Prospective Inquiries</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                        Begin Your Child's Journey of Excellence Today
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                        Learn about our junior and senior secondary academic streams, entrance assessment timetables, and download the full institutional syllabus.
                    </p>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <Link
                            href="/academics"
                            class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold px-5 py-2.5 rounded-xl text-xs shadow-md transition-colors"
                        >
                            <BookOpen class="size-4" />
                            <span>Academic Subjects & Prospectus</span>
                        </Link>
                        <Link
                            href="/contact"
                            class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white font-semibold px-5 py-2.5 rounded-xl border border-slate-700 text-xs transition-colors"
                        >
                            <span>Schedule Campus Visit</span>
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>
