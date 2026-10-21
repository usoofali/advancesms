<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class SystemInfoController extends Controller
{
    /**
     * Display the System Information & Housekeeping Management page.
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('settings/SystemInfo', [
            'system' => $this->getSystemMetrics(),
            'storageLink' => $this->getStorageLinkMetrics(),
            'logs' => $this->getLogFilesMetrics(),
            'caches' => $this->getCacheStatusMetrics(),
        ]);
    }

    /**
     * Truncate and purge application log files.
     */
    public function clearLogs(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $logPath = storage_path('logs');
        $reclaimedBytes = 0;
        $filesProcessed = 0;

        if (File::isDirectory($logPath)) {
            $files = File::glob($logPath.'/*.log');

            foreach ($files as $file) {
                $fileSize = (int) File::size($file);
                $reclaimedBytes += $fileSize;
                $filesProcessed++;

                // Truncate active logs or remove rotated logs
                if (basename($file) === 'laravel.log' || basename($file) === 'browser.log') {
                    File::put($file, '');
                } else {
                    File::delete($file);
                }
            }
        }

        ActivityLogger::make('system')
            ->performedOn(null)
            ->event('cleared_logs')
            ->withProperties([
                'files_count' => $filesProcessed,
                'reclaimed_bytes' => $reclaimedBytes,
                'reclaimed_formatted' => $this->formatBytes($reclaimedBytes),
            ])
            ->log("Purged application logs: Reclaimed {$this->formatBytes($reclaimedBytes)} across {$filesProcessed} file(s).");

        return back()->with('success', "Application logs cleared successfully! Reclaimed {$this->formatBytes($reclaimedBytes)} of disk space.");
    }

    /**
     * Create or verify the public storage symbolic link.
     */
    public function linkStorage(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        if (! app()->environment('testing')) {
            Artisan::call('storage:link', ['--force' => true]);
            $output = trim(Artisan::output());
        } else {
            $output = 'The [public/storage] link has been connected.';
        }

        ActivityLogger::make('system')
            ->performedOn(null)
            ->event('linked_storage')
            ->withProperties(['output' => $output])
            ->log('Connected public storage symbolic link.');

        return back()->with('success', 'Public storage symlink successfully connected [public/storage -> storage/app/public].');
    }

    /**
     * Run php artisan optimize:clear.
     */
    public function clearCache(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        if (! app()->environment('testing')) {
            Artisan::call('optimize:clear');
            $output = trim(Artisan::output());
        } else {
            $output = 'All optimization caches cleared.';
        }

        ActivityLogger::make('system')
            ->performedOn(null)
            ->event('cleared_cache')
            ->withProperties(['output' => $output])
            ->log('Purged system optimization caches.');

        return back()->with('success', 'Optimization caches purged successfully (Application cache, config, routes, views, and events).');
    }

    /**
     * Run php artisan optimize.
     */
    public function optimize(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        if (! app()->environment('testing')) {
            Artisan::call('optimize');
            $output = trim(Artisan::output());
        } else {
            $output = 'System optimized.';
        }

        ActivityLogger::make('system')
            ->performedOn(null)
            ->event('optimized_system')
            ->withProperties(['output' => $output])
            ->log('Compiled production cache manifests.');

        return back()->with('success', 'System optimized successfully: Routes, configuration, and Blade views cached for production.');
    }

    /**
     * Run full system refresh: clear caches then re-optimize.
     */
    public function refreshSystem(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        if (! app()->environment('testing')) {
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
        }

        ActivityLogger::make('system')
            ->performedOn(null)
            ->event('refreshed_system')
            ->log('Executed full system refresh: purged caches and re-compiled optimization manifests.');

        return back()->with('success', 'System refreshed successfully: All stale caches purged and fresh manifests compiled.');
    }

    /**
     * Ensure the authenticated user has superadmin clearance.
     */
    protected function ensureSuperAdmin(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized. Super Administrator clearance required for system housekeeping.');
        }
    }

    /**
     * Gather system runtime specifications and server metrics.
     *
     * @return array<string, mixed>
     */
    protected function getSystemMetrics(): array
    {
        $diskPath = base_path();
        $freeDisk = @disk_free_space($diskPath) ?: 0;
        $totalDisk = @disk_total_space($diskPath) ?: 1;
        $usedDisk = max(0, $totalDisk - $freeDisk);
        $usedDiskPercent = round(($usedDisk / $totalDisk) * 100, 1);

        $dbVersion = 'N/A';
        try {
            $dbVersion = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION) ?: 'N/A';
        } catch (\Throwable) {
            // Silently handle if disconnected
        }

        return [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'app_url' => config('app.url'),
            'app_timezone' => config('app.timezone'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_os' => php_uname('s').' '.php_uname('r').' ('.php_uname('m').')',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
            'server_time' => now()->format('D, d M Y H:i:s T'),
            'memory_limit' => ini_get('memory_limit') ?: 'N/A',
            'max_execution_time' => (ini_get('max_execution_time') ?: '0').'s',
            'opcache_enabled' => extension_loaded('Zend OPcache') && (bool) ini_get('opcache.enable'),
            'database' => [
                'driver' => config('database.default'),
                'database' => config('database.connections.'.config('database.default').'.database'),
                'version' => $dbVersion,
            ],
            'drivers' => [
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
                'mail' => config('mail.default'),
            ],
            'disk' => [
                'free_formatted' => $this->formatBytes($freeDisk),
                'total_formatted' => $this->formatBytes($totalDisk),
                'used_formatted' => $this->formatBytes($usedDisk),
                'used_percentage' => $usedDiskPercent,
            ],
        ];
    }

    /**
     * Gather storage symlink diagnostics.
     *
     * @return array<string, mixed>
     */
    protected function getStorageLinkMetrics(): array
    {
        $publicStoragePath = public_path('storage');
        $targetStoragePath = storage_path('app/public');

        $isLink = is_link($publicStoragePath);
        $fileExists = file_exists($publicStoragePath);
        $targetValid = false;

        if ($isLink) {
            $actualTarget = @readlink($publicStoragePath);
            $targetValid = ($actualTarget === $targetStoragePath || realpath($publicStoragePath) === realpath($targetStoragePath));
        }

        $isLinked = ($isLink && $targetValid) || ($fileExists && is_dir($publicStoragePath));

        return [
            'is_linked' => $isLinked,
            'status' => $isLinked ? 'linked' : 'not_linked',
            'link_path' => 'public/storage',
            'target_path' => 'storage/app/public',
            'exists' => $fileExists,
            'is_symlink' => $isLink,
        ];
    }

    /**
     * Gather log files sizes and metadata.
     *
     * @return array<string, mixed>
     */
    protected function getLogFilesMetrics(): array
    {
        $logPath = storage_path('logs');
        $files = [];
        $totalBytes = 0;

        if (File::isDirectory($logPath)) {
            $logFiles = File::glob($logPath.'/*.log');

            foreach ($logFiles as $filePath) {
                $size = (int) File::size($filePath);
                $totalBytes += $size;
                $lastModified = File::lastModified($filePath);

                $files[] = [
                    'name' => basename($filePath),
                    'size_bytes' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'modified_at' => Carbon::createFromTimestamp($lastModified)->format('Y-m-d H:i:s'),
                    'modified_human' => Carbon::createFromTimestamp($lastModified)->diffForHumans(),
                ];
            }
        }

        // Sort largest logs first
        usort($files, fn ($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);

        return [
            'files' => $files,
            'total_size_bytes' => $totalBytes,
            'total_size_formatted' => $this->formatBytes($totalBytes),
            'count' => count($files),
        ];
    }

    /**
     * Gather framework cache statuses.
     *
     * @return array<string, bool>
     */
    protected function getCacheStatusMetrics(): array
    {
        return [
            'config_cached' => app()->configurationIsCached(),
            'routes_cached' => app()->routesAreCached(),
            'events_cached' => app()->eventsAreCached(),
        ];
    }

    /**
     * Format byte values to human readable units.
     */
    protected function formatBytes(float|int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $precision).' '.$units[$power];
    }
}
