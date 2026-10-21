<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_superadmin_can_view_activity_logs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPERADMIN,
        ]);
        $admin->assignRole(UserRole::SUPERADMIN->value);

        ActivityLogger::make('academic')
            ->causedBy($admin)
            ->event('created')
            ->withProperties(['attributes' => ['name' => 'JSS 1']])
            ->log("Created SchoolClass 'JSS 1'");

        $response = $this->actingAs($admin)->get(route('admin.activity-logs.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/activity-logs/Index')
            ->has('logs.data', 1)
            ->has('stats')
        );
    }

    public function test_unauthorized_user_cannot_view_activity_logs(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
        ]);
        $student->assignRole(UserRole::STUDENT->value);

        $response = $this->actingAs($student)->get(route('admin.activity-logs.index'));

        $response->assertForbidden();
    }

    public function test_activity_logs_can_be_filtered_by_event(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPERADMIN,
        ]);
        $admin->assignRole(UserRole::SUPERADMIN->value);

        ActivityLogger::make('academic')
            ->causedBy($admin)
            ->event('created')
            ->log('Created record');

        ActivityLogger::make('auth')
            ->causedBy($admin)
            ->event('login')
            ->log('User logged in');

        $response = $this->actingAs($admin)->get(route('admin.activity-logs.index', ['event' => 'login']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/activity-logs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.event', 'login')
        );
    }

    public function test_admin_can_export_activity_logs_as_csv(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPERADMIN,
        ]);
        $admin->assignRole(UserRole::SUPERADMIN->value);

        ActivityLogger::make('academic')
            ->causedBy($admin)
            ->event('promoted')
            ->log('Promoted student test');

        $response = $this->actingAs($admin)->get(route('admin.activity-logs.index', ['export' => '1']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_logs_activity_trait_records_model_changes(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPERADMIN,
        ]);
        $admin->assignRole(UserRole::SUPERADMIN->value);
        $this->actingAs($admin);

        $schoolClass = SchoolClass::create([
            'name' => 'Primary 1',
            'stage' => 'primary',
            'level_order' => 1,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => SchoolClass::class,
            'subject_id' => $schoolClass->id,
            'event' => 'created',
        ]);

        $schoolClass->update(['name' => 'Primary 1 Gold']);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => SchoolClass::class,
            'subject_id' => $schoolClass->id,
            'event' => 'updated',
        ]);
    }

    public function test_user_with_delete_permission_can_delete_single_activity_log(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN]);
        $user->givePermissionTo('activity_logs.delete');

        $log = ActivityLogger::make('general')->log('Sample log entry for deletion');

        $response = $this->actingAs($user)->delete(route('admin.activity-logs.destroy', $log));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('activity_logs', ['id' => $log->id]);
    }

    public function test_user_without_delete_permission_cannot_delete_activity_log(): void
    {
        $user = User::factory()->create(['role' => UserRole::TEACHER]);
        $user->givePermissionTo('activity_logs.view');

        $log = ActivityLogger::make('general')->log('Protected log entry');

        $response = $this->actingAs($user)->delete(route('admin.activity-logs.destroy', $log));

        $response->assertForbidden();
        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
    }

    public function test_user_with_delete_permission_can_batch_delete_activity_logs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPERADMIN]);
        $admin->assignRole(UserRole::SUPERADMIN->value);

        $log1 = ActivityLogger::make('general')->log('Log 1');
        $log2 = ActivityLogger::make('general')->log('Log 2');
        $log3 = ActivityLogger::make('general')->log('Log 3');

        $response = $this->actingAs($admin)->delete(route('admin.activity-logs.destroy-batch'), [
            'ids' => [$log1->id, $log2->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('activity_logs', ['id' => $log1->id]);
        $this->assertDatabaseMissing('activity_logs', ['id' => $log2->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $log3->id]);
    }

    public function test_user_with_delete_permission_can_purge_activity_logs_by_scope(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPERADMIN]);
        $admin->assignRole(UserRole::SUPERADMIN->value);

        $oldLog = ActivityLogger::make('general')->log('Old log entry');
        $oldLog->created_at = now()->subDays(40);
        $oldLog->save();

        $recentLog = ActivityLogger::make('general')->log('Recent log entry');

        $response = $this->actingAs($admin)->post(route('admin.activity-logs.clear'), [
            'scope' => 'older_than_30',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('activity_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $recentLog->id]);
    }
}
