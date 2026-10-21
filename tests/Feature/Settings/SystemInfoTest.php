<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_system_info(): void
    {
        $response = $this->get(route('system-info.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_non_superadmin_users_are_forbidden(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $student->assignRole(UserRole::STUDENT->value);

        $response = $this->actingAs($student)->get(route('system-info.index'));
        $response->assertForbidden();

        $teacher = User::factory()->create([
            'role' => UserRole::TEACHER,
            'email_verified_at' => now(),
        ]);
        $teacher->assignRole(UserRole::TEACHER->value);

        $response = $this->actingAs($teacher)->get(route('system-info.index'));
        $response->assertForbidden();
    }

    public function test_superadmin_can_view_system_info_page(): void
    {
        $superadmin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($superadmin)->get(route('system-info.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/SystemInfo')
            ->has('system')
            ->has('storageLink')
            ->has('logs')
            ->has('caches')
        );
    }

    public function test_superadmin_can_clear_application_logs(): void
    {
        $superadmin = User::where('username', 'superadmin')->firstOrFail();

        // Create a temporary test log in storage/logs
        $logPath = storage_path('logs/test-housekeeping.log');
        File::put($logPath, 'Sample log content for testing');

        $this->assertFileExists($logPath);

        $response = $this->actingAs($superadmin)->post(route('system-info.clear-logs'));

        $response->assertSessionHas('success');
        $this->assertFileDoesNotExist($logPath);

        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'system',
            'event' => 'cleared_logs',
        ]);
    }

    public function test_superadmin_can_link_storage(): void
    {
        $superadmin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($superadmin)->post(route('system-info.storage-link'));

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'system',
            'event' => 'linked_storage',
        ]);
    }

    public function test_superadmin_can_purge_optimization_caches(): void
    {
        $superadmin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($superadmin)->post(route('system-info.optimize-clear'));

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'system',
            'event' => 'cleared_cache',
        ]);
    }

    public function test_superadmin_can_optimize_system(): void
    {
        $superadmin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($superadmin)->post(route('system-info.optimize'));

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'system',
            'event' => 'optimized_system',
        ]);
    }

    public function test_superadmin_can_refresh_system(): void
    {
        $superadmin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($superadmin)->post(route('system-info.refresh'));

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'system',
            'event' => 'refreshed_system',
        ]);
    }
}
