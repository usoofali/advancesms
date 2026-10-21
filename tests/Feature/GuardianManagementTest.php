<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\StandardAcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardAcademicSeeder::class);
    }

    public function test_guests_cannot_view_guardians_directory(): void
    {
        $this->get(route('people.guardians.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_users_cannot_view_guardians(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'email_verified_at' => now(),
        ]);
        $studentUser->assignRole(UserRole::STUDENT->value);

        $this->actingAs($studentUser)
            ->get(route('people.guardians.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_guardians_directory(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('people.guardians.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('people/guardians/Index')
            ->has('guardians')
            ->has('stats')
        );
    }

    public function test_can_create_guardian_without_portal_account(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'name' => 'Alhaji Sani Bello',
            'relationship' => 'Father',
            'phone' => '08033445566',
            'email' => 'sani.bello@example.com',
            'occupation' => 'Businessman',
            'address' => 'No. 5 Canteen Daji, Gusau',
            'create_user_account' => false,
        ];

        $response = $this->actingAs($admin)->post(route('people.guardians.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('guardians', [
            'name' => 'Alhaji Sani Bello',
            'relationship' => 'Father',
            'phone' => '08033445566',
            'email' => 'sani.bello@example.com',
            'occupation' => 'Businessman',
            'user_id' => null,
        ]);
    }

    public function test_can_create_guardian_with_portal_account(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $payload = [
            'name' => 'Hajiya Aisha Mohammed',
            'relationship' => 'Mother',
            'phone' => '08022334455',
            'email' => 'aisha.m@example.com',
            'occupation' => 'Educationist',
            'create_user_account' => true,
            'username' => 'aisha_mohammed_p',
            'password' => 'ParentSecret123#',
        ];

        $response = $this->actingAs($admin)->post(route('people.guardians.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $guardian = Guardian::where('email', 'aisha.m@example.com')->firstOrFail();
        $this->assertNotNull($guardian->user_id);

        $user = $guardian->user;
        $this->assertNotNull($user);
        $this->assertSame('aisha_mohammed_p', $user->username);
        $this->assertSame(UserRole::GUARDIAN, $user->role);
        $this->assertTrue(Hash::check('ParentSecret123#', $user->password));
        $this->assertTrue($user->hasRole('guardian'));
    }

    public function test_can_update_guardian_and_linked_user(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $user = User::factory()->create([
            'name' => 'Old Name',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'role' => UserRole::GUARDIAN,
            'phone' => '08011112222',
            'email' => 'old@example.com',
        ]);
        $user->assignRole(UserRole::GUARDIAN->value);

        $guardian = Guardian::create([
            'user_id' => $user->id,
            'name' => 'Old Name',
            'relationship' => 'Parent',
            'phone' => '08011112222',
            'email' => 'old@example.com',
        ]);

        $updatePayload = [
            'name' => 'Dr. Usman Danfodio',
            'relationship' => 'Guardian',
            'phone' => '08099887711',
            'email' => 'usman.d@example.com',
            'occupation' => 'Physician',
            'address' => 'Hospital Quarters, Gusau',
        ];

        $response = $this->actingAs($admin)->put(route('people.guardians.update', $guardian), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'name' => 'Dr. Usman Danfodio',
            'relationship' => 'Guardian',
            'phone' => '08099887711',
            'email' => 'usman.d@example.com',
            'occupation' => 'Physician',
        ]);

        $user->refresh();
        $this->assertSame('Dr. Usman Danfodio', $user->name);
        $this->assertSame('08099887711', $user->phone);
        $this->assertSame('usman.d@example.com', $user->email);
    }

    public function test_cannot_delete_guardian_with_active_student_wards(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $guardian = Guardian::create([
            'name' => 'Parent With Ward',
            'relationship' => 'Father',
            'phone' => '08055554444',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'guardian_id' => $guardian->id,
            'admission_number' => 'GSSS26-00099',
            'admission_date' => '2026-09-01',
        ]);

        $response = $this->actingAs($admin)->delete(route('people.guardians.destroy', $guardian));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id]);
    }

    public function test_can_delete_guardian_without_student_wards(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $guardian = Guardian::create([
            'name' => 'Unattached Guardian',
            'relationship' => 'Uncle',
            'phone' => '08012349999',
        ]);

        $response = $this->actingAs($admin)->delete(route('people.guardians.destroy', $guardian));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('guardians', ['id' => $guardian->id]);
    }

    public function test_can_enable_portal_account_when_updating_guardian_without_initial_account(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        $guardian = Guardian::create([
            'name' => 'Malam Bello Gusau',
            'relationship' => 'Father',
            'phone' => '08077665544',
            'email' => 'bello.gusau@example.com',
            'user_id' => null,
        ]);

        $this->assertNull($guardian->user_id);

        $updatePayload = [
            'name' => 'Malam Bello Gusau',
            'relationship' => 'Father',
            'phone' => '08077665544',
            'email' => 'bello.gusau@example.com',
            'create_user_account' => true,
            'username' => 'bello_gusau_p',
            'password' => 'PortalPass123#',
        ];

        $response = $this->actingAs($admin)->put(route('people.guardians.update', $guardian), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $guardian->refresh();
        $this->assertNotNull($guardian->user_id);

        $user = $guardian->user;
        $this->assertNotNull($user);
        $this->assertSame('bello_gusau_p', $user->username);
        $this->assertSame('bello.gusau@example.com', $user->email);
        $this->assertSame(UserRole::GUARDIAN, $user->role);
        $this->assertTrue(Hash::check('PortalPass123#', $user->password));
        $this->assertTrue($user->hasRole('guardian'));
    }

    public function test_cannot_create_guardian_portal_account_with_duplicate_user_email(): void
    {
        $admin = User::where('username', 'superadmin')->firstOrFail();

        User::factory()->create([
            'email' => 'existing.guardian@example.com',
        ]);

        $payload = [
            'name' => 'Duplicate Email Guardian',
            'relationship' => 'Mother',
            'phone' => '08033221100',
            'email' => 'existing.guardian@example.com',
            'create_user_account' => true,
        ];

        $response = $this->actingAs($admin)->post(route('people.guardians.store'), $payload);

        $response->assertSessionHasErrors(['email']);
    }
}
