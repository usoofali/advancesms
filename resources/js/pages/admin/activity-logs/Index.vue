<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowDownRight,
    ArrowRight,
    Calendar,
    CheckCircle2,
    Clock,
    Database,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Filter,
    Globe,
    History,
    Info,
    KeyRound,
    Laptop,
    Layers,
    PenTool,
    PlusCircle,
    RotateCcw,
    Search,
    Shield,
    ShieldAlert,
    Trash2,
    TrendingUp,
    User as UserIcon,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    clear as clearAllActivityLogs,
    destroy as destroyActivityLog,
    destroyBatch as destroyBatchActivityLogs,
    index as adminActivityLogs,
} from '@/routes/admin/activity-logs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface LogCauser {
    id: number;
    name: string;
    email: string;
    role?: string;
}

interface ActivityLogItem {
    id: number;
    log_name: string;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    causer_type: string | null;
    causer_id: number | null;
    event: string | null;
    properties: {
        attributes?: Record<string, any>;
        old?: Record<string, any>;
        context?: Record<string, any>;
    } | null;
    ip_address: string | null;
    user_agent: string | null;
    url: string | null;
    method: string | null;
    created_at: string;
    causer?: LogCauser | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedLogs {
    data: ActivityLogItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Props {
    logs: PaginatedLogs;
    stats: {
        total_activities: number;
        today_activities: number;
        active_actors_today: number;
        actions_created: number;
        actions_updated: number;
        actions_deleted: number;
        actions_promoted: number;
        actions_security: number;
    };
    filters: {
        search: string;
        log_name: string;
        event: string;
        causer_id: string;
        date_range: string;
        date_from: string;
        date_to: string;
    };
    modules: string[];
    events: string[];
    causers: LogCauser[];
    can_delete?: boolean;
}

const props = defineProps<Props>();

const page = usePage();
const canDelete = computed(() => {
    if (props.can_delete) return true;
    const perms = (page.props.auth?.permissions as string[]) || [];
    const roles = (page.props.auth?.roles as string[]) || [];
    return roles.includes('superadmin') || perms.includes('*') || perms.includes('activity_logs.delete');
});

// Multi-Selection State
const selectedIds = ref<number[]>([]);
const isAllSelected = computed(() => {
    if (props.logs.data.length === 0) return false;
    return props.logs.data.every((log) => selectedIds.value.includes(log.id));
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = Array.from(new Set([...selectedIds.value, ...props.logs.data.map((l) => l.id)]));
    } else {
        const currentPageIds = props.logs.data.map((l) => l.id);
        selectedIds.value = selectedIds.value.filter((id) => !currentPageIds.includes(id));
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);
        }
    } else {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
    }
};

watch(() => props.logs.data, () => {
    selectedIds.value = [];
});

// Single Delete Action
const logToDelete = ref<ActivityLogItem | null>(null);
const isSingleDeleteModalOpen = ref(false);
const isDeletingSingle = ref(false);

const confirmDeleteSingle = (log: ActivityLogItem) => {
    logToDelete.value = log;
    isSingleDeleteModalOpen.value = true;
};

const executeDeleteSingle = () => {
    if (!logToDelete.value) return;
    isDeletingSingle.value = true;
    router.delete(destroyActivityLog.url(logToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Activity log record deleted successfully.');
            isSingleDeleteModalOpen.value = false;
            if (inspectedLog.value?.id === logToDelete.value?.id) {
                isInspectModalOpen.value = false;
            }
            selectedIds.value = selectedIds.value.filter((id) => id !== logToDelete.value?.id);
            logToDelete.value = null;
        },
        onError: () => {
            toast.error('Failed to delete activity log record.');
        },
        onFinish: () => {
            isDeletingSingle.value = false;
        },
    });
};

// Batch Delete Action
const isBatchDeleteModalOpen = ref(false);
const isDeletingBatch = ref(false);

const executeDeleteBatch = () => {
    if (selectedIds.value.length === 0) return;
    isDeletingBatch.value = true;
    router.delete(destroyBatchActivityLogs.url(), {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`${selectedIds.value.length} activity log records deleted.`);
            isBatchDeleteModalOpen.value = false;
            selectedIds.value = [];
        },
        onError: () => {
            toast.error('Failed to delete selected activity logs.');
        },
        onFinish: () => {
            isDeletingBatch.value = false;
        },
    });
};

// Purge / Clear Logs Action
const isPurgeModalOpen = ref(false);
const isPurging = ref(false);
const purgeScope = ref<'older_than_30' | 'older_than_60' | 'older_than_90' | 'all'>('older_than_30');

const executePurge = () => {
    isPurging.value = true;
    router.post(clearAllActivityLogs.url(), {
        scope: purgeScope.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Activity logs purged successfully.');
            isPurgeModalOpen.value = false;
            selectedIds.value = [];
        },
        onError: () => {
            toast.error('Failed to purge activity logs.');
        },
        onFinish: () => {
            isPurging.value = false;
        },
    });
};

// Reactive filter state
const searchQuery = ref(props.filters.search || '');
const selectedModule = ref(props.filters.log_name || 'all');
const selectedEvent = ref(props.filters.event || 'all');
const selectedCauser = ref(props.filters.causer_id || 'all');
const selectedDateRange = ref(props.filters.date_range || 'all');

// Active Inspection Modal State
const inspectedLog = ref<ActivityLogItem | null>(null);
const isInspectModalOpen = ref(false);

const openInspectModal = (log: ActivityLogItem) => {
    inspectedLog.value = log;
    isInspectModalOpen.value = true;
};

// Apply filters to backend
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        adminActivityLogs.url(),
        {
            search: searchQuery.value || undefined,
            log_name: selectedModule.value !== 'all' ? selectedModule.value : undefined,
            event: selectedEvent.value !== 'all' ? selectedEvent.value : undefined,
            causer_id: selectedCauser.value !== 'all' ? selectedCauser.value : undefined,
            date_range: selectedDateRange.value !== 'all' ? selectedDateRange.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const handleSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedModule.value = 'all';
    selectedEvent.value = 'all';
    selectedCauser.value = 'all';
    selectedDateRange.value = 'all';
    applyFilters();
};

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value !== '' ||
        selectedModule.value !== 'all' ||
        selectedEvent.value !== 'all' ||
        selectedCauser.value !== 'all' ||
        selectedDateRange.value !== 'all'
    );
});

const exportCsvUrl = computed(() => {
    const params = new URLSearchParams();
    if (searchQuery.value) params.set('search', searchQuery.value);
    if (selectedModule.value !== 'all') params.set('log_name', selectedModule.value);
    if (selectedEvent.value !== 'all') params.set('event', selectedEvent.value);
    if (selectedCauser.value !== 'all') params.set('causer_id', selectedCauser.value);
    if (selectedDateRange.value !== 'all') params.set('date_range', selectedDateRange.value);
    params.set('export', '1');
    return `${adminActivityLogs.url()}?${params.toString()}`;
});

// Format helpers
const formatDate = (dateStr: string): string => {
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
};

const timeAgo = (dateStr: string): string => {
    try {
        const diff = Date.now() - new Date(dateStr).getTime();
        const mins = Math.floor(diff / 60000);
        if (mins < 1) return 'Just now';
        if (mins < 60) return `${mins}m ago`;
        const hours = Math.floor(mins / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        return `${days}d ago`;
    } catch {
        return '';
    }
};

// Event styling helper
const getEventBadgeClass = (event: string | null) => {
    switch (event) {
        case 'created':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30';
        case 'updated':
            return 'bg-sky-500/15 text-sky-700 dark:text-sky-400 border-sky-500/30';
        case 'deleted':
            return 'bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-500/30';
        case 'promoted':
            return 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border-indigo-500/30';
        case 'login':
        case 'logout':
            return 'bg-purple-500/15 text-purple-700 dark:text-purple-400 border-purple-500/30';
        default:
            return 'bg-secondary text-secondary-foreground border-border';
    }
};

const getModuleBadgeClass = (moduleName: string) => {
    switch (moduleName) {
        case 'academic':
            return 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        case 'people':
            return 'bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-300 border-teal-200 dark:border-teal-800';
        case 'auth':
            return 'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300 border-violet-200 dark:border-violet-800';
        case 'settings':
            return 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        case 'assessment':
            return 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
};

// Changed keys for inspect diff view
const inspectedDiffKeys = computed(() => {
    if (!inspectedLog.value?.properties) return [];
    const oldVals = inspectedLog.value.properties.old || {};
    const newVals = inspectedLog.value.properties.attributes || {};
    const keys = new Set([...Object.keys(oldVals), ...Object.keys(newVals)]);
    return Array.from(keys);
});
</script>

<template>
    <Head title="System Audit & Activity Logs" />

    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Page Header & Action Bar -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Activity Logs
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Log of administrative actions, staff activities, and system modifications.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-xs shadow-xs"
                    @click="applyFilters"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    <span>Refresh</span>
                </Button>

                <Button
                    v-if="canDelete"
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-xs text-destructive hover:text-destructive hover:bg-destructive/10 shadow-xs"
                    @click="isPurgeModalOpen = true"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                    <span>Purge Logs</span>
                </Button>

                <a
                    :href="exportCsvUrl"
                    download
                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md text-xs font-medium bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-xs"
                >
                    <Download class="h-3.5 w-3.5" />
                    <span>Export CSV</span>
                </a>
            </div>
        </div>

        <!-- Floating Multi-Selection Action Bar -->
        <div
            v-if="canDelete && selectedIds.length > 0"
            class="flex items-center justify-between p-3 rounded-lg border border-destructive/30 bg-destructive/5 text-destructive animate-in fade-in slide-in-from-top-2 duration-200"
        >
            <div class="flex items-center gap-2">
                <Badge variant="destructive" class="text-xs font-mono">
                    {{ selectedIds.length }} Selected
                </Badge>
                <span class="text-xs font-medium text-foreground">
                    Activity log records selected
                </span>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs"
                    @click="selectedIds = []"
                >
                    Deselect All
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    class="h-8 gap-1.5 text-xs shadow-xs"
                    @click="isBatchDeleteModalOpen = true"
                >
                    <Trash2 class="size-3.5" />
                    <span>Delete Selected ({{ selectedIds.length }})</span>
                </Button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Total Events Logged</CardDescription>
                    <CardTitle class="text-2xl font-bold text-foreground">
                        {{ stats.total_activities.toLocaleString() }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground flex items-center gap-1">
                        <Database class="size-3 text-primary" />
                        <span>Lifetime historical entries</span>
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Activities Today</CardDescription>
                    <CardTitle class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ stats.today_activities.toLocaleString() }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground flex items-center gap-1">
                        <Clock class="size-3 text-emerald-500" />
                        <span>{{ stats.active_actors_today }} active user(s) today</span>
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Data Changes</CardDescription>
                    <CardTitle class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ stats.actions_created + stats.actions_updated }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">
                        {{ stats.actions_created }} creates · {{ stats.actions_updated }} updates
                    </p>
                </CardContent>
            </Card>

            <Card class="border-border/60 shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <CardDescription class="text-xs font-medium">Progressions & Auth</CardDescription>
                    <CardTitle class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ stats.actions_promoted + stats.actions_security }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0">
                    <p class="text-xs text-muted-foreground">
                        {{ stats.actions_promoted }} promotions · {{ stats.actions_security }} logins
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter & Search Panel -->
        <Card class="border-border/60 shadow-xs">
            <CardContent class="p-4 space-y-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search by action description, actor name, IP address, or entity..."
                            class="pl-9 h-9 w-full text-xs"
                            @input="handleSearchInput"
                        />
                    </div>

                    <!-- Dropdown Filters -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Module Filter -->
                        <select
                            v-model="selectedModule"
                            @change="applyFilters"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs font-medium shadow-xs"
                        >
                            <option value="all">All Modules</option>
                            <option v-for="m in modules" :key="m" :value="m">
                                Module: {{ m.toUpperCase() }}
                            </option>
                        </select>

                        <!-- Event Type Filter -->
                        <select
                            v-model="selectedEvent"
                            @change="applyFilters"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs font-medium shadow-xs"
                        >
                            <option value="all">All Events</option>
                            <option v-for="ev in events" :key="ev" :value="ev">
                                Event: {{ ev }}
                            </option>
                        </select>

                        <!-- User / Causer Filter -->
                        <select
                            v-model="selectedCauser"
                            @change="applyFilters"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs font-medium shadow-xs max-w-44"
                        >
                            <option value="all">All Actors</option>
                            <option v-for="u in causers" :key="u.id" :value="String(u.id)">
                                {{ u.name }}
                            </option>
                        </select>

                        <!-- Date Preset Filter -->
                        <select
                            v-model="selectedDateRange"
                            @change="applyFilters"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs font-medium shadow-xs"
                        >
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">Past 7 Days</option>
                            <option value="month">Past 30 Days</option>
                        </select>

                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="h-9 text-xs text-muted-foreground hover:text-foreground"
                            @click="resetFilters"
                        >
                            <X class="h-3.5 w-3.5 mr-1" />
                            <span>Reset</span>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ACTIVITY LOG DATA TABLE -->
        <Card class="border-border/60 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/40 border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        <tr>
                            <th v-if="canDelete" class="w-10 px-4 py-3.5 text-center">
                                <Checkbox
                                    :checked="isAllSelected"
                                    @update:checked="toggleSelectAll"
                                    aria-label="Select all rows"
                                />
                            </th>
                            <th class="px-5 py-3.5">Timestamp</th>
                            <th class="px-5 py-3.5">Actor (Who)</th>
                            <th class="px-5 py-3.5">Module</th>
                            <th class="px-5 py-3.5">Action</th>
                            <th class="px-5 py-3.5">Description (What Happened)</th>
                            <th class="px-5 py-3.5">Source / IP</th>
                            <th class="px-5 py-3.5 text-right">Inspect</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="hover:bg-muted/30 transition-colors group cursor-pointer"
                            @click="openInspectModal(log)"
                        >
                            <!-- Row Checkbox (when delete allowed) -->
                            <td v-if="canDelete" class="px-4 py-3.5 text-center" @click.stop>
                                <Checkbox
                                    :checked="selectedIds.includes(log.id)"
                                    @update:checked="(val) => toggleSelectRow(log.id, !!val)"
                                    :aria-label="`Select log ${log.id}`"
                                />
                            </td>

                            <!-- Timestamp -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="font-medium text-xs text-foreground">
                                    {{ formatDate(log.created_at) }}
                                </div>
                                <div class="text-[11px] text-muted-foreground flex items-center gap-1">
                                    <Clock class="size-3" />
                                    <span>{{ timeAgo(log.created_at) }}</span>
                                </div>
                            </td>

                            <!-- Actor / Causer -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div v-if="log.causer" class="flex items-center gap-2">
                                    <div class="size-7 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ log.causer.name[0]?.toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-xs text-foreground">
                                            {{ log.causer.name }}
                                        </div>
                                        <div class="text-[11px] text-muted-foreground">
                                            {{ log.causer.email }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex items-center gap-1.5 text-xs text-muted-foreground italic">
                                    <Shield class="size-3.5 text-muted-foreground/60" />
                                    <span>System / CLI</span>
                                </div>
                            </td>

                            <!-- Module / Log Name -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <Badge
                                    variant="outline"
                                    :class="['text-[11px] font-semibold uppercase tracking-wider', getModuleBadgeClass(log.log_name)]"
                                >
                                    {{ log.log_name }}
                                </Badge>
                            </td>

                            <!-- Action Event -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <Badge
                                    variant="outline"
                                    :class="['capitalize text-xs font-semibold px-2 py-0.5', getEventBadgeClass(log.event)]"
                                >
                                    {{ log.event ?? 'event' }}
                                </Badge>
                            </td>

                            <!-- Description & Change Indicator -->
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-xs text-foreground">
                                    {{ log.description }}
                                </div>
                                <div v-if="log.properties" class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span
                                        v-if="log.properties.old && log.properties.attributes"
                                        class="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-800 dark:text-amber-300 border border-amber-500/20"
                                    >
                                        <PenTool class="size-2.5" />
                                        {{ Object.keys(log.properties.attributes).length }} modified field(s)
                                    </span>
                                    <span
                                        v-else-if="log.properties.context"
                                        class="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-primary/10 text-primary border border-primary/20"
                                    >
                                        <Layers class="size-2.5" />
                                        Context recorded
                                    </span>
                                </div>
                            </td>

                            <!-- IP & Client -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="font-mono text-[11px] text-muted-foreground">
                                    {{ log.ip_address ?? '—' }}
                                </div>
                                <div v-if="log.method" class="text-[10px] font-semibold text-muted-foreground/75 uppercase">
                                    {{ log.method }}
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-3.5 text-right whitespace-nowrap" @click.stop>
                                <div class="inline-flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 gap-1 text-xs text-muted-foreground hover:text-foreground"
                                        @click="openInspectModal(log)"
                                    >
                                        <Eye class="size-3.5" />
                                        <span>Details</span>
                                    </Button>
                                    <Button
                                        v-if="canDelete"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 px-2 text-xs text-destructive hover:text-destructive hover:bg-destructive/10"
                                        title="Delete log record"
                                        @click="confirmDeleteSingle(log)"
                                    >
                                        <Trash2 class="size-3.5" />
                                        <span class="sr-only">Delete</span>
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="logs.data.length === 0">
                            <td :colspan="canDelete ? 8 : 7" class="px-6 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <History class="size-8 text-muted-foreground/50" />
                                    <p class="font-medium text-sm">No activity records match your current filter selection.</p>
                                    <Button
                                        v-if="hasActiveFilters"
                                        variant="outline"
                                        size="sm"
                                        class="text-xs mt-2"
                                        @click="resetFilters"
                                    >
                                        Clear Filter Query
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div
                v-if="logs.total > 0"
                class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-border/40"
            >
                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-medium text-foreground">{{ logs.from ?? 0 }}</span> to
                    <span class="font-medium text-foreground">{{ logs.to ?? 0 }}</span> of
                    <span class="font-medium text-foreground">{{ logs.total }}</span> activities recorded
                </div>

                <div class="flex items-center gap-1">
                    <Button
                        v-for="(link, idx) in logs.links"
                        :key="idx"
                        variant="outline"
                        size="sm"
                        class="h-8 min-w-8 text-xs"
                        :class="{
                            'bg-primary text-primary-foreground hover:bg-primary/90': link.active,
                            'opacity-40 pointer-events-none': !link.url,
                        }"
                        @click="link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })"
                    >
                        <span v-html="link.label" />
                    </Button>
                </div>
            </div>
        </Card>

        <!-- ================================================================= -->
        <!-- ACTIVITY INSPECTION DRAWER / MODAL -->
        <!-- ================================================================= -->
        <Dialog :open="isInspectModalOpen" @update:open="(val) => isInspectModalOpen = val">
            <DialogContent class="sm:max-w-3xl max-h-[90vh] flex flex-col p-0 overflow-hidden gap-0">
                <DialogHeader class="p-5 sm:p-6 pb-4 border-b border-border shrink-0 pr-12">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
                            <Activity class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-bold">Activity Log Details #{{ inspectedLog?.id }}</DialogTitle>
                            <DialogDescription>
                                Full forensic inspection of this operational event and recorded changes.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div v-if="inspectedLog" class="flex-1 overflow-y-auto min-h-0 p-5 sm:p-6 space-y-4 text-xs">
                    <!-- Event Overview Header Card -->
                    <div class="rounded-lg border border-border bg-muted/30 p-3.5 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-foreground text-sm">
                                {{ inspectedLog.description }}
                            </span>
                            <Badge
                                variant="outline"
                                :class="['capitalize font-semibold text-xs', getEventBadgeClass(inspectedLog.event)]"
                            >
                                {{ inspectedLog.event ?? 'Event' }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-muted-foreground text-[11px] pt-1">
                            <span>Channel: <strong class="text-foreground uppercase">{{ inspectedLog.log_name }}</strong></span>
                            <span>•</span>
                            <span>Timestamp: <strong class="text-foreground">{{ formatDate(inspectedLog.created_at) }}</strong></span>
                            <span v-if="inspectedLog.subject_type">•</span>
                            <span v-if="inspectedLog.subject_type">
                                Target: <strong class="text-foreground">{{ inspectedLog.subject_type }} #{{ inspectedLog.subject_id }}</strong>
                            </span>
                        </div>
                    </div>

                    <!-- Actor & Network Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Actor Info -->
                        <div class="rounded-md border border-border bg-background p-3 space-y-1.5 shadow-2xs">
                            <div class="font-semibold text-muted-foreground uppercase text-[10px] tracking-wider flex items-center gap-1">
                                <UserIcon class="size-3" />
                                <span>Causer / Actor</span>
                            </div>
                            <div v-if="inspectedLog.causer" class="space-y-0.5">
                                <p class="font-bold text-foreground text-sm">{{ inspectedLog.causer.name }}</p>
                                <p class="text-muted-foreground">{{ inspectedLog.causer.email }}</p>
                                <Badge variant="secondary" class="text-[10px] capitalize font-medium mt-1">
                                    {{ inspectedLog.causer.role ?? 'User' }}
                                </Badge>
                            </div>
                            <div v-else class="text-muted-foreground italic py-1">
                                System / Automated Process
                            </div>
                        </div>

                        <!-- Network Context -->
                        <div class="rounded-md border border-border bg-background p-3 space-y-1.5 shadow-2xs">
                            <div class="font-semibold text-muted-foreground uppercase text-[10px] tracking-wider flex items-center gap-1">
                                <Globe class="size-3" />
                                <span>Origin & Client</span>
                            </div>
                            <p class="text-foreground font-mono">IP: {{ inspectedLog.ip_address ?? 'Not recorded' }}</p>
                            <p v-if="inspectedLog.method" class="text-muted-foreground font-mono text-[11px]">
                                HTTP {{ inspectedLog.method }}
                            </p>
                            <p v-if="inspectedLog.url" class="text-muted-foreground text-[11px] truncate" :title="inspectedLog.url">
                                {{ inspectedLog.url }}
                            </p>
                        </div>
                    </div>

                    <!-- USER AGENT / DEVICE -->
                    <div v-if="inspectedLog.user_agent" class="rounded-md border border-border/60 bg-muted/20 p-2.5 text-[11px]">
                        <span class="text-muted-foreground font-semibold">User Agent: </span>
                        <span class="font-mono text-muted-foreground break-all">{{ inspectedLog.user_agent }}</span>
                    </div>

                    <!-- FIELD CHANGES / DIFF TABLE -->
                    <div v-if="inspectedLog.properties?.old || inspectedLog.properties?.attributes" class="space-y-2">
                        <div class="font-bold text-foreground text-xs flex items-center gap-1.5">
                            <PenTool class="size-3.5 text-primary" />
                            <span>Attribute Modifications & Values</span>
                        </div>

                        <div class="border border-border rounded-md overflow-hidden overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th class="px-3 py-2">Field</th>
                                        <th v-if="inspectedLog.properties?.old" class="px-3 py-2 text-rose-600 dark:text-rose-400">
                                            Previous Value
                                        </th>
                                        <th class="px-3 py-2 text-emerald-600 dark:text-emerald-400">
                                            {{ inspectedLog.properties?.old ? 'New / Modified Value' : 'Recorded Value' }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40 font-mono text-[11px]">
                                    <tr
                                        v-for="key in inspectedDiffKeys"
                                        :key="key"
                                        class="hover:bg-muted/20"
                                    >
                                        <td class="px-3 py-2 font-sans font-semibold text-foreground">
                                            {{ key }}
                                        </td>
                                        <td
                                            v-if="inspectedLog.properties?.old"
                                            class="px-3 py-2 text-rose-700 dark:text-rose-300 bg-rose-500/5"
                                        >
                                            {{ inspectedLog.properties.old[key] !== undefined ? JSON.stringify(inspectedLog.properties.old[key]) : '—' }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-emerald-700 dark:text-emerald-300 bg-emerald-500/5 font-semibold"
                                        >
                                            {{ inspectedLog.properties?.attributes?.[key] !== undefined ? JSON.stringify(inspectedLog.properties.attributes[key]) : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- CONTEXT METADATA PAYLOAD -->
                    <div v-if="inspectedLog.properties?.context" class="space-y-2">
                        <div class="font-bold text-foreground text-xs flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            <span>Execution Context Details</span>
                        </div>
                        <div class="rounded-md border border-border bg-muted/30 p-3 font-mono text-[11px] overflow-x-auto max-h-60">
                            <pre class="text-foreground whitespace-pre-wrap break-all">{{ JSON.stringify(inspectedLog.properties.context, null, 2) }}</pre>
                        </div>
                    </div>
                </div>

                <DialogFooter class="p-4 sm:px-6 border-t border-border shrink-0 bg-muted/20 flex items-center justify-between sm:justify-between w-full">
                    <Button
                        v-if="canDelete && inspectedLog"
                        type="button"
                        variant="destructive"
                        size="sm"
                        class="gap-1.5 text-xs"
                        @click="confirmDeleteSingle(inspectedLog)"
                    >
                        <Trash2 class="size-3.5" />
                        <span>Delete Record</span>
                    </Button>
                    <div v-else />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="isInspectModalOpen = false"
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- SINGLE LOG DELETE CONFIRMATION DIALOG -->
        <Dialog v-model:open="isSingleDeleteModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-5" />
                        <span>Delete Activity Log</span>
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to permanently delete this activity log record? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="logToDelete" class="p-3 rounded-lg border bg-muted/40 space-y-1 text-xs">
                    <div class="font-semibold text-foreground">{{ logToDelete.description }}</div>
                    <div class="text-muted-foreground">
                        Module: <span class="font-mono uppercase">{{ logToDelete.log_name }}</span> ·
                        Event: <span class="capitalize">{{ logToDelete.event }}</span>
                    </div>
                    <div class="text-[11px] text-muted-foreground font-mono">
                        {{ formatDate(logToDelete.created_at) }}
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="isDeletingSingle"
                        @click="isSingleDeleteModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="isDeletingSingle"
                        class="gap-1.5"
                        @click="executeDeleteSingle"
                    >
                        <Trash2 class="size-3.5" />
                        <span>{{ isDeletingSingle ? 'Deleting...' : 'Confirm Delete' }}</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- BATCH LOG DELETE CONFIRMATION DIALOG -->
        <Dialog v-model:open="isBatchDeleteModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-5" />
                        <span>Delete Selected Activity Logs</span>
                    </DialogTitle>
                    <DialogDescription>
                        You are about to permanently delete <strong class="text-foreground">{{ selectedIds.length }}</strong> selected audit trail records. This action cannot be reversed.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="isDeletingBatch"
                        @click="isBatchDeleteModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="isDeletingBatch"
                        class="gap-1.5"
                        @click="executeDeleteBatch"
                    >
                        <Trash2 class="size-3.5" />
                        <span>{{ isDeletingBatch ? 'Deleting...' : `Delete ${selectedIds.length} Records` }}</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- PURGE ACTIVITY LOGS DIALOG -->
        <Dialog v-model:open="isPurgeModalOpen">
            <DialogScrollContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <Trash2 class="size-5" />
                        <span>Purge Activity Logs</span>
                    </DialogTitle>
                    <DialogDescription>
                        Select the retention window to purge historical audit trail records. Purged logs cannot be recovered.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 py-2">
                    <Label class="text-xs font-semibold">Select Purge Scope</Label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer hover:bg-muted/40 transition-colors" :class="purgeScope === 'older_than_30' ? 'border-primary bg-primary/5' : 'border-border'">
                            <input type="radio" v-model="purgeScope" value="older_than_30" class="text-primary" />
                            <div class="text-xs">
                                <div class="font-medium text-foreground">Older than 30 Days</div>
                                <div class="text-muted-foreground text-[11px]">Retain recent month of audit history</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer hover:bg-muted/40 transition-colors" :class="purgeScope === 'older_than_60' ? 'border-primary bg-primary/5' : 'border-border'">
                            <input type="radio" v-model="purgeScope" value="older_than_60" class="text-primary" />
                            <div class="text-xs">
                                <div class="font-medium text-foreground">Older than 60 Days</div>
                                <div class="text-muted-foreground text-[11px]">Retain past 2 months of audit history</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer hover:bg-muted/40 transition-colors" :class="purgeScope === 'older_than_90' ? 'border-primary bg-primary/5' : 'border-border'">
                            <input type="radio" v-model="purgeScope" value="older_than_90" class="text-primary" />
                            <div class="text-xs">
                                <div class="font-medium text-foreground">Older than 90 Days</div>
                                <div class="text-muted-foreground text-[11px]">Retain past quarter of audit history</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border border-destructive/40 bg-destructive/5 cursor-pointer hover:bg-destructive/10 transition-colors">
                            <input type="radio" v-model="purgeScope" value="all" class="text-destructive" />
                            <div class="text-xs">
                                <div class="font-medium text-destructive">All Activity Logs (Complete Purge)</div>
                                <div class="text-destructive/80 text-[11px]">Delete all historical audit trail entries entirely</div>
                            </div>
                        </label>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="isPurging"
                        @click="isPurgeModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="isPurging"
                        class="gap-1.5"
                        @click="executePurge"
                    >
                        <Trash2 class="size-3.5" />
                        <span>{{ isPurging ? 'Purging...' : 'Confirm Purge' }}</span>
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
