<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class InstallAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install
                            {--school-name= : The institutional name of the school}
                            {--admin-email= : The email for the superadmin account}
                            {--admin-password= : The password for the superadmin account}
                            {--academic-session= : The initial academic session (e.g. 2025/2026)}
                            {--force : Run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Turnkey automated installation and initialization wizard for new school instances';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  WHITE-LABEL SCHOOL MANAGEMENT SYSTEM INSTALLER    ');
        $this->info('====================================================');
        $this->newLine();

        // 1. Generate Application Key if not set
        if (empty(config('app.key'))) {
            $this->info('Generating application encryption key...');
            Artisan::call('key:generate', ['--force' => true]);
            $this->line(Artisan::output());
        }

        // 2. Link Storage
        $this->info('Linking storage directories...');
        Artisan::call('storage:link');
        $this->line(Artisan::output());

        // 3. Run Migrations
        $this->info('Executing database migrations...');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());

        // 4. Seed Standard Academic Data
        $this->info('Seeding standard academic structures, grading scales, and catalog...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\StandardAcademicSeeder',
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        // 5. Institutional & Admin Customization
        $schoolName = $this->option('school-name');
        if (! $schoolName && ! $this->option('no-interaction')) {
            $schoolName = $this->ask('Enter School Name', 'YUM Academy');
        } elseif (! $schoolName) {
            $schoolName = 'School Management System';
        }

        $academicSessionName = $this->option('academic-session');
        if (! $academicSessionName && ! $this->option('no-interaction')) {
            $academicSessionName = $this->ask('Enter Current Academic Session (e.g. 2025/2026)', '2025/2026');
        } elseif (! $academicSessionName) {
            $academicSessionName = '2025/2026';
        }

        $adminEmail = $this->option('admin-email');
        if (! $adminEmail && ! $this->option('no-interaction')) {
            $adminEmail = $this->ask('Enter Super Admin Email', 'admin@school.test');
        } elseif (! $adminEmail) {
            $adminEmail = 'admin@school.test';
        }

        $adminPassword = $this->option('admin-password');
        if (! $adminPassword && ! $this->option('no-interaction')) {
            $adminPassword = $this->secret('Enter Super Admin Password (leave blank for "password")') ?: 'password';
        } elseif (! $adminPassword) {
            $adminPassword = 'password';
        }

        // Apply institutional updates
        $setting = SchoolSetting::first();
        if ($setting) {
            $setting->update([
                'school_name' => $schoolName,
            ]);
        }

        // Update current session if name changed
        if ($academicSessionName !== '2025/2026') {
            $session = AcademicSession::where('is_current', true)->first();
            if ($session) {
                $session->update(['name' => $academicSessionName]);
            }
        }

        // Create or update Super Admin
        $admin = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Administrator',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'first_name' => 'System',
                'last_name' => 'Superadmin',
                'role' => UserRole::SUPERADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([UserRole::SUPERADMIN->value]);

        $this->newLine();
        $this->info('====================================================');
        $this->info('  INSTALLATION COMPLETED SUCCESSFULLY!             ');
        $this->info('====================================================');
        $this->info(" School:          {$schoolName}");
        $this->info(" Academic Year:   {$academicSessionName}");
        $this->info(' Admin Username:  superadmin');
        $this->info(" Admin Email:     {$adminEmail}");
        $this->info(' Ready to log in at /login');
        $this->newLine();

        return self::SUCCESS;
    }
}
