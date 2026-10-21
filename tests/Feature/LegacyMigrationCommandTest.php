<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegacyMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test command fails gracefully if database is invalid or inaccessible.
     */
    public function test_migration_command_fails_gracefully_with_invalid_database(): void
    {
        $this->artisan('migrate:legacy-sms', [
            '--db' => 'non_existent_legacy_database_xyz',
            '--dry-run' => true,
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('Failed to connect to legacy database');
    }

    /**
     * Test legacy 40-character SHA-1 password hashes are transparently upgraded to bcrypt on login.
     */
    public function test_legacy_sha1_passwords_are_transparently_upgraded_on_login(): void
    {
        $plainPassword = 'legacyPassword123';
        $sha1Hash = sha1($plainPassword);

        // Insert user directly with legacy 40-character SHA-1 password (bypassing model cast)
        $userId = DB::table('users')->insertGetId([
            'username' => 'GSSS25-99999',
            'name' => 'Legacy Student',
            'email' => 'legacystudent@school.local',
            'password' => $sha1Hash,
            'role' => UserRole::STUDENT->value,
            'gender' => 'male',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::find($userId);
        $this->assertEquals(40, strlen($user->password));
        $this->assertEquals($sha1Hash, $user->password);

        // Attempt login via username
        $response = $this->post('/login', [
            'email' => 'GSSS25-99999',
            'password' => $plainPassword,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // Verify password has been upgraded to bcrypt
        $user->refresh();
        $this->assertNotEquals($sha1Hash, $user->password);
        $this->assertTrue(Hash::check($plainPassword, $user->password));
    }
}
