<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { GraduationCap } from '@lucide/vue';
import { computed } from 'vue';
import type { SchoolSetting } from '@/types/school';

interface Props {
    title: string;
    subtitle?: string;
    session?: string;
    term?: string;
    sessionTerm?: string;
    className?: string;
    department?: string;
    classSection?: string;
    showOnScreen?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showOnScreen: false,
});

const page = usePage();
const school = computed(() => (page.props.school || {}) as Record<string, any>);
const schoolName = computed(() => {
    return (
        school.value?.school_name ||
        school.value?.name ||
        'YUM Academy'
    );
});
const schoolMotto = computed(() => school.value?.motto || '');
const schoolAddress = computed(() => school.value?.address || '');
const schoolPhone = computed(() => school.value?.phone || '');
const schoolEmail = computed(() => school.value?.email || '');
const schoolCode = computed(() => school.value?.school_code || school.value?.code || '');
const schoolLogo = computed(() => school.value?.logo_url || null);

const currentDateFormatted = computed(() => {
    return new Date().toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});
</script>

<template>
    <div
        :class="[
            'w-full pb-4 mb-4 border-b-2 border-black/80 text-black',
            showOnScreen ? 'block' : 'hidden print:block',
        ]"
    >
        <div class="flex items-start justify-between gap-4">
            <!-- Left: Logo & School Particulars -->
            <div class="flex items-start gap-3.5">
                <div class="size-16 shrink-0 rounded-lg border border-neutral-300 p-1 flex items-center justify-center bg-white">
                    <img
                        v-if="schoolLogo"
                        :src="schoolLogo"
                        :alt="schoolName"
                        class="size-full object-contain"
                    />
                    <GraduationCap v-else class="size-8 text-neutral-700" />
                </div>

                <div class="space-y-0.5">
                    <h1 class="text-xl font-black uppercase tracking-tight text-black leading-tight">
                        {{ schoolName }}
                    </h1>
                    <p v-if="schoolMotto" class="text-xs italic text-neutral-600 font-medium">
                        "{{ schoolMotto }}"
                    </p>
                    <p v-if="schoolAddress" class="text-xs text-neutral-700">
                        {{ schoolAddress }}
                    </p>
                    <div class="text-[11px] text-neutral-600 flex flex-wrap gap-x-3 pt-0.5">
                        <span v-if="schoolPhone">Tel: {{ schoolPhone }}</span>
                        <span v-if="schoolEmail">Email: {{ schoolEmail }}</span>
                        <span v-if="schoolCode">School Code: {{ schoolCode }}</span>
                    </div>
                </div>
            </div>

            <!-- Right: Document Title & Metadata Box -->
            <div class="text-right shrink-0">
                <div class="inline-block border border-neutral-800 rounded px-3 py-1 bg-neutral-50 text-right">
                    <div class="text-xs font-black uppercase tracking-wider text-black">
                        {{ title }}
                    </div>
                    <div v-if="subtitle" class="text-[10px] text-neutral-600 font-medium">
                        {{ subtitle }}
                    </div>
                </div>

                <div class="text-[10px] text-neutral-600 mt-1.5 space-y-0.5">
                    <div v-if="sessionTerm || session || term" class="font-semibold text-black">
                        <span v-if="sessionTerm">{{ sessionTerm }}</span>
                        <template v-else>
                            <span v-if="session">{{ session }}</span>
                            <span v-if="session && term"> • </span>
                            <span v-if="term">{{ term }}</span>
                        </template>
                    </div>
                    <div v-if="classSection || className || department">
                        <span v-if="classSection" class="font-medium text-black">{{ classSection }}</span>
                        <template v-else>
                            <span v-if="className" class="font-medium text-black">Class: {{ className }}</span>
                            <span v-if="className && department"> • </span>
                            <span v-if="department">Dept: {{ department }}</span>
                        </template>
                    </div>
                    <div>
                        Printed: {{ currentDateFormatted }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
