<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    HelpCircle,
    Mail,
    Phone,
    Search,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FrontendLayout from '@/layouts/FrontendLayout.vue';
import type { FrontendFaq } from '@/types/frontend';

interface Props {
    faqGroups: Record<string, FrontendFaq[]>;
}

const props = defineProps<Props>();

const searchQuery = ref('');
const openItems = ref<Record<number, boolean>>({});

const toggleItem = (id: number) => {
    openItems.value[id] = !openItems.value[id];
};

// Filter faqs across all groups by searchQuery
const filteredGroups = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) {
        return props.faqGroups;
    }

    const result: Record<string, FrontendFaq[]> = {};

    Object.entries(props.faqGroups).forEach(([cat, list]) => {
        const matches = list.filter(
            (faq) =>
                faq.question.toLowerCase().includes(query) ||
                faq.answer.toLowerCase().includes(query)
        );
        if (matches.length > 0) {
            result[cat] = matches;
        }
    });

    return result;
});
</script>

<template>
    <FrontendLayout>
        <Head title="Frequently Asked Questions (FAQs)" />

        <!-- 1. Header Banner -->
        <div class="bg-slate-900 text-white py-12 lg:py-16 border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl space-y-4">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/20">
                        <HelpCircle class="size-3.5" />
                        <span>Help & Information Center</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                        Frequently Asked Questions
                    </h1>
                    <p class="text-sm sm:text-base text-slate-300">
                        Answers to the most common questions regarding student admissions, academic subjects, examinations, and portal logins.
                    </p>

                    <!-- Real-time Filter Search Input -->
                    <div class="pt-2 max-w-lg">
                        <div class="relative">
                            <Search class="absolute left-3.5 top-3 size-4 text-slate-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search questions (e.g. admissions, fees, prospectus, timetable)..."
                                class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs sm:text-sm pl-10 pr-4 py-2.5 rounded-xl border border-slate-700 focus:outline-hidden focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-colors"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14 space-y-12">
            <!-- 2. FAQ Accordion Groups -->
            <div v-if="Object.keys(filteredGroups).length > 0" class="space-y-10">
                <div
                    v-for="(faqs, category) in filteredGroups"
                    :key="category"
                    class="space-y-4"
                >
                    <h2 class="text-base font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-200 pb-2 flex items-center gap-2">
                        <span class="size-2 rounded-full bg-amber-500" />
                        <span>{{ category }}</span>
                        <span class="text-xs text-slate-400 font-normal">({{ faqs.length }})</span>
                    </h2>

                    <div class="space-y-3">
                        <div
                            v-for="faq in faqs"
                            :key="faq.id"
                            class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden transition-colors"
                        >
                            <button
                                type="button"
                                @click="toggleItem(faq.id)"
                                class="w-full text-left p-5 flex items-center justify-between gap-4 font-bold text-slate-900 text-sm hover:text-amber-600 focus:outline-hidden"
                            >
                                <span>{{ faq.question }}</span>
                                <ChevronDown
                                    :class="[
                                        openItems[faq.id] ? 'rotate-180 text-amber-600' : 'text-slate-400',
                                        'size-4 shrink-0 transition-transform duration-200'
                                    ]"
                                />
                            </button>

                            <div
                                v-if="openItems[faq.id]"
                                class="px-5 pb-5 pt-0 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100 mt-1 animate-in fade-in duration-200"
                            >
                                <p class="pt-3 whitespace-pre-line">{{ faq.answer }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty Search Results -->
            <div v-else class="text-center py-16 bg-white rounded-3xl border border-dashed border-slate-200 space-y-3">
                <HelpCircle class="size-10 text-slate-300 mx-auto" />
                <h3 class="text-sm font-bold text-slate-800">No matching questions found</h3>
                <p class="text-xs text-slate-500">
                    Try different keywords or get in touch with our help desk directly.
                </p>
            </div>

            <!-- 3. Still Have Questions Box -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-3xl p-8 sm:p-10 shadow-lg text-center space-y-4">
                <h3 class="text-xl sm:text-2xl font-extrabold text-white">
                    Still have questions or need assistance?
                </h3>
                <p class="text-xs sm:text-sm text-slate-300 max-w-md mx-auto">
                    Our administrative team is available during official hours to address inquiries, clarify admission guidelines, and assist parents.
                </p>

                <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                    <Link
                        href="/contact"
                        class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold px-5 py-2.5 rounded-xl text-xs shadow-md transition-colors"
                    >
                        <Mail class="size-4" />
                        <span>Send Us an Inquiry</span>
                    </Link>
                </div>
            </div>
        </div>
    </FrontendLayout>
</template>
