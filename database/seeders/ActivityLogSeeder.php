<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

final class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $student = Student::with('user')->first();

        if (! $admin) {
            return;
        }

        ActivityLog::query()->firstOrCreate(
            ['description' => "User '{$admin->name}' logged into administrative portal"],
            [
                'log_name' => 'auth',
                'causer_type' => User::class,
                'causer_id' => $admin->id,
                'event' => 'login',
                'properties' => ['context' => ['guard' => 'web']],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36',
                'url' => 'http://127.0.0.1:8000/login',
                'method' => 'POST',
                'created_at' => now()->subHours(8),
            ]
        );

        ActivityLog::query()->firstOrCreate(
            ['description' => "Created AcademicSession '2025/2026' (Current Active Session)"],
            [
                'log_name' => 'academic',
                'causer_type' => User::class,
                'causer_id' => $admin->id,
                'event' => 'created',
                'subject_type' => AcademicSession::class,
                'subject_id' => 1,
                'properties' => [
                    'attributes' => [
                        'name' => '2025/2026',
                        'is_current' => true,
                        'start_date' => '2025-09-01',
                        'end_date' => '2026-07-31',
                    ],
                ],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
                'url' => 'http://127.0.0.1:8000/academic/sessions',
                'method' => 'POST',
                'created_at' => now()->subHours(6),
            ]
        );

        if ($student) {
            ActivityLog::query()->firstOrCreate(
                ['description' => "Admitted new student '{$student->user->name}' ({$student->admission_number}) into JSS 1 (Section A)"],
                [
                    'log_name' => 'people',
                    'causer_type' => User::class,
                    'causer_id' => $admin->id,
                    'event' => 'created',
                    'subject_type' => Student::class,
                    'subject_id' => $student->id,
                    'properties' => [
                        'attributes' => [
                            'name' => $student->user->name,
                            'admission_number' => $student->admission_number,
                            'class' => 'JSS 1',
                            'section' => 'Section A',
                            'admission_date' => (string) $student->admission_date,
                        ],
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
                    'url' => 'http://127.0.0.1:8000/people/students',
                    'method' => 'POST',
                    'created_at' => now()->subHours(4),
                ]
            );

            ActivityLog::query()->firstOrCreate(
                ['description' => 'Executed promoted progression for 1 student(s) into JSS 2 (2026/2027)'],
                [
                    'log_name' => 'academic',
                    'causer_type' => User::class,
                    'causer_id' => $admin->id,
                    'event' => 'promoted',
                    'properties' => [
                        'context' => [
                            'action' => 'promote',
                            'source_session' => '2025/2026',
                            'target_session' => '2026/2027',
                            'source_class' => 'JSS 1',
                            'target_class' => 'JSS 2',
                            'target_section' => 'Section A',
                            'students_count' => 1,
                            'student_names' => [$student->user->name],
                        ],
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
                    'url' => 'http://127.0.0.1:8000/academic/promotions',
                    'method' => 'POST',
                    'created_at' => now()->subHours(2),
                ]
            );
        }
    }
}
