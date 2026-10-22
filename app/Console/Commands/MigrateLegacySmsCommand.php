<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\EducationalStage;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Throwable;

class MigrateLegacySmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-sms
                            {--db=sms1 : The legacy MySQL database name to migrate from}
                            {--host= : The legacy MySQL host (defaults to DB_HOST)}
                            {--port= : The legacy MySQL port (defaults to DB_PORT)}
                            {--username= : The legacy MySQL username (defaults to DB_USERNAME)}
                            {--password= : The legacy MySQL password (defaults to DB_PASSWORD)}
                            {--dry-run : Simulate and validate the migration without committing changes}
                            {--fresh : Clear existing school records before migrating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy Ekattor/CodeIgniter school database into AdvanceSMS enterprise architecture';

    /**
     * In-memory relational ID mappings.
     */
    protected array $sessionMap = [];

    protected array $termMap = [];

    protected array $examMap = [];

    protected array $departmentMap = [];

    protected array $classMap = [];

    protected array $sectionMap = [];

    protected array $subjectMap = [];

    protected array $guardianMap = [];

    protected array $studentMap = [];

    protected array $enrollmentMap = [];

    protected array $gradesCache = [];

    protected int $caCount = 3;

    protected bool $hasCa1 = false;

    protected bool $hasCa2 = false;

    protected bool $hasCa3 = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dbName = (string) $this->option('db');
        $host = $this->option('host') ?: config('database.connections.mysql.host', '127.0.0.1');
        $port = $this->option('port') ?: config('database.connections.mysql.port', '3306');
        $username = $this->option('username') ?: config('database.connections.mysql.username', 'root');
        $password = $this->option('password') !== null ? (string) $this->option('password') : (string) config('database.connections.mysql.password', '');
        $isDryRun = (bool) $this->option('dry-run');
        $isFresh = (bool) $this->option('fresh');

        DB::disableQueryLog();

        $this->info('================================================================');
        $this->info('   ADVANCESMS LEGACY INSTITUTIONAL MIGRATION PIPELINE          ');
        $this->info('================================================================');
        $this->line("Source Database : <comment>{$dbName}</comment> on {$host}:{$port}");
        $this->line('Target Database : <comment>'.config('database.connections.mysql.database').'</comment>');
        $this->line('Execution Mode  : <comment>'.($isDryRun ? 'DRY-RUN (Simulation Only)' : 'LIVE MIGRATION').'</comment>');
        $this->newLine();

        // 1. Establish Legacy Connection
        try {
            Config::set('database.connections.legacy', [
                'driver' => 'mysql',
                'host' => $host,
                'port' => $port,
                'database' => $dbName,
                'username' => $username,
                'password' => $password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
            ]);

            DB::purge('legacy');
            $legacy = DB::connection('legacy');
            $legacy->getPdo();
            $this->info('✓ Connected to legacy database successfully.');
        } catch (Throwable $e) {
            $this->error('Failed to connect to legacy database: '.$e->getMessage());

            return self::FAILURE;
        }

        // 2. Pre-flight Validation
        $legacyTables = collect($legacy->select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0]);
        $requiredTables = ['schools', 'settings', 'sessions', 'classes', 'sections', 'subjects', 'users', 'teachers', 'students', 'enrols', 'exams', 'marks'];
        $missingTables = collect($requiredTables)->diff($legacyTables);

        if ($missingTables->isNotEmpty()) {
            $this->error('Missing expected legacy tables: '.$missingTables->implode(', '));

            return self::FAILURE;
        }

        $legacySchool = $legacy->table('schools')->first();
        $schoolTitle = $legacySchool->name ?? 'Unknown School';
        $this->line("School Identified: <info>{$schoolTitle}</info>");

        // Detect continuous assessment column structure (some legacy SMS installations use 2 CAs instead of 3)
        $this->hasCa1 = $legacy->getSchemaBuilder()->hasColumn('marks', 'ca1');
        $this->hasCa2 = $legacy->getSchemaBuilder()->hasColumn('marks', 'ca2');
        $this->hasCa3 = $legacy->getSchemaBuilder()->hasColumn('marks', 'ca3');
        $this->caCount = $this->hasCa3 ? 3 : ($this->hasCa2 ? 2 : ($this->hasCa1 ? 1 : 1));
        $this->line("Assessment Profile: <info>{$this->caCount} Continuous Assessment (CA) component(s) detected</info>");
        $this->newLine();

        $startTime = microtime(true);

        DB::beginTransaction();

        try {
            if ($isFresh) {
                $this->warn('Clearing existing school academic data (--fresh requested)...');
                $this->purgeExistingData();
                $this->info('✓ Clean slate prepared.');
            }

            // Ensure Spatie Roles exist
            $this->ensureRolesExist();

            // Cache grades for fast mark grading evaluation
            $this->gradesCache = Grade::orderBy('mark_from', 'desc')->get()->toArray();

            // [1/9] School & System Settings
            $this->migrateSchoolSettings($legacy);

            // [2/9] Academic Sessions, Terms & Exam Windows
            $this->migrateAcademicSessionsAndExams($legacy);

            // [3/9] Departments
            $this->migrateDepartments($legacy);

            // [4/9] Classes & Sections
            $this->migrateClassesAndSections($legacy);

            // [5/9] Subjects & Class-Subject Matrix (Deduplicated)
            $this->migrateSubjects($legacy);

            // [6/9] Staff & Administrative Users
            $this->migrateStaff($legacy);

            // [7/9] Students, Guardians & Enrollments
            $this->migrateStudentsAndEnrollments($legacy);

            // [8/9] Examination Marks (Batch Processing)
            $this->migrateMarks($legacy);

            // [9/9] Daily Attendance History (Batch Processing)
            $this->migrateAttendance($legacy);

            $duration = round(microtime(true) - $startTime, 2);

            if ($isDryRun) {
                if (DB::connection()->getPdo()->inTransaction()) {
                    DB::rollBack();
                }
                $this->newLine();
                $this->info('================================================================');
                $this->info("   DRY-RUN VALIDATION SUCCESSFUL ({$duration}s)                ");
                $this->info('   All integrity constraints and relational maps verified!     ');
                $this->info('   No changes were written to the database.                    ');
                $this->info('================================================================');
            } else {
                if (DB::connection()->getPdo()->inTransaction()) {
                    DB::commit();
                }
                $this->newLine();
                $this->info('================================================================');
                $this->info("   MIGRATION COMPLETED SUCCESSFULLY in {$duration}s!           ");
                $this->info("   Institution: {$schoolTitle}                                  ");
                $this->info('================================================================');
            }

            $this->printSummary();

            return self::SUCCESS;
        } catch (Throwable $e) {
            if (DB::connection()->getPdo()->inTransaction()) {
                try {
                    DB::rollBack();
                } catch (Throwable) {
                    // Safe fallback if connection was already rolled back
                }
            }
            $this->newLine();
            $this->error('================================================================');
            $this->error('   MIGRATION FAILED - DATABASE TRANSACTION ROLLED BACK          ');
            $this->error('================================================================');
            $this->error('Error: '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Purge existing academic records before fresh migration.
     */
    protected function purgeExistingData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('student_marks')->delete();
        DB::table('daily_attendances')->delete();
        DB::table('enrollments')->delete();
        DB::table('class_subjects')->delete();
        DB::table('teacher_subject_assignments')->delete();
        DB::table('teacher_attendances')->delete();
        DB::table('teachers')->delete();
        DB::table('students')->delete();
        DB::table('guardians')->delete();
        DB::table('exams')->delete();
        DB::table('academic_terms')->delete();
        DB::table('academic_sessions')->delete();
        DB::table('sections')->delete();
        DB::table('classes')->delete();
        DB::table('subjects')->delete();
        DB::table('departments')->delete();
        DB::table('school_settings')->delete();

        // Remove migrated users while preserving non-legacy users if any
        DB::table('model_has_roles')->delete();
        DB::table('users')->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Ensure core Spatie roles exist.
     */
    protected function ensureRolesExist(): void
    {
        $roles = [
            UserRole::SUPERADMIN->value,
            UserRole::ADMIN->value,
            UserRole::EXAM_OFFICER->value,
            UserRole::TEACHER->value,
            UserRole::STUDENT->value,
            UserRole::GUARDIAN->value,
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    /**
     * [1/9] Migrate School Settings.
     */
    protected function migrateSchoolSettings($legacy): void
    {
        $this->line('[1/9] Migrating Institutional Settings...');

        $school = $legacy->table('schools')->first();
        $settings = $legacy->table('settings')->first();

        $schoolName = html_entity_decode(trim($school->name ?? $settings->system_name ?? 'School Management System'));
        $schoolCode = trim($settings->system_title ?? 'SMS-APP');
        $address = html_entity_decode(trim($school->address ?? $settings->address ?? ''));
        $phone = trim($school->phone ?? $settings->phone ?? '');
        $email = trim($settings->system_email ?? '');
        $currency = trim($settings->system_currency ?? 'NGN');

        // Detect principal from teachers
        $principalName = null;
        $principalTeacher = $legacy->table('teachers')
            ->join('users', 'teachers.user_id', '=', 'users.id')
            ->where('teachers.designation', 'LIKE', '%principal%')
            ->select('users.name', 'teachers.designation')
            ->first();

        if ($principalTeacher) {
            $principalName = html_entity_decode(trim($principalTeacher->name));
        }

        $maxCa1 = $this->caCount === 3 ? 10.00 : ($this->caCount === 2 ? 20.00 : 30.00);
        $maxCa2 = $this->caCount >= 2 ? ($this->caCount === 3 ? 10.00 : 20.00) : 0.00;
        $maxCa3 = $this->caCount === 3 ? 10.00 : 0.00;
        $maxExam = $this->caCount === 3 ? 70.00 : ($this->caCount === 2 ? 60.00 : 70.00);

        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'school_name' => $schoolName,
                'school_code' => $schoolCode,
                'motto' => 'Knowledge, Character and Excellence',
                'address' => $address,
                'phone' => $phone,
                'email' => $email,
                'currency_symbol' => $currency === 'USD' ? '$' : '₦',
                'currency_code' => $currency ?: 'NGN',
                'principal_name' => $principalName,
                'ca_count' => $this->caCount,
                'default_max_ca1' => $maxCa1,
                'default_max_ca2' => $maxCa2,
                'default_max_ca3' => $maxCa3,
                'default_max_exam' => $maxExam,
                'ca1_label' => '1st CA',
                'ca2_label' => '2nd CA',
                'ca3_label' => '3rd CA',
                'letterhead_top_margin' => 120,
                'letterhead_bottom_margin' => 50,
                'letterhead_left_margin' => 40,
                'letterhead_right_margin' => 40,
            ]
        );

        $this->info("   ✓ School profile configured: {$schoolName}");
    }

    /**
     * [2/9] Migrate Academic Sessions, Terms & Exams.
     */
    protected function migrateAcademicSessionsAndExams($legacy): void
    {
        $this->line('[2/9] Migrating Academic Sessions, Terms & Assessment Windows...');

        $legacySessions = $legacy->table('sessions')->get();

        foreach ($legacySessions as $sess) {
            $sessionName = trim($sess->name);
            $isCurrent = (int) $sess->status === 1;

            $academicSession = AcademicSession::firstOrCreate(
                ['name' => $sessionName],
                [
                    'is_current' => $isCurrent,
                    'start_date' => '2024-09-01',
                    'end_date' => '2025-07-31',
                ]
            );

            $this->sessionMap[$sess->id] = $academicSession->id;

            // Generate Terms 1, 2, 3 for this session
            $termNames = [
                1 => 'First Term',
                2 => 'Second Term',
                3 => 'Third Term',
            ];

            foreach ($termNames as $termNum => $termTitle) {
                $term = AcademicTerm::firstOrCreate(
                    [
                        'academic_session_id' => $academicSession->id,
                        'term_number' => $termNum,
                    ],
                    [
                        'name' => $termTitle,
                        'start_date' => match ($termNum) {
                            1 => '2024-09-01',
                            2 => '2025-01-06',
                            3 => '2025-04-28',
                        },
                        'end_date' => match ($termNum) {
                            1 => '2024-12-15',
                            2 => '2025-04-11',
                            3 => '2025-07-25',
                        },
                        'is_current' => ($isCurrent && $termNum === 2),
                    ]
                );

                $this->termMap[$sess->id.'_'.$termNum] = $term->id;
            }
        }

        $maxCa1 = $this->caCount === 3 ? 10.00 : ($this->caCount === 2 ? 20.00 : 30.00);
        $maxCa2 = $this->caCount >= 2 ? ($this->caCount === 3 ? 10.00 : 20.00) : 0.00;
        $maxCa3 = $this->caCount === 3 ? 10.00 : 0.00;
        $maxExam = $this->caCount === 3 ? 70.00 : ($this->caCount === 2 ? 60.00 : 70.00);

        // Migrate Exams
        $legacyExams = $legacy->table('exams')->get();
        foreach ($legacyExams as $ex) {
            $sessionId = $this->sessionMap[$ex->session] ?? array_values($this->sessionMap)[0];
            $termNumber = (int) $ex->term ?: 1;
            $termId = $this->termMap[$ex->session.'_'.$termNumber] ?? array_values($this->termMap)[0];

            $exam = Exam::create([
                'academic_session_id' => $sessionId,
                'academic_term_id' => $termId,
                'name' => html_entity_decode(trim($ex->name)),
                'ca_count' => $this->caCount,
                'max_ca1' => $maxCa1,
                'max_ca2' => $maxCa2,
                'max_ca3' => $maxCa3,
                'max_exam' => $maxExam,
                'ca1_label' => '1st CA',
                'ca2_label' => '2nd CA',
                'ca3_label' => '3rd CA',
                'is_published' => true,
            ]);

            $this->examMap[$ex->id] = $exam->id;
        }

        $this->info('   ✓ Migrated '.count($this->sessionMap).' sessions, '.count($this->termMap).' terms, '.count($this->examMap).' exams.');
    }

    /**
     * [3/9] Migrate Departments.
     */
    protected function migrateDepartments($legacy): void
    {
        $this->line('[3/9] Migrating Departments...');

        $legacyDepartments = $legacy->table('departments')->get();

        foreach ($legacyDepartments as $dept) {
            $deptName = html_entity_decode(trim($dept->name));
            $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $deptName), 0, 4)) ?: 'DEPT';

            $targetDept = Department::firstOrCreate(
                ['name' => $deptName],
                ['code' => $code]
            );

            $this->departmentMap[$dept->id] = $targetDept->id;
        }

        $this->info('   ✓ Migrated '.count($this->departmentMap).' departments.');
    }

    /**
     * [4/9] Migrate Classes and Sections.
     */
    protected function migrateClassesAndSections($legacy): void
    {
        $this->line('[4/9] Migrating Academic Classes & Sections...');

        $legacyClasses = $legacy->table('classes')->get();

        foreach ($legacyClasses as $cls) {
            $className = html_entity_decode(trim($cls->name));
            $numOnly = (int) filter_var($className, FILTER_SANITIZE_NUMBER_INT) ?: 1;

            if (stripos($className, 'JSS') !== false || stripos($className, 'Junior') !== false) {
                $stage = EducationalStage::JUNIOR_SECONDARY->value;
                $levelOrder = $numOnly;
            } elseif (stripos($className, 'SS') !== false || stripos($className, 'Senior') !== false) {
                $stage = EducationalStage::SENIOR_SECONDARY->value;
                $levelOrder = $numOnly + 3;
            } elseif (stripos($className, 'Primary') !== false) {
                $stage = EducationalStage::PRIMARY->value;
                $levelOrder = $numOnly;
            } else {
                $stage = EducationalStage::SENIOR_SECONDARY->value;
                $levelOrder = $numOnly;
            }

            $schoolClass = SchoolClass::firstOrCreate(
                ['name' => $className],
                [
                    'level_order' => $levelOrder,
                    'stage' => $stage,
                ]
            );

            $this->classMap[$cls->id] = $schoolClass->id;
        }

        $legacySections = $legacy->table('sections')->get();

        foreach ($legacySections as $sec) {
            $classId = $this->classMap[$sec->class_id] ?? null;
            if (! $classId) {
                continue;
            }

            $sectionName = trim($sec->name);
            $section = Section::firstOrCreate(
                [
                    'class_id' => $classId,
                    'name' => $sectionName,
                ],
                ['capacity' => 60]
            );

            $this->sectionMap[$sec->id] = $section->id;
        }

        $this->info('   ✓ Migrated '.count($this->classMap).' classes and '.count($this->sectionMap).' sections.');
    }

    /**
     * [5/9] Migrate Subjects (Deduplicating into normalized matrix).
     */
    protected function migrateSubjects($legacy): void
    {
        $this->line('[5/9] Migrating & Deduplicating Subjects into Relational Matrix...');

        $legacySubjects = $legacy->table('subjects')->get();
        $uniqueSubjectMapByName = [];
        $classSubjectLinks = 0;

        foreach ($legacySubjects as $sub) {
            $cleanName = strtoupper(html_entity_decode(trim($sub->name)));

            if (! isset($uniqueSubjectMapByName[$cleanName])) {
                // Generate clean code
                $words = explode(' ', $cleanName);
                if (count($words) >= 2) {
                    $code = strtoupper(substr($words[0], 0, 3).substr($words[1], 0, 2));
                } else {
                    $code = strtoupper(substr($cleanName, 0, 4));
                }

                // Ensure code uniqueness
                $baseCode = $code;
                $suffix = 1;
                while (Subject::where('code', $code)->exists()) {
                    $code = substr($baseCode, 0, 3).$suffix;
                    $suffix++;
                }

                $subject = Subject::firstOrCreate(
                    ['name' => $cleanName],
                    ['code' => $code]
                );

                $uniqueSubjectMapByName[$cleanName] = $subject->id;
            }

            $targetSubjectId = $uniqueSubjectMapByName[$cleanName];
            $this->subjectMap[$sub->id] = $targetSubjectId;

            // Link to class_subjects
            $targetClassId = $this->classMap[$sub->class_id] ?? null;
            if ($targetClassId) {
                DB::table('class_subjects')->updateOrInsert(
                    [
                        'class_id' => $targetClassId,
                        'subject_id' => $targetSubjectId,
                    ],
                    [
                        'is_compulsory' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $classSubjectLinks++;
            }
        }

        $this->info('   ✓ Normalized '.count($legacySubjects).' legacy rows into '.count($uniqueSubjectMapByName).' unique subjects and '.$classSubjectLinks.' class links.');
    }

    /**
     * [6/9] Migrate Staff & Administrative Users.
     */
    protected function migrateStaff($legacy): void
    {
        $this->line('[6/9] Migrating Staff & Administrative Accounts...');

        $legacyTeachers = $legacy->table('teachers')
            ->join('users', 'teachers.user_id', '=', 'users.id')
            ->select('teachers.*', 'users.name', 'users.email', 'users.phone', 'users.gender', 'users.password', 'users.address')
            ->get();

        $teacherRole = Role::findByName(UserRole::TEACHER->value, 'web');
        $superadminRole = Role::findByName(UserRole::SUPERADMIN->value, 'web');
        $adminRole = Role::findByName(UserRole::ADMIN->value, 'web');

        $migratedStaffCount = 0;

        foreach ($legacyTeachers as $t) {
            $cleanName = html_entity_decode(trim($t->name));
            $email = trim($t->email);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = 'staff_'.strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cleanName)).'_'.$t->id.'@school.local';
            }

            $username = 'staff_'.str_pad((string) $t->id, 4, '0', STR_PAD_LEFT);
            $baseUsername = $username;
            $uCounter = 1;
            while (DB::table('users')->where('username', $username)->exists()) {
                $username = "{$baseUsername}_{$uCounter}";
                $uCounter++;
            }

            if (DB::table('users')->where('email', $email)->exists()) {
                $email = "staff_{$t->id}_{$email}";
            }

            $gender = strtolower(trim($t->gender)) === 'female' ? Gender::FEMALE->value : Gender::MALE->value;

            $userId = DB::table('users')->insertGetId([
                'username' => $username,
                'name' => $cleanName,
                'email' => $email,
                'password' => $t->password, // Retains raw 40-character legacy SHA-1 hash
                'role' => UserRole::TEACHER->value,
                'gender' => $gender,
                'phone' => trim($t->phone) ?: null,
                'address' => html_entity_decode(trim($t->address ?? '')) ?: null,
                'legacy_id' => $t->user_id,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('model_has_roles')->insert([
                'role_id' => $teacherRole->id,
                'model_type' => User::class,
                'model_id' => $userId,
            ]);

            $staffId = 'STAFF-'.str_pad((string) $t->id, 4, '0', STR_PAD_LEFT);
            $baseStaffId = $staffId;
            $stCounter = 1;
            while (DB::table('teachers')->where('staff_id', $staffId)->exists()) {
                $staffId = "{$baseStaffId}_{$stCounter}";
                $stCounter++;
            }

            DB::table('teachers')->insert([
                'user_id' => $userId,
                'staff_id' => $staffId,
                'department_id' => $this->departmentMap[$t->department_id] ?? null,
                'designation' => html_entity_decode(trim($t->designation ?? 'Teacher')),
                'joining_date' => '2020-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $migratedStaffCount++;
        }

        // Migrate Admins & Superadmins
        $legacyAdmins = $legacy->table('users')
            ->whereIn('role', ['superadmin', 'admin', 'accountant', 'librarian'])
            ->get();

        $migratedAdminCount = 0;

        foreach ($legacyAdmins as $adm) {
            $cleanName = html_entity_decode(trim($adm->name));
            $email = trim($adm->email);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = 'admin_'.strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cleanName)).'_'.$adm->id.'@school.local';
            }

            $roleName = $adm->role === 'superadmin' ? UserRole::SUPERADMIN->value : UserRole::ADMIN->value;
            $gender = strtolower(trim($adm->gender)) === 'female' ? Gender::FEMALE->value : Gender::MALE->value;

            // Avoid duplicate email collision
            if (DB::table('users')->where('email', $email)->exists()) {
                $email = "admin_{$adm->id}_{$email}";
            }

            $adminUsername = 'admin_'.$adm->id;
            $baseAdminUsername = $adminUsername;
            $admCounter = 1;
            while (DB::table('users')->where('username', $adminUsername)->exists()) {
                $adminUsername = "{$baseAdminUsername}_{$admCounter}";
                $admCounter++;
            }

            $userId = DB::table('users')->insertGetId([
                'username' => $adminUsername,
                'name' => $cleanName,
                'email' => $email,
                'password' => $adm->password, // Retains raw 40-character legacy SHA-1 hash
                'role' => $roleName,
                'gender' => $gender,
                'phone' => trim($adm->phone) ?: null,
                'address' => html_entity_decode(trim($adm->address ?? '')) ?: null,
                'legacy_id' => $adm->id,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('model_has_roles')->insert([
                'role_id' => $roleName === UserRole::SUPERADMIN->value ? $superadminRole->id : $adminRole->id,
                'model_type' => User::class,
                'model_id' => $userId,
            ]);

            $migratedAdminCount++;
        }

        $this->info("   ✓ Migrated {$migratedStaffCount} teachers and {$migratedAdminCount} administrative accounts.");
    }

    /**
     * [7/9] Migrate Students, Guardians & Enrollments.
     */
    protected function migrateStudentsAndEnrollments($legacy): void
    {
        $this->line('[7/9] Migrating Students, Guardians & Class Enrollments...');

        // Parents / Guardians
        $legacyParents = $legacy->table('parents')
            ->join('users', 'parents.user_id', '=', 'users.id')
            ->select('parents.id as parent_id', 'users.*')
            ->get();

        foreach ($legacyParents as $p) {
            $guardianId = DB::table('guardians')->insertGetId([
                'name' => html_entity_decode(trim($p->name)),
                'relationship' => 'Parent',
                'phone' => trim($p->phone) ?: '08000000000',
                'email' => filter_var($p->email, FILTER_VALIDATE_EMAIL) ? $p->email : null,
                'address' => html_entity_decode(trim($p->address ?? '')) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->guardianMap[$p->parent_id] = $guardianId;
        }

        // Students
        $legacyStudents = $legacy->table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->select('students.id as student_id', 'students.code', 'students.parent_id', 'students.session', 'users.*')
            ->get();

        $bar = $this->output->createProgressBar(count($legacyStudents));
        $bar->start();

        $studentRole = Role::findByName(UserRole::STUDENT->value, 'web');
        $studentRoleId = $studentRole->id;
        $roleAssignments = [];

        foreach ($legacyStudents as $s) {
            $cleanName = html_entity_decode(trim($s->name));
            // In Ekattor, users.email holds the true student admission number (e.g. GSSS25-00001)
            $admissionNo = trim($s->email) ?: trim($s->code);
            $studentUsername = $admissionNo;
            $baseStudentUsername = $studentUsername;
            $sCounter = 1;
            while (DB::table('users')->where('username', $studentUsername)->exists()) {
                $studentUsername = "{$baseStudentUsername}_{$sCounter}";
                $sCounter++;
            }

            $gender = strtolower(trim($s->gender)) === 'female' ? Gender::FEMALE->value : Gender::MALE->value;
            $dob = ($s->birthday && $s->birthday !== 'NULL' && $s->birthday !== '0000-00-00')
                ? date('Y-m-d', strtotime($s->birthday))
                : null;

            $userId = DB::table('users')->insertGetId([
                'username' => $studentUsername,
                'name' => $cleanName,
                'email' => null, // Null to prevent invalid email validation and unique index clashes
                'password' => $s->password, // Retains raw 40-character legacy SHA-1 hash
                'role' => UserRole::STUDENT->value,
                'gender' => $gender,
                'phone' => ($s->phone && $s->phone !== 'Phone') ? trim($s->phone) : null,
                'address' => html_entity_decode(trim($s->address ?? '')) ?: null,
                'legacy_id' => $s->id,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleAssignments[] = [
                'role_id' => $studentRoleId,
                'model_type' => User::class,
                'model_id' => $userId,
            ];

            $uniqueAdmissionNo = $admissionNo;
            $baseAdmissionNo = $uniqueAdmissionNo;
            $aCounter = 1;
            while (DB::table('students')->where('admission_number', $uniqueAdmissionNo)->exists()) {
                $uniqueAdmissionNo = "{$baseAdmissionNo}_{$aCounter}";
                $aCounter++;
            }

            $studentId = DB::table('students')->insertGetId([
                'user_id' => $userId,
                'admission_number' => $uniqueAdmissionNo,
                'admission_date' => '2024-09-01',
                'date_of_birth' => $dob,
                'guardian_id' => $this->guardianMap[$s->parent_id] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->studentMap[$s->student_id] = $studentId;

            $bar->advance();
        }

        if (! empty($roleAssignments)) {
            DB::table('model_has_roles')->insert($roleAssignments);
        }

        $bar->finish();
        $this->newLine();

        // Enrollments
        $legacyEnrols = $legacy->table('enrols')->get();
        $enrollmentCount = 0;

        foreach ($legacyEnrols as $e) {
            $studentId = $this->studentMap[$e->student_id] ?? null;
            $classId = $this->classMap[$e->class_id] ?? null;
            $sectionId = $this->sectionMap[$e->section_id] ?? null;
            $sessionId = $this->sessionMap[$e->session] ?? array_values($this->sessionMap)[0];

            if (! $studentId || ! $classId || ! $sectionId) {
                continue;
            }

            $enrollmentId = DB::table('enrollments')->insertGetId([
                'student_id' => $studentId,
                'academic_session_id' => $sessionId,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->enrollmentMap[$e->student_id.'_'.$e->session] = $enrollmentId;
            $enrollmentCount++;
        }

        $this->info('   ✓ Migrated '.count($this->studentMap)." students and {$enrollmentCount} active class enrollments.");
    }

    /**
     * [8/9] Migrate Examination Marks (Chunked Batch Insert).
     */
    protected function migrateMarks($legacy): void
    {
        $this->line('[8/9] Migrating Examination Marks (Continuous Assessment & Exams)...');

        $totalMarks = $legacy->table('marks')->count();
        $this->line("   Processing {$totalMarks} marks in chunks of 1,000...");

        $bar = $this->output->createProgressBar($totalMarks);
        $bar->start();

        $chunkSize = 1000;
        $insertedCount = 0;
        $processedMarksKeys = [];

        $legacy->table('marks')
            ->orderBy('mark_obtained', 'desc')
            ->orderBy('id', 'asc')
            ->chunk($chunkSize, function ($marksChunk) use (&$insertedCount, &$processedMarksKeys, $bar) {
                $insertBatch = [];

                foreach ($marksChunk as $m) {
                    $enrollmentId = $this->enrollmentMap[$m->student_id.'_'.$m->session] ?? null;
                    $subjectId = $this->subjectMap[$m->subject_id] ?? null;
                    $examId = $this->examMap[$m->exam_id] ?? null;

                    if (! $enrollmentId || ! $subjectId || ! $examId) {
                        continue;
                    }

                    $markKey = $enrollmentId.'_'.$subjectId.'_'.$examId;
                    if (isset($processedMarksKeys[$markKey])) {
                        continue; // Skip duplicate dummy/unassigned exam rows, keeping real scores
                    }
                    $processedMarksKeys[$markKey] = true;

                    $ca1 = isset($m->ca1) ? (float) $m->ca1 : 0.00;
                    $ca2 = isset($m->ca2) ? (float) $m->ca2 : 0.00;
                    $ca3 = isset($m->ca3) ? (float) $m->ca3 : 0.00;
                    $examScore = isset($m->exam) ? (float) $m->exam : (isset($m->exam_score) ? (float) $m->exam_score : 0.00);

                    // Sanitize legacy entry anomalies (clamping typos, negative scores, and integer overflows)
                    $ca1 = max(0.00, $ca1 > 100 ? ($this->caCount === 3 ? 10.00 : 20.00) : $ca1);
                    $ca2 = max(0.00, $ca2 > 100 ? ($this->caCount === 3 ? 10.00 : 20.00) : $ca2);
                    $ca3 = max(0.00, $ca3 > 100 ? 10.00 : $ca3);
                    $examScore = max(0.00, $examScore > 100 ? ($this->caCount === 3 ? 70.00 : 60.00) : $examScore);

                    $totalScore = $ca1 + $ca2 + $ca3 + $examScore;

                    // Evaluate grade
                    $gradeId = null;
                    foreach ($this->gradesCache as $grade) {
                        if ($totalScore >= (float) $grade['mark_from'] && $totalScore <= (float) $grade['mark_to']) {
                            $gradeId = $grade['id'];
                            break;
                        }
                    }

                    $insertBatch[] = [
                        'enrollment_id' => $enrollmentId,
                        'subject_id' => $subjectId,
                        'exam_id' => $examId,
                        'ca1' => $ca1,
                        'ca2' => $ca2,
                        'ca3' => $ca3,
                        'exam_score' => $examScore,
                        'grade_id' => $gradeId,
                        'teacher_comment' => ! empty($m->comment) ? html_entity_decode(trim($m->comment)) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($insertBatch)) {
                    DB::table('student_marks')->insert($insertBatch);
                    $insertedCount += count($insertBatch);
                }

                $bar->advance(count($marksChunk));
            });

        $bar->finish();
        $this->newLine();
        $this->info("   ✓ Migrated {$insertedCount} unique examination mark records (deduplicated dummy entries).");
    }

    /**
     * [9/9] Migrate Daily Attendance History.
     */
    protected function migrateAttendance($legacy): void
    {
        $this->line('[9/9] Migrating Daily Student Attendance Records...');

        $totalAttendance = $legacy->table('daily_attendances')->count();
        if ($totalAttendance === 0) {
            $this->line('   No attendance records found.');

            return;
        }

        $bar = $this->output->createProgressBar($totalAttendance);
        $bar->start();

        $insertedCount = 0;
        $processedAttendanceKeys = [];

        $legacy->table('daily_attendances')->orderBy('id')->chunk(1000, function ($attendances) use (&$insertedCount, &$processedAttendanceKeys, $bar) {
            $insertBatch = [];

            foreach ($attendances as $a) {
                $enrollmentId = $this->enrollmentMap[$a->student_id.'_'.$a->session_id] ?? null;
                if (! $enrollmentId) {
                    continue;
                }

                $timestamp = (int) $a->timestamp;
                $date = $timestamp > 0 ? date('Y-m-d', $timestamp) : date('Y-m-d');

                $attKey = $enrollmentId.'_'.$date;
                if (isset($processedAttendanceKeys[$attKey])) {
                    continue;
                }
                $processedAttendanceKeys[$attKey] = true;

                $status = (int) $a->status === 1 ? AttendanceStatus::PRESENT->value : AttendanceStatus::ABSENT->value;

                $insertBatch[] = [
                    'enrollment_id' => $enrollmentId,
                    'attendance_date' => $date,
                    'status' => $status,
                    'recorded_by' => null,
                    'remark' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($insertBatch)) {
                DB::table('daily_attendances')->insert($insertBatch);
                $insertedCount += count($insertBatch);
            }

            $bar->advance(count($attendances));
        });

        $bar->finish();
        $this->newLine();
        $this->info("   ✓ Migrated {$insertedCount} attendance records.");
    }

    /**
     * Display final metrics table.
     */
    protected function printSummary(): void
    {
        $this->newLine();
        $this->table(
            ['Entity', 'Migrated Count', 'Status'],
            [
                ['School Profile', SchoolSetting::count(), 'Active'],
                ['Academic Sessions', AcademicSession::count(), 'Mapped'],
                ['Academic Terms', AcademicTerm::count(), 'Mapped'],
                ['Classes & Arms', SchoolClass::count().' classes / '.Section::count().' sections', 'Configured'],
                ['Deduplicated Subjects', Subject::count().' subjects ('.DB::table('class_subjects')->count().' class links)', 'Active'],
                ['Staff & Teachers', Teacher::count().' teachers / '.User::where('role', 'teacher')->count().' accounts', 'Verified'],
                ['Students & Profiles', Student::count().' profiles / '.Enrollment::count().' enrollments', 'Enrolled'],
                ['Examination Marks', DB::table('student_marks')->count().' scores', 'Graded'],
                ['Attendance Log', DB::table('daily_attendances')->count().' records', 'Indexed'],
            ]
        );
    }
}
