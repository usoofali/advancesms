<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    Filter,
    GraduationCap,
    HeartHandshake,
    IdCard,
    Mail,
    Phone,
    Printer,
    RefreshCw,
    Search,
    Sparkles,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PrintHeader from '@/components/PrintHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePrintOrientation } from '@/composables/usePrintOrientation';
import { index as academicPromotions } from '@/routes/academic/promotions';
import { index as peopleAlumniIndex } from '@/routes/people/alumni';
import { show as showStudent } from '@/routes/people/students';
import type { AcademicSession, SchoolClass } from '@/types/academic';
import type { AlumniStats, Alumnus } from '@/types/people';

interface Props {
    alumni: Alumnus[];
    sessions: AcademicSession[];
    classes: SchoolClass[];
    stats: AlumniStats;
    filters: {
        session_id: string;
        class_id: string;
        gender: string;
        search: string;
    };
}

const props = defineProps<Props>();

// Setup print orientation composable (landscape recommended for broad roster)
usePrintOrientation('landscape');

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Alumni Directory',
                href: peopleAlumniIndex().url,
            },
        ],
    },
});

// Client-side Reactive Filters
const searchQuery = ref(props.filters.search || '');
const selectedSession = ref(props.filters.session_id || 'all');
const selectedClass = ref(props.filters.class_id || 'all');
const selectedGender = ref(props.filters.gender || 'all');

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(15);

// Reset to page 1 whenever search, filters, or page size change
watch([searchQuery, selectedSession, selectedClass, selectedGender, itemsPerPage], () => {
    currentPage.value = 1;
});

const filteredAlumni = computed(() => {
    return props.alumni.filter((item) => {
        // Search filter
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase().trim();
            const admMatch = item.admission_number?.toLowerCase().includes(query);
            const nameMatch = item.user?.name?.toLowerCase().includes(query);
            const emailMatch = item.user?.email?.toLowerCase().includes(query);
            const phoneMatch = item.user?.phone?.toLowerCase().includes(query);
            const originMatch = item.state_of_origin?.toLowerCase().includes(query);

            if (!admMatch && !nameMatch && !emailMatch && !phoneMatch && !originMatch) {
                return false;
            }
        }

        // Graduation Session filter
        if (selectedSession.value !== 'all') {
            const gradSessionId =
                item.graduating_enrollment?.academic_session_id ??
                item.current_enrollment?.academic_session_id;
            if (String(gradSessionId) !== String(selectedSession.value)) {
                return false;
            }
        }

        // Graduating Class filter
        if (selectedClass.value !== 'all') {
            const gradClassId =
                item.graduating_enrollment?.class_id ??
                item.current_enrollment?.class_id;
            if (String(gradClassId) !== String(selectedClass.value)) {
                return false;
            }
        }

        // Gender filter
        if (selectedGender.value !== 'all') {
            if (item.user?.gender !== selectedGender.value) {
                return false;
            }
        }

        return true;
    });
});

const totalPages = computed(() => {
    return Math.ceil(filteredAlumni.value.length / itemsPerPage.value) || 1;
});

const paginatedAlumni = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredAlumni.value.slice(start, start + itemsPerPage.value);
});

const paginationStart = computed(() => {
    if (filteredAlumni.value.length === 0) return 0;
    return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const paginationEnd = computed(() => {
    return Math.min(currentPage.value * itemsPerPage.value, filteredAlumni.value.length);
});

const displayedPageNumbers = computed(() => {
    const pages: number[] = [];
    const maxButtons = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxButtons / 2));
    let end = Math.min(totalPages.value, start + maxButtons - 1);

    if (end - start + 1 < maxButtons) {
        start = Math.max(1, end - maxButtons + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value !== '' ||
        selectedSession.value !== 'all' ||
        selectedClass.value !== 'all' ||
        selectedGender.value !== 'all'
    );
});

const resetFilters = () => {
    searchQuery.value = '';
    selectedSession.value = 'all';
    selectedClass.value = 'all';
    selectedGender.value = 'all';
    currentPage.value = 1;
};

const triggerPrint = () => {
    window.print();
};

const getInitials = (name?: string | null): string => {
    if (!name) return 'A';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
};

const exportCsvUrl = computed(() => {
    const params = new URLSearchParams();
    params.set('export', 'csv');
    if (selectedSession.value !== 'all') params.set('session_id', selectedSession.value);
    if (selectedClass.value !== 'all') params.set('class_id', selectedClass.value);
    if (selectedGender.value !== 'all') params.set('gender', selectedGender.value);
    if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim());

    return `${peopleAlumniIndex().url}?${params.toString()}`;
});
</script>

<template>
    <Head title="Alumni & Graduated Students Directory" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8 pb-12 print:p-0 print:space-y-3">
        <!-- Print Header (shown exclusively in print mode) -->
        <PrintHeader
            title="Alumni & Graduated Students Directory"
            subtitle="Official Institutional Register of Graduated Students"
        />

        <!-- Screen Top Header & Action Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <Award class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        Alumni Directory
                    </h1>
                    <Badge variant="outline" class="bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border-purple-200">
                        {{ stats.total_alumni }} Graduated
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground mt-1">
                    Permanent register and academic archives of graduated students who completed their studies.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-xs shadow-xs"
                    @click="triggerPrint"
                >
                    <Printer class="h-3.5 w-3.5 text-muted-foreground" />
                    <span>Print Register</span>
                </Button>

                <a
                    :href="exportCsvUrl"
                    class="inline-flex items-center justify-center gap-1.5 text-xs font-medium rounded-md border border-input bg-background hover:bg-muted/50 px-3 py-1.5 h-8 shadow-xs transition-colors"
                >
                    <Download class="h-3.5 w-3.5 text-muted-foreground" />
                    <span>Export CSV</span>
                </a>

                <Link
                    :href="academicPromotions().url"
                    class="inline-flex items-center justify-center gap-1.5 text-xs font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 px-3 py-1.5 h-8 shadow-xs transition-colors"
                >
                    <TrendingUp class="h-3.5 w-3.5" />
                    <span>Promotions & Graduation</span>
                </Link>
            </div>
        </div>

        <!-- Metric Cards Grid (print:hidden) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 print:hidden">
            <!-- Total Alumni -->
            <Card class="border-border/60 shadow-xs relative overflow-hidden">
                <div class="absolute -right-4 -bottom-4 opacity-10 pointer-events-none">
                    <Award class="w-24 h-24 text-purple-600" />
                </div>
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Alumni</span>
                        <Award class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-foreground">
                        {{ stats.total_alumni }}
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-0.5">
                        Historical graduates archived
                    </p>
                </CardContent>
            </Card>

            <!-- Male Graduates -->
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Male Graduates</span>
                        <Users class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ stats.male_alumni }}
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-0.5">
                        {{ stats.total_alumni > 0 ? Math.round((stats.male_alumni / stats.total_alumni) * 100) : 0 }}% of alumni body
                    </p>
                </CardContent>
            </Card>

            <!-- Female Graduates -->
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Female Graduates</span>
                        <Users class="h-4 w-4 text-rose-600 dark:text-rose-400" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                        {{ stats.female_alumni }}
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-0.5">
                        {{ stats.total_alumni > 0 ? Math.round((stats.female_alumni / stats.total_alumni) * 100) : 0 }}% of alumni body
                    </p>
                </CardContent>
            </Card>

            <!-- Graduating Batches -->
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Graduating Batches</span>
                        <GraduationCap class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ stats.graduating_batches }}
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-0.5">
                        Academic sessions with graduates
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter & Search Toolbar (print:hidden) -->
        <Card class="border-border/60 shadow-xs print:hidden">
            <CardContent class="p-4 sm:p-5">
                <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center justify-between">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search by name, admission #, phone, email, or state..."
                            class="pl-9 h-10 w-full"
                        />
                    </div>

                    <!-- Filter Dropdowns -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Graduation Session Filter -->
                        <div class="flex items-center gap-1.5">
                            <select
                                v-model="selectedSession"
                                class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="all">All Graduation Batches</option>
                                <option
                                    v-for="s in sessions"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }} Batch {{ s.is_current ? '(Current)' : '' }}
                                </option>
                            </select>
                        </div>

                        <!-- Class Filter -->
                        <div class="flex items-center gap-1.5">
                            <select
                                v-model="selectedClass"
                                class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="all">All Graduating Classes</option>
                                <option
                                    v-for="c in classes"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Gender Filter -->
                        <div class="flex items-center gap-1.5">
                            <select
                                v-model="selectedGender"
                                class="h-10 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="all">All Genders</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <!-- Reset Filters -->
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="h-10 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                            @click="resetFilters"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                            <span>Reset</span>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Alumni Catalog Table (Screen View) -->
        <Card class="border-border/60 shadow-xs print:hidden">
            <CardHeader class="px-6 py-4 border-b border-border/40">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-semibold">Graduated Alumni Roster</CardTitle>
                        <CardDescription>
                            Showing {{ paginationStart }} - {{ paginationEnd }} of {{ filteredAlumni.length }} alumni records
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/40 border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3.5">Admission #</th>
                            <th class="px-6 py-3.5">Alumnus Name</th>
                            <th class="px-6 py-3.5">Graduating Class</th>
                            <th class="px-6 py-3.5">Graduation Batch</th>
                            <th class="px-6 py-3.5">Gender & Origin</th>
                            <th class="px-6 py-3.5">Guardian Contact</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr
                            v-for="alumnus in paginatedAlumni"
                            :key="alumnus.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <!-- Admission # -->
                            <td class="px-6 py-4">
                                <Link
                                    :href="showStudent(alumnus.id).url"
                                    class="font-mono font-semibold text-xs text-primary hover:underline inline-flex items-center gap-1"
                                    title="View Full Academic Profile"
                                >
                                    <IdCard class="h-3.5 w-3.5 text-muted-foreground" />
                                    <span>{{ alumnus.admission_number }}</span>
                                </Link>
                            </td>

                            <!-- Alumnus Name & Avatar -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-purple-500/10 text-purple-700 dark:text-purple-300 flex items-center justify-center font-bold text-xs shrink-0 border border-purple-200/50">
                                        <img
                                            v-if="alumnus.user?.avatar"
                                            :src="alumnus.user.avatar"
                                            :alt="alumnus.user.name"
                                            class="h-full w-full rounded-full object-cover"
                                        />
                                        <span v-else>{{ getInitials(alumnus.user?.name) }}</span>
                                    </div>
                                    <div>
                                        <Link
                                            :href="showStudent(alumnus.id).url"
                                            class="font-medium text-foreground hover:text-primary hover:underline transition-colors block"
                                            title="View Full Academic Profile"
                                        >
                                            {{ alumnus.user?.name }}
                                        </Link>
                                        <div v-if="alumnus.user?.email || alumnus.user?.phone" class="text-xs text-muted-foreground flex items-center gap-2">
                                            <span v-if="alumnus.user?.email">{{ alumnus.user.email }}</span>
                                            <span v-if="alumnus.user?.email && alumnus.user?.phone">•</span>
                                            <span v-if="alumnus.user?.phone">{{ alumnus.user.phone }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Graduating Class & Section -->
                            <td class="px-6 py-4">
                                <div v-if="alumnus.graduating_enrollment?.school_class || alumnus.current_enrollment?.school_class" class="flex flex-col gap-0.5">
                                    <span class="font-medium text-foreground">
                                        {{ alumnus.graduating_enrollment?.school_class?.name ?? alumnus.current_enrollment?.school_class?.name }}
                                    </span>
                                    <div class="flex items-center gap-1.5">
                                        <Badge variant="secondary" class="text-[10px] font-semibold">
                                            Section {{ (alumnus.graduating_enrollment?.section ?? alumnus.current_enrollment?.section)?.name ?? 'General' }}
                                        </Badge>
                                    </div>
                                </div>
                                <span v-else class="text-xs text-muted-foreground italic">—</span>
                            </td>

                            <!-- Graduation Batch / Session -->
                            <td class="px-6 py-4">
                                <div v-if="alumnus.graduating_enrollment?.academic_session || alumnus.current_enrollment?.academic_session" class="flex items-center gap-1.5">
                                    <Badge variant="outline" class="bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-200/80 font-semibold text-xs gap-1">
                                        <GraduationCap class="h-3 w-3" />
                                        <span>{{ (alumnus.graduating_enrollment?.academic_session ?? alumnus.current_enrollment?.academic_session)?.name }}</span>
                                    </Badge>
                                </div>
                                <span v-else class="text-xs text-muted-foreground italic">—</span>
                            </td>

                            <!-- Gender & Origin -->
                            <td class="px-6 py-4">
                                <div class="space-y-0.5">
                                    <Badge
                                        variant="outline"
                                        class="capitalize text-xs font-normal"
                                        :class="{
                                            'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200': alumnus.user?.gender === 'male',
                                            'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-200': alumnus.user?.gender === 'female',
                                        }"
                                    >
                                        {{ alumnus.user?.gender }}
                                    </Badge>
                                    <div v-if="alumnus.state_of_origin" class="text-[11px] text-muted-foreground truncate">
                                        {{ alumnus.state_of_origin }}<template v-if="alumnus.lga"> ({{ alumnus.lga }})</template>
                                    </div>
                                </div>
                            </td>

                            <!-- Guardian Contact -->
                            <td class="px-6 py-4">
                                <div v-if="alumnus.guardian" class="space-y-0.5">
                                    <div class="text-xs font-medium text-foreground">
                                        {{ alumnus.guardian.name }}
                                    </div>
                                    <div class="text-[11px] text-muted-foreground flex items-center gap-1">
                                        <span>{{ alumnus.guardian.relationship }}</span>
                                        <span>•</span>
                                        <span>{{ alumnus.guardian.phone }}</span>
                                    </div>
                                </div>
                                <span v-else class="text-xs text-muted-foreground italic">
                                    None linked
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <Link
                                        :href="showStudent(alumnus.id).url"
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1.5 rounded-md bg-muted/60 hover:bg-muted text-foreground transition-colors"
                                        title="View Academic Records, Marks, and Attendance Dossier"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                        <span>Dossier</span>
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="filteredAlumni.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="max-w-md mx-auto space-y-3">
                                    <div class="h-12 w-12 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400 mx-auto flex items-center justify-center">
                                        <Award class="h-6 w-6" />
                                    </div>
                                    <div class="space-y-1">
                                        <h3 class="font-semibold text-foreground text-base">No alumni records found</h3>
                                        <p class="text-xs text-muted-foreground">
                                            <template v-if="hasActiveFilters">
                                                No graduated students match your search or filter criteria. Try adjusting your filters.
                                            </template>
                                            <template v-else>
                                                No students have been graduated to alumni status yet. You can graduate students during promotion cycles.
                                            </template>
                                        </p>
                                    </div>
                                    <div v-if="hasActiveFilters">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="gap-1.5 text-xs"
                                            @click="resetFilters"
                                        >
                                            <RefreshCw class="h-3.5 w-3.5" />
                                            <span>Clear All Filters</span>
                                        </Button>
                                    </div>
                                    <div v-else class="pt-2">
                                        <Link
                                            :href="academicPromotions().url"
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                                        >
                                            <span>Go to Promotions to graduate final-year classes</span>
                                            <ArrowRight class="h-3.5 w-3.5" />
                                        </Link>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar (print:hidden) -->
            <div
                v-if="filteredAlumni.length > 0"
                class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-border/40"
            >
                <div class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                    <div>
                        Showing <span class="font-medium text-foreground">{{ paginationStart }}</span> to
                        <span class="font-medium text-foreground">{{ paginationEnd }}</span> of
                        <span class="font-medium text-foreground">{{ filteredAlumni.length }}</span> alumni records
                    </div>

                    <div class="flex items-center gap-1.5">
                        <span class="text-muted-foreground/80">Show</span>
                        <select
                            v-model.number="itemsPerPage"
                            class="h-8 rounded-md border border-input bg-background px-2 py-0 text-xs shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                        <span class="text-muted-foreground/80">per page</span>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="currentPage === 1"
                        @click="currentPage = Math.max(1, currentPage - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        <span class="sr-only">Previous</span>
                    </Button>

                    <div class="flex items-center gap-1 px-1">
                        <Button
                            v-for="pg in displayedPageNumbers"
                            :key="pg"
                            variant="outline"
                            size="sm"
                            class="h-8 min-w-[2rem] px-2 text-xs"
                            :class="{
                                'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground':
                                    currentPage === pg,
                            }"
                            @click="currentPage = pg"
                        >
                            {{ pg }}
                        </Button>
                    </div>

                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage = Math.min(totalPages, currentPage + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                        <span class="sr-only">Next</span>
                    </Button>
                </div>
            </div>
        </Card>

        <!-- Dedicated Print Table (visible only during print) -->
        <div class="hidden print:block w-full">
            <div class="mb-3 text-xs text-neutral-600">
                <span>Total Graduates Listed: <strong>{{ filteredAlumni.length }}</strong></span>
                <span class="mx-2">•</span>
                <span>Date: <strong>{{ new Date().toLocaleDateString() }}</strong></span>
            </div>

            <table class="w-full text-left text-xs border-collapse border border-neutral-300 print:text-black">
                <thead>
                    <tr class="bg-neutral-100 border-b border-neutral-300 font-bold uppercase text-[10px]">
                        <th class="py-2 px-2 border-r border-neutral-300 w-8 text-center">S/N</th>
                        <th class="py-2 px-2 border-r border-neutral-300">Admission #</th>
                        <th class="py-2 px-2 border-r border-neutral-300">Full Name</th>
                        <th class="py-2 px-2 border-r border-neutral-300">Gender</th>
                        <th class="py-2 px-2 border-r border-neutral-300">Graduating Class</th>
                        <th class="py-2 px-2 border-r border-neutral-300">Graduation Batch</th>
                        <th class="py-2 px-2 border-r border-neutral-300">Guardian Name & Contact</th>
                        <th class="py-2 px-2">State of Origin</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(alumnus, idx) in filteredAlumni"
                        :key="alumnus.id"
                        class="border-b border-neutral-200 hover:bg-neutral-50"
                    >
                        <td class="py-1.5 px-2 border-r border-neutral-200 text-center font-mono text-[11px]">{{ idx + 1 }}</td>
                        <td class="py-1.5 px-2 border-r border-neutral-200 font-mono text-[11px]">{{ alumnus.admission_number }}</td>
                        <td class="py-1.5 px-2 border-r border-neutral-200 font-medium">{{ alumnus.user?.name }}</td>
                        <td class="py-1.5 px-2 border-r border-neutral-200 capitalize text-center">{{ alumnus.user?.gender }}</td>
                        <td class="py-1.5 px-2 border-r border-neutral-200">
                            {{ (alumnus.graduating_enrollment?.school_class ?? alumnus.current_enrollment?.school_class)?.name ?? '—' }}
                            <template v-if="(alumnus.graduating_enrollment?.section ?? alumnus.current_enrollment?.section)?.name">
                                ({{ (alumnus.graduating_enrollment?.section ?? alumnus.current_enrollment?.section)?.name }})
                            </template>
                        </td>
                        <td class="py-1.5 px-2 border-r border-neutral-200 text-center">
                            {{ (alumnus.graduating_enrollment?.academic_session ?? alumnus.current_enrollment?.academic_session)?.name ?? '—' }}
                        </td>
                        <td class="py-1.5 px-2 border-r border-neutral-200">
                            <span v-if="alumnus.guardian">{{ alumnus.guardian.name }} ({{ alumnus.guardian.phone }})</span>
                            <span v-else class="text-neutral-400 italic">—</span>
                        </td>
                        <td class="py-1.5 px-2">
                            {{ alumnus.state_of_origin ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
