<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { GraduationCap } from '@lucide/vue';

const page = usePage();
const school = computed(() => (page.props.school || {}) as Record<string, any>);
const appName = computed(() => page.props.name || 'Laravel');
const schoolCode = computed(() => school.value?.school_code || school.value?.code || '');
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-transparent overflow-hidden"
    >
        <img
            v-if="school?.logo_url"
            :src="school.logo_url"
            :alt="appName"
            class="size-full object-contain"
        />
        <GraduationCap v-else class="size-5 text-amber-500 dark:text-amber-400" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold" :title="appName">
            {{ appName }}
        </span>
        <span v-if="schoolCode" class="truncate text-[10px] text-muted-foreground uppercase tracking-wider">
            {{ schoolCode }}
        </span>
    </div>
</template>
