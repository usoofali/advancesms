<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { GraduationCap } from '@lucide/vue';
import { computed } from 'vue';
import { home } from '@/routes';
import type { SchoolSetting } from '@/types/school';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const school = computed(() => page.props.school as SchoolSetting | null);
const schoolName = computed(() => school.value?.school_name || page.props.name || 'School Management System');
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-slate-50 dark:bg-slate-950 p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col items-center gap-3">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2.5 group text-center"
                    >
                        <div
                            class="size-16 rounded-2xl bg-slate-900 dark:bg-slate-800 flex items-center justify-center p-2 text-white shadow-md ring-1 ring-slate-200 dark:ring-slate-700 overflow-hidden transition-transform group-hover:scale-105"
                        >
                            <img
                                v-if="school?.logo_url"
                                :src="school.logo_url"
                                :alt="schoolName"
                                class="size-full object-contain"
                            />
                            <GraduationCap v-else class="size-9 text-amber-400" />
                        </div>
                        <div class="space-y-0.5">
                            <span class="font-extrabold text-slate-900 dark:text-white text-lg tracking-tight block">
                                {{ schoolName }}
                            </span>
                            <span v-if="school?.school_code" class="inline-block bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-mono font-bold px-2 py-0.5 rounded">
                                {{ school.school_code }}
                            </span>
                        </div>
                    </Link>
                    <div class="space-y-1.5 text-center mt-2">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ title }}</h1>
                        <p class="text-center text-xs text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
