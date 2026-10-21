<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronRight,
    Clock,
    GraduationCap,
    Mail,
    MapPin,
    Menu,
    Phone,
    User,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Toaster } from '@/components/ui/sonner';
import type { SchoolSetting } from '@/types/school';

interface FlashProps {
    success?: string | null;
    error?: string | null;
    message?: string | null;
}

const page = usePage();
const school = computed(() => page.props.school as SchoolSetting | null);
const user = computed(() => page.props.auth?.user);
const academic = computed(() => page.props.academic as { current_session?: { name: string } | null; current_term?: { name: string } | null } | null);

const isMobileMenuOpen = ref(false);

const navigationItems = [
    { name: 'Home', href: '/' },
    { name: 'About Us', href: '/about' },
    { name: 'Academics', href: '/academics' },
    { name: 'Announcements', href: '/announcements' },
    { name: 'Campus Gallery', href: '/gallery' },
    { name: 'FAQs', href: '/faq' },
    { name: 'Contact Us', href: '/contact' },
];

const isCurrentRoute = (href: string): boolean => {
    const currentUrl = page.url;
    if (href === '/') {
        return currentUrl === '/';
    }
    return currentUrl.startsWith(href);
};

// Flash messages listener
watch(
    () => page.props.flash as FlashProps | undefined,
    (flash) => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.message) {
            toast.info(flash.message);
        }
    },
    { immediate: true, deep: true }
);
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-50 text-slate-800 antialiased selection:bg-amber-500 selection:text-white">
        <Toaster richColors position="top-right" />

        <!-- 1. Top Utility Bar -->
        <div class="bg-slate-900 text-slate-300 text-xs border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-x-5 gap-y-1">
                    <span v-if="school?.phone" class="inline-flex items-center gap-1.5 hover:text-white transition-colors">
                        <Phone class="size-3.5 text-amber-400" />
                        {{ school.phone }}
                    </span>
                    <span v-if="school?.email" class="inline-flex items-center gap-1.5 hover:text-white transition-colors">
                        <Mail class="size-3.5 text-amber-400" />
                        {{ school.email }}
                    </span>
                    <span v-if="school?.office_hours" class="hidden md:inline-flex items-center gap-1.5 text-slate-400">
                        <Clock class="size-3.5 text-amber-400" />
                        {{ school.office_hours }}
                    </span>
                </div>

                <div class="flex items-center gap-4">
                    <div v-if="academic?.current_session" class="hidden lg:flex items-center gap-1.5 text-amber-400 font-medium bg-amber-400/10 px-2 py-0.5 rounded-full border border-amber-400/20 text-[11px]">
                        <Calendar class="size-3.5" />
                        Session: {{ academic.current_session.name }} <span v-if="academic?.current_term">• {{ academic.current_term.name }}</span>
                    </div>

                    <Link
                        v-if="user"
                        href="/dashboard"
                        class="inline-flex items-center gap-1.5 font-semibold text-white bg-slate-800 hover:bg-slate-700 px-3 py-1 rounded transition-colors text-xs"
                    >
                        <User class="size-3.5 text-amber-400" />
                        Portal Dashboard
                    </Link>
                    <Link
                        v-else
                        href="/login"
                        class="inline-flex items-center gap-1.5 font-semibold text-slate-900 bg-amber-400 hover:bg-amber-300 px-3 py-1 rounded transition-colors text-xs"
                    >
                        <User class="size-3.5 text-slate-900" />
                        Portal Sign In
                    </Link>
                </div>
            </div>
        </div>

        <!-- 2. Institutional Header -->
        <header class="bg-white border-b border-slate-100 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-3.5 group">
                    <div class="size-12 rounded-xl bg-transparent flex items-center justify-center overflow-hidden shrink-0 transition-transform group-hover:scale-105">
                        <img
                            v-if="school?.logo_url"
                            :src="school.logo_url"
                            :alt="school.school_name || 'School Crest'"
                            class="size-full object-contain"
                        />
                        <GraduationCap v-else class="size-7 text-amber-500 dark:text-amber-400" />
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-extrabold text-slate-900 text-lg sm:text-xl tracking-tight leading-tight">
                                {{ school?.school_name || 'School Management System' }}
                            </span>
                            <span v-if="school?.school_code" class="hidden sm:inline-block bg-slate-100 text-slate-600 text-[10px] font-mono font-bold px-1.5 py-0.5 rounded border border-slate-200">
                                {{ school.school_code }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 italic mt-0.5 line-clamp-1 font-serif">
                            "{{ school?.motto || 'Excellence, Character & Scientific Discipline' }}"
                        </p>
                    </div>
                </Link>

                <!-- Desktop CTA Button -->
                <div class="hidden md:flex items-center gap-3">
                    <Link
                        href="/contact"
                        class="text-xs font-semibold text-slate-700 hover:text-slate-900 px-3 py-2 rounded-lg hover:bg-slate-100 transition-colors"
                    >
                        Inquire & Visit
                    </Link>
                    <Link
                        v-if="!user"
                        href="/login"
                        class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-medium text-xs px-4 py-2 rounded-lg shadow-sm transition-all hover:shadow"
                    >
                        <span>Staff & Student Portal</span>
                        <ChevronRight class="size-3.5 text-amber-400" />
                    </Link>
                    <Link
                        v-else
                        href="/dashboard"
                        class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-medium text-xs px-4 py-2 rounded-lg shadow-sm transition-all hover:shadow"
                    >
                        <span>Back to Portal</span>
                        <ChevronRight class="size-3.5 text-amber-400" />
                    </Link>
                </div>

                <!-- Mobile Hamburger Button -->
                <button
                    type="button"
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                    class="md:hidden p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-hidden"
                    aria-label="Toggle navigation menu"
                >
                    <Menu v-if="!isMobileMenuOpen" class="size-6" />
                    <X v-else class="size-6 text-red-500" />
                </button>
            </div>
        </header>

        <!-- 3. Navigation Bar (Sticky) -->
        <nav class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-md text-white border-b border-slate-800 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="hidden md:flex items-center justify-between">
                    <div class="flex items-center gap-1 py-1">
                        <Link
                            v-for="item in navigationItems"
                            :key="item.name"
                            :href="item.href"
                            :class="[
                                isCurrentRoute(item.href)
                                    ? 'bg-amber-500 text-slate-950 font-bold shadow-xs'
                                    : 'text-slate-300 hover:text-white hover:bg-slate-800/80 font-medium',
                                'px-3.5 py-2 rounded-md text-sm transition-all duration-150'
                            ]"
                        >
                            {{ item.name }}
                        </Link>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-slate-400">
                        <Link
                            href="/academics"
                            class="inline-flex items-center gap-1.5 text-amber-400 hover:text-amber-300 transition-colors font-medium"
                        >
                            <span>Admissions & Subjects</span>
                            <ChevronRight class="size-3" />
                        </Link>
                    </div>
                </div>

                <!-- Mobile Menu Drawer -->
                <div
                    v-if="isMobileMenuOpen"
                    class="md:hidden py-3 space-y-1 border-t border-slate-800 animate-in fade-in duration-200"
                >
                    <Link
                        v-for="item in navigationItems"
                        :key="item.name"
                        :href="item.href"
                        @click="isMobileMenuOpen = false"
                        :class="[
                            isCurrentRoute(item.href)
                                ? 'bg-amber-500 text-slate-950 font-bold'
                                : 'text-slate-200 hover:bg-slate-800 font-medium',
                            'block px-3 py-2 rounded-md text-base'
                        ]"
                    >
                        {{ item.name }}
                    </Link>

                    <div class="pt-4 pb-2 border-t border-slate-800 px-3">
                        <Link
                            v-if="!user"
                            href="/login"
                            @click="isMobileMenuOpen = false"
                            class="w-full flex items-center justify-center gap-2 bg-amber-400 text-slate-950 font-bold py-2.5 rounded-lg shadow-sm"
                        >
                            <User class="size-5" />
                            Portal Sign In
                        </Link>
                        <Link
                            v-else
                            href="/dashboard"
                            @click="isMobileMenuOpen = false"
                            class="w-full flex items-center justify-center gap-2 bg-slate-800 text-white font-bold py-2.5 rounded-lg border border-slate-700 shadow-sm"
                        >
                            <User class="size-5 text-amber-400" />
                            Return to Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- 4. Dynamic Page Slot -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- 5. Institutional Footer -->
        <footer class="bg-slate-950 text-slate-300 border-t border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                    <!-- Column 1: Identity & Motto -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-lg bg-transparent flex items-center justify-center overflow-hidden shrink-0">
                                <img
                                    v-if="school?.logo_url"
                                    :src="school.logo_url"
                                    :alt="school.school_name"
                                    class="size-full object-contain"
                                />
                                <GraduationCap v-else class="size-6 text-amber-400" />
                            </div>
                            <span class="font-bold text-white text-base leading-tight">
                                {{ school?.school_name || 'School Management System' }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-400 leading-relaxed font-serif italic">
                            "{{ school?.motto || 'Inspiring knowledge, scientific inquiry, and exemplary leadership.' }}"
                        </p>

                        <p class="text-xs text-slate-400 leading-relaxed line-clamp-3">
                            {{ school?.about_text || 'Committed to empowering young minds through rigorous academics, character development, and modern learning infrastructure.' }}
                        </p>

                        <div v-if="school?.social_links" class="flex items-center gap-3 pt-1">
                            <a
                                v-for="(url, platform) in school.social_links"
                                :key="platform"
                                :href="url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="size-8 rounded-full bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-400 hover:text-amber-400 hover:border-amber-400 transition-colors capitalize text-xs font-semibold"
                            >
                                {{ platform[0] }}
                            </a>
                        </div>
                    </div>

                    <!-- Column 2: Quick Links -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-4">
                            Institutional Links
                        </h3>
                        <ul class="space-y-2 text-xs">
                            <li>
                                <Link href="/about" class="hover:text-white transition-colors">About Our School</Link>
                            </li>
                            <li>
                                <Link href="/academics" class="hover:text-white transition-colors">Subjects & Classes</Link>
                            </li>
                            <li>
                                <Link href="/announcements" class="hover:text-white transition-colors">Announcements & Circulars</Link>
                            </li>
                            <li>
                                <Link href="/gallery" class="hover:text-white transition-colors">Campus Photo Gallery</Link>
                            </li>
                            <li>
                                <Link href="/faq" class="hover:text-white transition-colors">Frequently Asked Questions</Link>
                            </li>
                            <li>
                                <Link href="/contact" class="hover:text-white transition-colors">Inquiries & Campus Visits</Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Column 3: Contact & Visiting -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-4">
                            Contact & Office
                        </h3>
                        <ul class="space-y-3 text-xs text-slate-400">
                            <li v-if="school?.address" class="flex items-start gap-2">
                                <MapPin class="size-4 text-amber-400 shrink-0 mt-0.5" />
                                <span>{{ school.address }}</span>
                            </li>
                            <li v-if="school?.phone" class="flex items-center gap-2">
                                <Phone class="size-4 text-amber-400 shrink-0" />
                                <span>{{ school.phone }}</span>
                            </li>
                            <li v-if="school?.email" class="flex items-center gap-2">
                                <Mail class="size-4 text-amber-400 shrink-0" />
                                <span>{{ school.email }}</span>
                            </li>
                            <li v-if="school?.office_hours" class="flex items-center gap-2">
                                <Clock class="size-4 text-amber-400 shrink-0" />
                                <span>{{ school.office_hours }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Column 4: Portal & Current Session -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-4">
                            Management Portal
                        </h3>
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3 text-xs">
                            <p class="text-slate-400 text-xs">
                                Access student reports, attendance registers, continuous assessments, and subject records.
                            </p>
                            <Link
                                v-if="!user"
                                href="/login"
                                class="w-full inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-amber-400 font-semibold py-2 px-3 rounded-lg border border-slate-700 transition-colors"
                            >
                                <User class="size-4" />
                                Student / Staff Login
                            </Link>
                            <Link
                                v-else
                                href="/dashboard"
                                class="w-full inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-white font-semibold py-2 px-3 rounded-lg border border-slate-700 transition-colors"
                            >
                                <User class="size-4 text-amber-400" />
                                Return to Dashboard
                            </Link>

                            <div v-if="school?.principal_name" class="pt-2 border-t border-slate-800 text-[11px] text-slate-500">
                                Head of School: <span class="text-slate-300 font-medium">{{ school.principal_name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-900 pt-6 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-3">
                    <p>© {{ new Date().getFullYear() }} {{ school?.school_name || 'Advance School Management System' }}. All rights reserved.</p>
                    <p class="font-mono text-[11px]">Advance SMS Institutional Edition</p>
                </div>
            </div>
        </footer>
    </div>
</template>
