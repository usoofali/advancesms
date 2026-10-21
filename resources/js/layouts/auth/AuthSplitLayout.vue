<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { GraduationCap } from '@lucide/vue';
import { computed } from 'vue';
import { home } from '@/routes';
import type { SchoolSetting } from '@/types/school';

const page = usePage();
const school = computed(() => page.props.school as SchoolSetting | null);
const schoolName = computed(() => school.value?.school_name || page.props.name || 'School Management System');

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col bg-muted p-10 text-white lg:flex dark:border-r"
        >
            <div class="absolute inset-0 bg-slate-950" />
            <Link
                :href="home()"
                class="relative z-20 flex items-center gap-3 text-lg font-bold"
            >
                <div class="size-10 rounded-xl bg-slate-900 flex items-center justify-center p-1 text-white border border-slate-700 overflow-hidden shrink-0">
                    <img
                        v-if="school?.logo_url"
                        :src="school.logo_url"
                        :alt="schoolName"
                        class="size-full object-contain"
                    />
                    <GraduationCap v-else class="size-6 text-amber-400" />
                </div>
                <div class="flex flex-col">
                    <span class="leading-tight">{{ schoolName }}</span>
                    <span v-if="school?.motto" class="text-xs text-slate-400 font-normal italic">"{{ school.motto }}"</span>
                </div>
            </Link>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-bold tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
