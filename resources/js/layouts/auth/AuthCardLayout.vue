<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { GraduationCap } from '@lucide/vue';
import { computed } from 'vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
        class="relative flex min-h-svh flex-col items-center justify-center gap-6 app-textured-bg p-6 md:p-10 transition-colors duration-200"
    >
        <!-- Top Navigation Action (Theme Switcher & Public Home) -->
        <div class="absolute top-4 right-4 sm:top-6 sm:right-6 flex items-center gap-2 z-10">
            <Link
                :href="home()"
                class="hidden sm:inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground px-3 py-1.5 rounded-md border border-border/60 bg-background/60 backdrop-blur-xs hover:bg-muted/50 transition-colors shadow-2xs"
            >
                <span>Return to Website</span>
            </Link>
            <ThemeToggle variant="outline" class="border-border/70 bg-background/70 backdrop-blur-xs shadow-2xs" />
        </div>

        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex flex-col items-center gap-3 self-center text-center group"
            >
                <div class="size-14 rounded-2xl bg-card border border-border/80 flex items-center justify-center p-2 text-foreground shadow-sm ring-1 ring-border/40 overflow-hidden transition-transform duration-200 group-hover:scale-105">
                    <img
                        v-if="school?.logo_url"
                        :src="school.logo_url"
                        :alt="schoolName"
                        class="size-full object-contain"
                    />
                    <GraduationCap v-else class="size-8 text-primary" />
                </div>
                <div class="space-y-0.5">
                    <span class="font-extrabold text-foreground text-lg tracking-tight block">
                        {{ schoolName }}
                    </span>
                    <span class="text-xs text-muted-foreground font-medium block">
                        Academic & Administration Portal
                    </span>
                </div>
            </Link>

            <div class="flex flex-col gap-4">
                <Card class="rounded-2xl border-border/70 dark:border-border/50 bg-card/95 dark:bg-card/85 backdrop-blur-md shadow-xl shadow-black/5 dark:shadow-black/30 overflow-hidden">
                    <CardHeader class="px-8 text-center">
                        <CardTitle class="text-xl font-bold tracking-tight text-foreground">{{ title }}</CardTitle>
                        <CardDescription class="text-xs text-muted-foreground">
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-8 py-2">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
