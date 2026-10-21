<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ImageIcon,
    Maximize2,
    Sparkles,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import FrontendLayout from '@/layouts/FrontendLayout.vue';
import type { FrontendGallery } from '@/types/frontend';

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Props {
    items: PaginatedData<FrontendGallery>;
    categories: string[];
    selectedCategory?: string | null;
}

const props = defineProps<Props>();

// Lightbox state
const activePhotoIndex = ref<number | null>(null);

const openLightbox = (index: number) => {
    activePhotoIndex.value = index;
};

const closeLightbox = () => {
    activePhotoIndex.value = null;
};

const nextPhoto = () => {
    if (activePhotoIndex.value !== null && props.items.data.length > 0) {
        activePhotoIndex.value = (activePhotoIndex.value + 1) % props.items.data.length;
    }
};

const prevPhoto = () => {
    if (activePhotoIndex.value !== null && props.items.data.length > 0) {
        activePhotoIndex.value = (activePhotoIndex.value - 1 + props.items.data.length) % props.items.data.length;
    }
};
</script>

<template>
    <FrontendLayout>
        <Head title="Campus Photo Gallery - Life & Facilities" />

        <!-- 1. Header Banner -->
        <div class="bg-slate-900 text-white py-12 lg:py-16 border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl space-y-3">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/20">
                        <ImageIcon class="size-3.5" />
                        <span>Visual Showcase</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                        Campus Life & Facilities Gallery
                    </h1>
                    <p class="text-sm sm:text-base text-slate-300">
                        Explore our laboratories, state-of-the-art classrooms, sports activities, and memorable student milestones.
                    </p>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
            <!-- 2. Category Filter Pills -->
            <div class="flex flex-wrap items-center gap-2 pb-4 border-b border-slate-200">
                <Link
                    href="/gallery"
                    :class="[
                        !selectedCategory
                            ? 'bg-slate-900 text-white font-bold'
                            : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-medium',
                        'px-4 py-2 rounded-xl text-xs transition-colors'
                    ]"
                >
                    All Photos
                </Link>

                <Link
                    v-for="cat in categories"
                    :key="cat"
                    :href="`/gallery?category=${cat}`"
                    :class="[
                        selectedCategory === cat
                            ? 'bg-amber-500 text-slate-950 font-bold'
                            : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-medium',
                        'px-4 py-2 rounded-xl text-xs capitalize transition-colors'
                    ]"
                >
                    {{ cat.replace('_', ' ') }}
                </Link>
            </div>

            <!-- 3. Photo Grid -->
            <div v-if="items.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <div
                    v-for="(photo, index) in items.data"
                    :key="photo.id"
                    @click="openLightbox(index)"
                    class="group relative aspect-4/3 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200 shadow-xs hover:shadow-lg transition-all cursor-pointer"
                >
                    <img
                        :src="photo.image_url || photo.image_path"
                        :alt="photo.title"
                        class="size-full object-cover group-hover:scale-105 transition-transform duration-500"
                    />

                    <!-- Overlay on Hover -->
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="size-8 rounded-full bg-white/20 backdrop-blur-xs flex items-center justify-center text-white">
                                <Maximize2 class="size-4" />
                            </span>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 bg-amber-400/20 px-2 py-0.5 rounded backdrop-blur-xs">
                                {{ photo.category.replace('_', ' ') }}
                            </span>
                            <h4 class="font-bold text-white text-sm mt-1 line-clamp-1">{{ photo.title }}</h4>
                            <p v-if="photo.caption" class="text-xs text-slate-300 line-clamp-1 mt-0.5">{{ photo.caption }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-20 bg-white rounded-3xl border border-dashed border-slate-200 space-y-3">
                <ImageIcon class="size-12 text-slate-300 mx-auto" />
                <h3 class="text-base font-bold text-slate-800">No photos in this category yet</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    New campus photo albums and event highlights are uploaded regularly by the media committee.
                </p>
                <div class="pt-2">
                    <Link href="/gallery" class="text-xs font-bold text-amber-600 hover:underline">
                        View All Categories
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="items.last_page > 1" class="flex items-center justify-center gap-3 pt-6">
                <Link
                    v-if="items.prev_page_url"
                    :href="items.prev_page_url"
                    class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Previous
                </Link>
                <span class="text-xs text-slate-500">
                    Page {{ items.current_page }} of {{ items.last_page }}
                </span>
                <Link
                    v-if="items.next_page_url"
                    :href="items.next_page_url"
                    class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Next
                </Link>
            </div>
        </div>

        <!-- 4. Interactive Lightbox Modal -->
        <div
            v-if="activePhotoIndex !== null && items.data[activePhotoIndex]"
            class="fixed inset-0 z-50 bg-slate-950/95 backdrop-blur-md flex items-center justify-center p-4"
        >
            <button
                type="button"
                @click="closeLightbox"
                class="absolute top-5 right-5 size-10 rounded-full bg-slate-800 text-slate-300 hover:text-white flex items-center justify-center transition-colors"
                aria-label="Close photo preview"
            >
                <X class="size-6" />
            </button>

            <!-- Nav Arrows -->
            <button
                v-if="items.data.length > 1"
                type="button"
                @click.stop="prevPhoto"
                class="absolute left-4 size-10 rounded-full bg-slate-800/80 text-white flex items-center justify-center hover:bg-slate-700 transition-colors"
                aria-label="Previous photo"
            >
                <ChevronLeft class="size-6" />
            </button>

            <button
                v-if="items.data.length > 1"
                type="button"
                @click.stop="nextPhoto"
                class="absolute right-4 size-10 rounded-full bg-slate-800/80 text-white flex items-center justify-center hover:bg-slate-700 transition-colors"
                aria-label="Next photo"
            >
                <ChevronRight class="size-6" />
            </button>

            <!-- Photo Presentation -->
            <div class="max-w-4xl w-full flex flex-col items-center space-y-4">
                <div class="max-h-[75vh] overflow-hidden rounded-2xl border border-slate-800 shadow-2xl">
                    <img
                        :src="items.data[activePhotoIndex].image_url || items.data[activePhotoIndex].image_path"
                        :alt="items.data[activePhotoIndex].title"
                        class="max-h-[75vh] w-auto object-contain mx-auto"
                    />
                </div>

                <div class="text-center text-white space-y-1">
                    <h3 class="text-base font-bold">{{ items.data[activePhotoIndex].title }}</h3>
                    <p v-if="items.data[activePhotoIndex].caption" class="text-xs text-slate-400">
                        {{ items.data[activePhotoIndex].caption }}
                    </p>
                    <span class="text-[10px] text-amber-400 font-mono block">
                        {{ activePhotoIndex + 1 }} / {{ items.data.length }}
                    </span>
                </div>
            </div>
        </div>
    </FrontendLayout>
</template>
