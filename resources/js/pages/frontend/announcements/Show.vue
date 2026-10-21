<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    ChevronRight,
    Download,
    FileText,
    Megaphone,
    Paperclip,
    Share2,
    User,
} from '@lucide/vue';
import FrontendLayout from '@/layouts/FrontendLayout.vue';
import type { FrontendAnnouncement } from '@/types/frontend';

interface Props {
    announcement: FrontendAnnouncement;
    recentAnnouncements: FrontendAnnouncement[];
}

const props = defineProps<Props>();
</script>

<template>
    <FrontendLayout>
        <Head :title="`${announcement.title} - Official Announcement`" />

        <!-- 1. Header Banner -->
        <div class="bg-slate-900 text-white py-10 lg:py-14 border-b border-slate-800">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                <Link
                    href="/announcements"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors"
                >
                    <ArrowLeft class="size-3.5" />
                    <span>Back to All Circulars</span>
                </Link>

                <div class="flex items-center gap-2 pt-1">
                    <span class="inline-block bg-amber-500 text-slate-950 text-xs font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full">
                        {{ announcement.category }}
                    </span>
                    <span v-if="announcement.is_pinned" class="text-xs font-bold text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded border border-amber-400/20">
                        Pinned Circular
                    </span>
                </div>

                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight">
                    {{ announcement.title }}
                </h1>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-400 pt-1">
                    <span class="flex items-center gap-1.5">
                        <Calendar class="size-3.5 text-amber-400" />
                        Published: {{ announcement.published_at ? new Date(announcement.published_at).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) : 'Recent' }}
                    </span>
                    <span v-if="announcement.author?.name" class="flex items-center gap-1.5">
                        <User class="size-3.5 text-amber-400" />
                        By {{ announcement.author.name }}
                    </span>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
                <!-- 2. Left / Center: Article Content -->
                <article class="lg:col-span-8 bg-white p-6 sm:p-10 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <!-- Cover Image -->
                    <div
                        v-if="announcement.cover_image_url || announcement.cover_image_path"
                        class="rounded-2xl overflow-hidden aspect-16/9 bg-slate-100 border border-slate-200"
                    >
                        <img
                            :src="announcement.cover_image_url || announcement.cover_image_path"
                            :alt="announcement.title"
                            class="size-full object-cover"
                        />
                    </div>

                    <!-- Summary Callout -->
                    <div
                        v-if="announcement.summary"
                        class="p-4 bg-amber-50/70 border-l-4 border-amber-500 rounded-r-xl text-xs sm:text-sm text-slate-700 font-medium leading-relaxed"
                    >
                        {{ announcement.summary }}
                    </div>

                    <!-- Main Article Body -->
                    <div class="prose prose-slate max-w-none text-slate-700 text-sm sm:text-base leading-relaxed whitespace-pre-line space-y-4 font-normal">
                        {{ announcement.content }}
                    </div>

                    <!-- Attachment Download Card -->
                    <div
                        v-if="announcement.attachment_url || announcement.attachment_path"
                        class="mt-8 p-5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                    >
                        <div class="flex items-center gap-3">
                            <div class="size-11 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                                <FileText class="size-6" />
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 text-xs sm:text-sm">
                                    {{ announcement.attachment_name || 'Official Attached Circular Document' }}
                                </h4>
                                <span class="text-[11px] text-slate-500">Official attachment for this notice</span>
                            </div>
                        </div>

                        <a
                            :href="announcement.attachment_url || announcement.attachment_path"
                            target="_blank"
                            download
                            class="inline-flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2 rounded-xl text-xs shadow-xs transition-colors"
                        >
                            <Download class="size-3.5 text-amber-400" />
                            <span>Download Document</span>
                        </a>
                    </div>
                </article>

                <!-- 3. Right: Sidebar with Recent Notices -->
                <aside class="lg:col-span-4 space-y-6">
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                        <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                            <Megaphone class="size-4 text-amber-600" />
                            <h3 class="font-bold text-slate-900 text-sm">Recent Circulars</h3>
                        </div>

                        <div v-if="recentAnnouncements.length > 0" class="space-y-4">
                            <Link
                                v-for="item in recentAnnouncements"
                                :key="item.id"
                                :href="`/announcements/${item.slug}`"
                                class="group block space-y-1 pb-3 border-b border-slate-100 last:border-b-0 last:pb-0"
                            >
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 bg-amber-50 px-2 py-0.5 rounded">
                                    {{ item.category }}
                                </span>
                                <h4 class="font-bold text-slate-800 group-hover:text-amber-600 text-xs leading-snug line-clamp-2 transition-colors">
                                    {{ item.title }}
                                </h4>
                                <span class="text-[10px] text-slate-400 block">
                                    {{ item.published_at ? new Date(item.published_at).toLocaleDateString() : 'Recent' }}
                                </span>
                            </Link>
                        </div>

                        <div v-else class="text-xs text-slate-400 italic">
                            No other recent announcements.
                        </div>

                        <div class="pt-2">
                            <Link
                                href="/announcements"
                                class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 py-2 rounded-xl transition-colors"
                            >
                                <span>Browse Noticeboard Directory</span>
                                <ChevronRight class="size-3.5" />
                            </Link>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </FrontendLayout>
</template>
