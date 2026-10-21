<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronRight,
    FileText,
    Megaphone,
    Paperclip,
    Pin,
    Search,
    User,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import FrontendLayout from '@/layouts/FrontendLayout.vue';
import type { FrontendAnnouncement } from '@/types/frontend';

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Props {
    announcements: PaginatedData<FrontendAnnouncement>;
    categories: string[];
    pinned: FrontendAnnouncement[];
    filters: {
        search?: string;
        category?: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || 'all');

const applyFilter = (category?: string) => {
    if (category !== undefined) {
        selectedCategory.value = category;
    }

    router.get(
        '/announcements',
        {
            search: search.value || undefined,
            category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const clearSearch = () => {
    search.value = '';
    applyFilter();
};
</script>

<template>
    <FrontendLayout>
        <Head title="Official Announcements & Circulars" />

        <!-- 1. Header Banner -->
        <div class="bg-slate-900 text-white py-12 lg:py-16 border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl space-y-4">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/20">
                        <Megaphone class="size-3.5" />
                        <span>Public Noticeboard</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                        Announcements & Circulars
                    </h1>
                    <p class="text-sm sm:text-base text-slate-300">
                        Official communications, examination schedules, holiday circulars, and institutional updates for students, staff, and parents.
                    </p>

                    <!-- Search Input -->
                    <form @submit.prevent="applyFilter()" class="max-w-md pt-2">
                        <div class="relative">
                            <Search class="absolute left-3.5 top-3 size-4 text-slate-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search circulars by keyword..."
                                class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs sm:text-sm pl-10 pr-10 py-2.5 rounded-xl border border-slate-700 focus:outline-hidden focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                            />
                            <button
                                v-if="search"
                                type="button"
                                @click="clearSearch"
                                class="absolute right-3 top-2.5 text-slate-400 hover:text-white"
                                aria-label="Clear search"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
            <!-- 2. Category Filter Pills -->
            <div class="flex flex-wrap items-center gap-2 pb-4 border-b border-slate-200">
                <button
                    type="button"
                    @click="applyFilter('all')"
                    :class="[
                        selectedCategory === 'all'
                            ? 'bg-slate-900 text-white font-bold'
                            : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-medium',
                        'px-4 py-2 rounded-xl text-xs transition-colors'
                    ]"
                >
                    All Circulars
                </button>

                <button
                    v-for="cat in categories"
                    :key="cat"
                    type="button"
                    @click="applyFilter(cat)"
                    :class="[
                        selectedCategory === cat
                            ? 'bg-amber-500 text-slate-950 font-bold'
                            : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-medium',
                        'px-4 py-2 rounded-xl text-xs capitalize transition-colors'
                    ]"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- 3. Pinned Announcements Strip (Page 1 only) -->
            <div v-if="pinned.length > 0 && announcements.current_page === 1 && !search" class="space-y-4">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-700">
                    <Pin class="size-4 rotate-45" />
                    <span>Pinned / Priority Notices</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div
                        v-for="item in pinned"
                        :key="item.id"
                        class="bg-amber-50/60 border-2 border-amber-300/80 rounded-2xl p-5 shadow-xs flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 bg-amber-200/60 px-2 py-0.5 rounded">
                                    {{ item.category }}
                                </span>
                                <span class="text-[10px] font-bold text-amber-700 flex items-center gap-1">
                                    <Pin class="size-3" /> Pinned
                                </span>
                            </div>
                            <h3 class="font-extrabold text-slate-900 text-base mb-2">
                                {{ item.title }}
                            </h3>
                            <p class="text-xs text-slate-600 line-clamp-3 mb-4">
                                {{ item.summary || item.content }}
                            </p>
                        </div>

                        <div class="pt-3 border-t border-amber-200 flex items-center justify-between text-xs">
                            <span class="text-slate-500 text-[11px]">
                                {{ item.published_at ? new Date(item.published_at).toLocaleDateString() : 'Recent' }}
                            </span>
                            <Link
                                :href="`/announcements/${item.slug}`"
                                class="text-amber-800 font-bold hover:underline inline-flex items-center gap-0.5"
                            >
                                <span>Read Notice</span>
                                <ChevronRight class="size-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Main Announcements Grid -->
            <div v-if="announcements.data.length > 0" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="item in announcements.data"
                        :key="item.id"
                        class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs flex flex-col justify-between hover:shadow-md transition-shadow group"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded-full border border-slate-200">
                                    {{ item.category }}
                                </span>
                                <span v-if="item.attachment_path" class="text-[10px] text-slate-400 font-medium flex items-center gap-1">
                                    <Paperclip class="size-3" /> Document
                                </span>
                            </div>

                            <h3 class="font-bold text-slate-900 text-base group-hover:text-amber-600 transition-colors line-clamp-2 mb-2">
                                {{ item.title }}
                            </h3>

                            <p class="text-xs text-slate-500 line-clamp-3 mb-4">
                                {{ item.summary || item.content }}
                            </p>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                            <span class="flex items-center gap-1">
                                <Calendar class="size-3" />
                                {{ item.published_at ? new Date(item.published_at).toLocaleDateString() : 'Recent' }}
                            </span>

                            <Link
                                :href="`/announcements/${item.slug}`"
                                class="text-amber-600 font-bold hover:text-amber-700 inline-flex items-center gap-0.5"
                            >
                                <span>Read Circular</span>
                                <ChevronRight class="size-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="announcements.last_page > 1" class="flex items-center justify-center gap-3 pt-6">
                    <Link
                        v-if="announcements.prev_page_url"
                        :href="announcements.prev_page_url"
                        class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Previous
                    </Link>
                    <span class="text-xs text-slate-500">
                        Page {{ announcements.current_page }} of {{ announcements.last_page }}
                    </span>
                    <Link
                        v-if="announcements.next_page_url"
                        :href="announcements.next_page_url"
                        class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Next
                    </Link>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-20 bg-white rounded-3xl border border-dashed border-slate-200 space-y-3">
                <FileText class="size-12 text-slate-300 mx-auto" />
                <h3 class="text-sm font-bold text-slate-800">No announcements found</h3>
                <p class="text-xs text-slate-500">
                    Try searching with different terms or check back soon for administrative circulars.
                </p>
            </div>
        </div>
    </FrontendLayout>
</template>
