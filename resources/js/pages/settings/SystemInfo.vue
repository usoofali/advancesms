<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Cpu,
    Database,
    HardDrive,
    Info,
    Link2,
    RefreshCw,
    Server,
    ShieldAlert,
    Sparkles,
    Terminal,
    Trash2,
    Zap,
} from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import {
    clearLogs as clearLogsRoute,
    index as systemInfoIndex,
    optimize as optimizeRoute,
    optimizeClear as optimizeClearRoute,
    refresh as refreshRoute,
    storageLink as storageLinkRoute,
} from '@/routes/system-info';

interface SystemMetric {
    app_name: string;
    app_env: string;
    app_debug: boolean;
    app_url: string;
    app_timezone: string;
    php_version: string;
    laravel_version: string;
    server_os: string;
    server_software: string;
    server_time: string;
    memory_limit: string;
    max_execution_time: string;
    opcache_enabled: boolean;
    database: {
        driver: string;
        database: string;
        version: string;
    };
    drivers: {
        cache: string;
        queue: string;
        session: string;
        mail: string;
    };
    disk: {
        free_formatted: string;
        total_formatted: string;
        used_formatted: string;
        used_percentage: number;
    };
}

interface StorageLinkMetric {
    is_linked: boolean;
    status: 'linked' | 'not_linked';
    link_path: string;
    target_path: string;
    exists: boolean;
    is_symlink: boolean;
}

interface LogFile {
    name: string;
    size_bytes: number;
    size_formatted: string;
    modified_at: string;
    modified_human: string;
}

interface LogsMetric {
    files: LogFile[];
    total_size_bytes: number;
    total_size_formatted: string;
    count: number;
}

interface CacheStatusMetric {
    config_cached: boolean;
    routes_cached: boolean;
    events_cached: boolean;
}

const props = defineProps<{
    system: SystemMetric;
    storageLink: StorageLinkMetric;
    logs: LogsMetric;
    caches: CacheStatusMetric;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'System Info & Housekeeping',
                href: systemInfoIndex().url,
            },
        ],
    },
});

// Action loading states
const isClearingLogs = ref(false);
const isLinkingStorage = ref(false);
const isClearingCache = ref(false);
const isOptimizing = ref(false);
const isRefreshing = ref(false);

// Confirm Dialog State for Log Purge
const showLogClearConfirm = ref(false);

const handleClearLogs = () => {
    isClearingLogs.value = true;
    router.post(
        clearLogsRoute().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Application logs cleared successfully!');
                showLogClearConfirm.value = false;
            },
            onError: (err) => {
                toast.error('Failed to clear application logs.');
                console.error(err);
            },
            onFinish: () => {
                isClearingLogs.value = false;
            },
        }
    );
};

const handleStorageLink = () => {
    isLinkingStorage.value = true;
    router.post(
        storageLinkRoute().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Storage symlink verified and linked successfully!');
            },
            onError: (err) => {
                toast.error('Failed to link storage.');
                console.error(err);
            },
            onFinish: () => {
                isLinkingStorage.value = false;
            },
        }
    );
};

const handleOptimizeClear = () => {
    isClearingCache.value = true;
    router.post(
        optimizeClearRoute().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('All optimization caches purged successfully.');
            },
            onError: (err) => {
                toast.error('Failed to clear caches.');
                console.error(err);
            },
            onFinish: () => {
                isClearingCache.value = false;
            },
        }
    );
};

const handleOptimize = () => {
    isOptimizing.value = true;
    router.post(
        optimizeRoute().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('System optimized: Routes, configuration & views cached!');
            },
            onError: (err) => {
                toast.error('Failed to optimize system.');
                console.error(err);
            },
            onFinish: () => {
                isOptimizing.value = false;
            },
        }
    );
};

const handleRefreshSystem = () => {
    isRefreshing.value = true;
    router.post(
        refreshRoute().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Full system refresh completed successfully.');
            },
            onError: (err) => {
                toast.error('Failed to execute system refresh.');
                console.error(err);
            },
            onFinish: () => {
                isRefreshing.value = false;
            },
        }
    );
};
</script>

<template>
    <Head title="System Info & Housekeeping" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                variant="small"
                title="System Info & Maintenance"
                description="Inspect institutional runtime metrics, clean disk logs, manage public storage symlinks, and execute framework optimization housekeeping."
            />
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 shadow-xs"
                    :disabled="isRefreshing"
                    @click="handleRefreshSystem"
                >
                    <RefreshCw :class="['h-3.5 w-3.5', { 'animate-spin': isRefreshing }]" />
                    <span>Refresh System</span>
                </Button>
            </div>
        </div>

        <!-- 4 Quick Status Metric Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Storage Link Status Card -->
            <Card class="border shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription class="text-xs font-medium">Storage Symlink</CardDescription>
                        <Link2 class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="mt-1 flex items-center gap-2">
                        <Badge
                            :variant="props.storageLink.is_linked ? 'default' : 'destructive'"
                            class="gap-1 font-semibold"
                            :class="props.storageLink.is_linked ? 'bg-emerald-600 hover:bg-emerald-600' : ''"
                        >
                            <component
                                :is="props.storageLink.is_linked ? CheckCircle2 : AlertTriangle"
                                class="h-3.5 w-3.5"
                            />
                            {{ props.storageLink.is_linked ? 'Linked & Active' : 'Not Linked' }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="p-4 pt-1">
                    <p class="text-xs text-muted-foreground font-mono truncate">
                        {{ props.storageLink.link_path }}
                    </p>
                </CardContent>
            </Card>

            <!-- Logs Size Metric Card -->
            <Card class="border shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription class="text-xs font-medium">Disk Logs</CardDescription>
                        <Terminal class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight">
                            {{ props.logs.total_size_formatted }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            ({{ props.logs.count }} {{ props.logs.count === 1 ? 'file' : 'files' }})
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="p-4 pt-1">
                    <p class="text-xs text-muted-foreground">
                        Located in <code class="bg-muted px-1 py-0.5 rounded text-[11px]">storage/logs</code>
                    </p>
                </CardContent>
            </Card>

            <!-- Framework Cache Status Card -->
            <Card class="border shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription class="text-xs font-medium">Framework Cache</CardDescription>
                        <Zap class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="mt-1 flex items-center gap-1.5">
                        <Badge
                            v-if="props.caches.config_cached && props.caches.routes_cached"
                            class="bg-emerald-600 hover:bg-emerald-600 text-xs font-medium"
                        >
                            Optimized
                        </Badge>
                        <Badge
                            v-else
                            variant="secondary"
                            class="text-xs font-medium"
                        >
                            Development Mode
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="p-4 pt-1">
                    <p class="text-xs text-muted-foreground">
                        Config: {{ props.caches.config_cached ? 'Cached' : 'Live' }} • Routes: {{ props.caches.routes_cached ? 'Cached' : 'Live' }}
                    </p>
                </CardContent>
            </Card>

            <!-- Server Environment Metric Card -->
            <Card class="border shadow-xs">
                <CardHeader class="p-4 pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription class="text-xs font-medium">Environment</CardDescription>
                        <Server class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-lg font-bold uppercase tracking-wide">
                            {{ props.system.app_env }}
                        </span>
                        <Badge
                            :variant="props.system.app_debug ? 'outline' : 'secondary'"
                            class="text-[11px]"
                            :class="props.system.app_debug ? 'border-amber-500 text-amber-600 dark:text-amber-400' : ''"
                        >
                            Debug: {{ props.system.app_debug ? 'On' : 'Off' }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="p-4 pt-1">
                    <p class="text-xs text-muted-foreground">
                        PHP {{ props.system.php_version }} • Laravel {{ props.system.laravel_version }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Housekeeping Actions Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Log Files & Purge Card -->
            <Card class="border shadow-xs">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base flex items-center gap-2">
                                <Terminal class="h-4 w-4 text-primary" />
                                Application Log Management
                            </CardTitle>
                            <CardDescription class="text-xs mt-1">
                                Monitor application log file footprint and reclaim storage by truncating active log buffers.
                            </CardDescription>
                        </div>
                        <Button
                            variant="destructive"
                            size="sm"
                            class="gap-1.5 shadow-xs"
                            :disabled="props.logs.total_size_bytes === 0 || isClearingLogs"
                            @click="showLogClearConfirm = true"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>Clear Logs</span>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="rounded-md border divide-y text-xs">
                        <div class="flex items-center justify-between bg-muted/50 px-3 py-2 font-medium text-muted-foreground">
                            <span>Log File</span>
                            <div class="flex items-center gap-8">
                                <span>Last Updated</span>
                                <span class="w-16 text-right">Size</span>
                            </div>
                        </div>

                        <template v-if="props.logs.files.length > 0">
                            <div
                                v-for="file in props.logs.files"
                                :key="file.name"
                                class="flex items-center justify-between px-3 py-2.5 transition-colors hover:bg-muted/30"
                            >
                                <div class="flex items-center gap-2 font-mono">
                                    <Terminal class="h-3.5 w-3.5 text-muted-foreground" />
                                    <span class="font-medium text-foreground">{{ file.name }}</span>
                                </div>
                                <div class="flex items-center gap-8">
                                    <span class="text-muted-foreground" :title="file.modified_at">
                                        {{ file.modified_human }}
                                    </span>
                                    <Badge variant="outline" class="w-16 justify-center font-mono text-[11px]">
                                        {{ file.size_formatted }}
                                    </Badge>
                                </div>
                            </div>
                        </template>
                        <div v-else class="p-6 text-center text-muted-foreground text-xs">
                            No log files found in storage/logs.
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-muted-foreground px-1">
                        <span>Total footprint: <strong>{{ props.logs.total_size_formatted }}</strong></span>
                        <span>Safety notice: Clearing truncates log contents without breaking file descriptors.</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Public Storage Symbolic Link Card -->
            <Card class="border shadow-xs">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base flex items-center gap-2">
                                <Link2 class="h-4 w-4 text-primary" />
                                Public Storage Symbolic Link
                            </CardTitle>
                            <CardDescription class="text-xs mt-1">
                                Maps web-accessible media (student photos, report card stamps, logos) from private storage to the public root.
                            </CardDescription>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            class="gap-1.5 shadow-xs"
                            :disabled="isLinkingStorage"
                            @click="handleStorageLink"
                        >
                            <Spinner v-if="isLinkingStorage" class="h-3.5 w-3.5" />
                            <Link2 v-else class="h-3.5 w-3.5" />
                            <span>{{ props.storageLink.is_linked ? 'Recreate Link' : 'Create Link' }}</span>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4 text-xs">
                    <div class="p-4 rounded-lg border bg-muted/30 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground font-medium">Link Status:</span>
                            <Badge
                                :variant="props.storageLink.is_linked ? 'default' : 'destructive'"
                                class="gap-1 font-semibold"
                                :class="props.storageLink.is_linked ? 'bg-emerald-600 hover:bg-emerald-600' : ''"
                            >
                                <component
                                    :is="props.storageLink.is_linked ? CheckCircle2 : AlertTriangle"
                                    class="h-3.5 w-3.5"
                                />
                                {{ props.storageLink.is_linked ? 'Symbolic Link Healthy' : 'Symlink Missing or Broken' }}
                            </Badge>
                        </div>

                        <Separator />

                        <div class="space-y-1.5 font-mono text-[11px]">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Source Symlink:</span>
                                <span class="font-medium text-foreground bg-background px-2 py-0.5 rounded border">
                                    {{ props.storageLink.link_path }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Target Storage:</span>
                                <span class="font-medium text-foreground bg-background px-2 py-0.5 rounded border">
                                    {{ props.storageLink.target_path }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded text-amber-800 dark:text-amber-300 text-xs flex items-start gap-2">
                        <Info class="h-4 w-4 shrink-0 mt-0.5" />
                        <div>
                            Executes <code>php artisan storage:link --force</code> to ensure student profile avatars, report card letterheads, and school logos load properly across all browser clients.
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Framework Cache & Optimization Card -->
            <Card class="border shadow-xs lg:col-span-2">
                <CardHeader>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle class="text-base flex items-center gap-2">
                                <Sparkles class="h-4 w-4 text-primary" />
                                Framework Optimization & Cache Maintenance
                            </CardTitle>
                            <CardDescription class="text-xs mt-1">
                                Purge stale compiled files or generate high-speed production manifests for configuration, routes, and views.
                            </CardDescription>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                variant="destructive"
                                size="sm"
                                class="h-8 gap-1.5 shadow-xs"
                                :disabled="isClearingCache"
                                @click="handleOptimizeClear"
                            >
                                <Spinner v-if="isClearingCache" class="h-3.5 w-3.5" />
                                <Trash2 v-else class="h-3.5 w-3.5" />
                                <span>Purge Caches (optimize:clear)</span>
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 shadow-xs bg-background"
                                :disabled="isOptimizing"
                                @click="handleOptimize"
                            >
                                <Spinner v-if="isOptimizing" class="h-3.5 w-3.5" />
                                <Zap v-else class="h-3.5 w-3.5 text-amber-500" />
                                <span>Compile Caches (optimize)</span>
                            </Button>

                            <Button
                                size="sm"
                                class="h-8 gap-1.5 shadow-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                                :disabled="isRefreshing"
                                @click="handleRefreshSystem"
                            >
                                <Spinner v-if="isRefreshing" class="h-3.5 w-3.5" />
                                <RefreshCw v-else class="h-3.5 w-3.5" />
                                <span>Full Refresh Cycle</span>
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 text-xs">
                        <!-- Configuration Cache -->
                        <div class="p-3 rounded-lg border bg-muted/20 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-foreground">Config Cache</span>
                                <Badge
                                    :variant="props.caches.config_cached ? 'default' : 'secondary'"
                                    class="text-[11px]"
                                    :class="props.caches.config_cached ? 'bg-emerald-600 hover:bg-emerald-600' : ''"
                                >
                                    {{ props.caches.config_cached ? 'Compiled' : 'Dynamic' }}
                                </Badge>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Speeds up environment configuration reads.
                            </p>
                        </div>

                        <!-- Route Cache -->
                        <div class="p-3 rounded-lg border bg-muted/20 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-foreground">Route Cache</span>
                                <Badge
                                    :variant="props.caches.routes_cached ? 'default' : 'secondary'"
                                    class="text-[11px]"
                                    :class="props.caches.routes_cached ? 'bg-emerald-600 hover:bg-emerald-600' : ''"
                                >
                                    {{ props.caches.routes_cached ? 'Compiled' : 'Dynamic' }}
                                </Badge>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                High-speed route resolution and lookup table.
                            </p>
                        </div>

                        <!-- Event Cache -->
                        <div class="p-3 rounded-lg border bg-muted/20 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-foreground">Event Cache</span>
                                <Badge
                                    :variant="props.caches.events_cached ? 'default' : 'secondary'"
                                    class="text-[11px]"
                                    :class="props.caches.events_cached ? 'bg-emerald-600 hover:bg-emerald-600' : ''"
                                >
                                    {{ props.caches.events_cached ? 'Compiled' : 'Dynamic' }}
                                </Badge>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Pre-dispatched listeners and event mappings.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- System Specifications & Server Runtime Information -->
        <Card class="border shadow-xs">
            <CardHeader>
                <CardTitle class="text-base flex items-center gap-2">
                    <Server class="h-4 w-4 text-primary" />
                    Institutional Server & Runtime Specifications
                </CardTitle>
                <CardDescription class="text-xs">
                    Comprehensive environment details, database engine status, and runtime thresholds.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Specs Grid -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 text-xs">
                    <!-- Column 1: Core Framework -->
                    <div class="space-y-3">
                        <div class="font-semibold text-foreground flex items-center gap-1.5 text-sm">
                            <Cpu class="h-4 w-4 text-primary" />
                            Platform & Engine
                        </div>
                        <div class="space-y-2 border rounded-md p-3 bg-muted/20">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">PHP Version:</span>
                                <span class="font-mono font-medium">{{ props.system.php_version }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Laravel Version:</span>
                                <span class="font-mono font-medium">{{ props.system.laravel_version }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">OPcache:</span>
                                <span class="font-medium" :class="props.system.opcache_enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">
                                    {{ props.system.opcache_enabled ? 'Active' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Web Server:</span>
                                <span class="font-medium truncate max-w-[120px]" :title="props.system.server_software">
                                    {{ props.system.server_software }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Database Status -->
                    <div class="space-y-3">
                        <div class="font-semibold text-foreground flex items-center gap-1.5 text-sm">
                            <Database class="h-4 w-4 text-primary" />
                            Database Connection
                        </div>
                        <div class="space-y-2 border rounded-md p-3 bg-muted/20">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Driver:</span>
                                <span class="font-medium uppercase">{{ props.system.database.driver }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Database Name:</span>
                                <span class="font-mono font-medium text-primary">{{ props.system.database.database }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Engine Version:</span>
                                <span class="font-mono font-medium truncate max-w-[120px]" :title="props.system.database.version">
                                    {{ props.system.database.version }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Connection:</span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">Online</span>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: Drivers & Services -->
                    <div class="space-y-3">
                        <div class="font-semibold text-foreground flex items-center gap-1.5 text-sm">
                            <Activity class="h-4 w-4 text-primary" />
                            System Drivers
                        </div>
                        <div class="space-y-2 border rounded-md p-3 bg-muted/20">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Cache Driver:</span>
                                <span class="font-mono font-medium">{{ props.system.drivers.cache }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Queue Connection:</span>
                                <span class="font-mono font-medium">{{ props.system.drivers.queue }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Session Driver:</span>
                                <span class="font-mono font-medium">{{ props.system.drivers.session }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Mail Driver:</span>
                                <span class="font-mono font-medium">{{ props.system.drivers.mail }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Column 4: Server Limits & Time -->
                    <div class="space-y-3">
                        <div class="font-semibold text-foreground flex items-center gap-1.5 text-sm">
                            <Clock class="h-4 w-4 text-primary" />
                            Limits & Timezone
                        </div>
                        <div class="space-y-2 border rounded-md p-3 bg-muted/20">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Memory Limit:</span>
                                <span class="font-mono font-medium">{{ props.system.memory_limit }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Max Execution:</span>
                                <span class="font-mono font-medium">{{ props.system.max_execution_time }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Timezone:</span>
                                <span class="font-mono font-medium truncate max-w-[120px]">{{ props.system.app_timezone }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Debug Mode:</span>
                                <span :class="props.system.app_debug ? 'text-amber-500 font-bold' : 'text-emerald-600 font-medium'">
                                    {{ props.system.app_debug ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Server Disk Footprint Bar -->
                <div class="p-4 rounded-lg border bg-muted/30 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <HardDrive class="h-4 w-4 text-muted-foreground" />
                            <span class="font-semibold">Local Storage Footprint</span>
                        </div>
                        <span class="text-muted-foreground">
                            {{ props.system.disk.used_formatted }} used of {{ props.system.disk.total_formatted }} ({{ props.system.disk.free_formatted }} free)
                        </span>
                    </div>
                    <div class="w-full bg-muted rounded-full h-2.5 overflow-hidden">
                        <div
                            class="bg-primary h-2.5 rounded-full transition-all"
                            :style="{ width: `${props.system.disk.used_percentage}%` }"
                        />
                    </div>
                    <div class="flex justify-between text-[11px] text-muted-foreground font-mono">
                        <span>Base Path: AdvanceSMS Server Root</span>
                        <span>{{ props.system.disk.used_percentage }}% utilized</span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Confirm Log Clear Dialog -->
    <Dialog :open="showLogClearConfirm" @update:open="showLogClearConfirm = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-2 text-destructive">
                    <ShieldAlert class="h-5 w-5" />
                    <DialogTitle>Confirm Log File Purge</DialogTitle>
                </div>
                <DialogDescription class="pt-2 text-xs">
                    Are you sure you want to purge all application log files? This will truncate active logs and remove dated error logs, reclaiming
                    <strong>{{ props.logs.total_size_formatted }}</strong> of disk space. This operation is permanent and cannot be reversed.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="isClearingLogs"
                    @click="showLogClearConfirm = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    class="gap-1.5"
                    :disabled="isClearingLogs"
                    @click="handleClearLogs"
                >
                    <Spinner v-if="isClearingLogs" class="h-3.5 w-3.5" />
                    <Trash2 v-else class="h-3.5 w-3.5" />
                    <span>Confirm & Purge Logs</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
