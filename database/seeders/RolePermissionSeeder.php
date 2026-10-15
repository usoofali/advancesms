<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /**
         * Comprehensive catalog of permissions mapped to system models.
         * Every permission includes a dot-notation identifier, human-readable display name,
         * domain module group, and a detailed functional description.
         *
         * @var array<int, array{name: string, display_name: string, group: string, description: string}>
         */
        $permissionDefinitions = [
            // -------------------------------------------------------------------------
            // 1. Institutional Settings (SchoolSetting)
            // -------------------------------------------------------------------------
            [
                'name' => 'school_settings.view',
                'display_name' => 'View Institutional Profile',
                'group' => 'Institutional Settings',
                'description' => 'Allows viewing the institution name, address, contact details, currency, and branding configuration.',
            ],
            [
                'name' => 'school_settings.update',
                'display_name' => 'Update Institutional Profile',
                'group' => 'Institutional Settings',
                'description' => 'Allows editing school information, uploading official logos, badges, watermarks, and principal signatures.',
            ],

            // -------------------------------------------------------------------------
            // 2. Academic Sessions (AcademicSession)
            // -------------------------------------------------------------------------
            [
                'name' => 'academic_sessions.view',
                'display_name' => 'View Academic Sessions',
                'group' => 'Academic Calendar',
                'description' => 'Allows viewing the list of academic years/sessions (e.g. 2025/2026) and their status.',
            ],
            [
                'name' => 'academic_sessions.create',
                'display_name' => 'Create Academic Session',
                'group' => 'Academic Calendar',
                'description' => 'Allows defining and initializing new academic sessions with term structures.',
            ],
            [
                'name' => 'academic_sessions.update',
                'display_name' => 'Update Academic Session',
                'group' => 'Academic Calendar',
                'description' => 'Allows editing academic session names, start dates, and conclusion dates.',
            ],
            [
                'name' => 'academic_sessions.delete',
                'display_name' => 'Delete Academic Session',
                'group' => 'Academic Calendar',
                'description' => 'Allows removing academic sessions that do not contain dependent student enrollment history.',
            ],
            [
                'name' => 'academic_sessions.set_current',
                'display_name' => 'Activate Academic Session',
                'group' => 'Academic Calendar',
                'description' => 'Allows switching the active system-wide academic year for all school operations.',
            ],

            // -------------------------------------------------------------------------
            // 3. Academic Terms (AcademicTerm)
            // -------------------------------------------------------------------------
            [
                'name' => 'academic_terms.view',
                'display_name' => 'View Academic Terms',
                'group' => 'Academic Calendar',
                'description' => 'Allows viewing academic terms (First Term, Second Term, Third Term) and their dates.',
            ],
            [
                'name' => 'academic_terms.create',
                'display_name' => 'Create Academic Term',
                'group' => 'Academic Calendar',
                'description' => 'Allows creating academic terms within an existing academic session.',
            ],
            [
                'name' => 'academic_terms.update',
                'display_name' => 'Update Academic Term',
                'group' => 'Academic Calendar',
                'description' => 'Allows modifying term schedules, opening dates, and closing dates.',
            ],
            [
                'name' => 'academic_terms.delete',
                'display_name' => 'Delete Academic Term',
                'group' => 'Academic Calendar',
                'description' => 'Allows deleting academic terms from the calendar.',
            ],
            [
                'name' => 'academic_terms.set_current',
                'display_name' => 'Activate Academic Term',
                'group' => 'Academic Calendar',
                'description' => 'Allows setting the active term for marks recording, broadsheets, and attendance.',
            ],

            // -------------------------------------------------------------------------
            // 4. Academic Departments (Department)
            // -------------------------------------------------------------------------
            [
                'name' => 'departments.view',
                'display_name' => 'View Departments',
                'group' => 'Departments',
                'description' => 'Allows viewing academic departments (e.g. Sciences, Arts, Languages, Commercial, Vocational).',
            ],
            [
                'name' => 'departments.create',
                'display_name' => 'Create Department',
                'group' => 'Departments',
                'description' => 'Allows adding new academic departments to the institutional directory.',
            ],
            [
                'name' => 'departments.update',
                'display_name' => 'Update Department',
                'group' => 'Departments',
                'description' => 'Allows updating department titles, codes, and descriptions.',
            ],
            [
                'name' => 'departments.delete',
                'display_name' => 'Delete Department',
                'group' => 'Departments',
                'description' => 'Allows deleting academic departments that have no assigned teachers or subjects.',
            ],

            // -------------------------------------------------------------------------
            // 5. Classes (SchoolClass)
            // -------------------------------------------------------------------------
            [
                'name' => 'classes.view',
                'display_name' => 'View Classes',
                'group' => 'Classes & Sections',
                'description' => 'Allows viewing all secondary school classes (JSS 1 through SS 3) and stages.',
            ],
            [
                'name' => 'classes.create',
                'display_name' => 'Create Class',
                'group' => 'Classes & Sections',
                'description' => 'Allows creating new classes and assigning them to junior or senior secondary stages.',
            ],
            [
                'name' => 'classes.update',
                'display_name' => 'Update Class',
                'group' => 'Classes & Sections',
                'description' => 'Allows modifying class names, levels, and numeric ordering.',
            ],
            [
                'name' => 'classes.delete',
                'display_name' => 'Delete Class',
                'group' => 'Classes & Sections',
                'description' => 'Allows removing classes that have no active sections or students enrolled.',
            ],

            // -------------------------------------------------------------------------
            // 6. Sections / Arms (Section)
            // -------------------------------------------------------------------------
            [
                'name' => 'sections.view',
                'display_name' => 'View Sections (Arms)',
                'group' => 'Classes & Sections',
                'description' => 'Allows viewing class arms (e.g. Arm A, B, C, D) and student capacity limits.',
            ],
            [
                'name' => 'sections.create',
                'display_name' => 'Create Section (Arm)',
                'group' => 'Classes & Sections',
                'description' => 'Allows creating new class arms and assigning maximum student seat capacities.',
            ],
            [
                'name' => 'sections.update',
                'display_name' => 'Update Section (Arm)',
                'group' => 'Classes & Sections',
                'description' => 'Allows changing section names, room numbers, and capacity limits.',
            ],
            [
                'name' => 'sections.delete',
                'display_name' => 'Delete Section (Arm)',
                'group' => 'Classes & Sections',
                'description' => 'Allows removing class arms that contain no active student enrollments.',
            ],

            // -------------------------------------------------------------------------
            // 7. Master Subjects (Subject)
            // -------------------------------------------------------------------------
            [
                'name' => 'subjects.view',
                'display_name' => 'View Subjects Catalog',
                'group' => 'Curriculum & Subjects',
                'description' => 'Allows viewing the centralized master subject catalog and subject codes.',
            ],
            [
                'name' => 'subjects.create',
                'display_name' => 'Create Subject',
                'group' => 'Curriculum & Subjects',
                'description' => 'Allows adding new subjects to the master catalog (e.g. Mathematics, English Language, Physics).',
            ],
            [
                'name' => 'subjects.update',
                'display_name' => 'Update Subject',
                'group' => 'Curriculum & Subjects',
                'description' => 'Allows editing subject names, codes, and department categorizations.',
            ],
            [
                'name' => 'subjects.delete',
                'display_name' => 'Delete Subject',
                'group' => 'Curriculum & Subjects',
                'description' => 'Allows deleting subjects from the catalog when not assigned to class curriculums.',
            ],

            // -------------------------------------------------------------------------
            // 8. Class Curriculum Allocation (ClassSubject)
            // -------------------------------------------------------------------------
            [
                'name' => 'class_subjects.view',
                'display_name' => 'View Class Curriculum',
                'group' => 'Curriculum & Subjects',
                'description' => 'Allows viewing subjects assigned to specific classes (e.g. JSS 1 Curriculum).',
            ],
            [
                'name' => 'class_subjects.create',
                'display_name' => 'Allocate Subject to Class',
                'group' => 'Curriculum & Subjects',
                'description' => 'Allows assigning a master subject to a class curriculum with core/elective status.',
            ],
            [
                'name' => 'class_subjects.update',
                'display_name' => 'Update Class Subject Settings',
                'group' => 'Curriculum & Subjects',
                'description' => 'Allows toggling whether a subject is compulsory/elective and setting credit units.',
            ],
            [
                'name' => 'class_subjects.delete',
                'display_name' => 'Remove Subject from Class',
                'group' => 'Curriculum & Subjects',
                'description' => 'Allows removing a subject from a class curriculum.',
            ],

            // -------------------------------------------------------------------------
            // 9. Teachers (Teacher)
            // -------------------------------------------------------------------------
            [
                'name' => 'teachers.view',
                'display_name' => 'View Teachers Directory',
                'group' => 'Teachers & Staff',
                'description' => 'Allows viewing academic staff profiles, staff IDs, designations, and qualifications.',
            ],
            [
                'name' => 'teachers.create',
                'display_name' => 'Register Teacher',
                'group' => 'Teachers & Staff',
                'description' => 'Allows registering new teachers and generating their academic staff accounts.',
            ],
            [
                'name' => 'teachers.update',
                'display_name' => 'Update Teacher Profile',
                'group' => 'Teachers & Staff',
                'description' => 'Allows editing teacher details, qualifications, payroll staff IDs, and designations.',
            ],
            [
                'name' => 'teachers.delete',
                'display_name' => 'Delete Teacher',
                'group' => 'Teachers & Staff',
                'description' => 'Allows removing teacher profiles and archiving staff records.',
            ],

            // -------------------------------------------------------------------------
            // 10. Teacher Subject Assignments (TeacherSubjectAssignment)
            // -------------------------------------------------------------------------
            [
                'name' => 'teacher_subject_assignments.view',
                'display_name' => 'View Teacher Allocations',
                'group' => 'Teachers & Staff',
                'description' => 'Allows viewing which teachers are allocated to teach specific subjects in specific class sections.',
            ],
            [
                'name' => 'teacher_subject_assignments.create',
                'display_name' => 'Assign Teacher to Subject',
                'group' => 'Teachers & Staff',
                'description' => 'Allows assigning a teacher to teach a subject in an arm with mark entry and attendance privileges.',
            ],
            [
                'name' => 'teacher_subject_assignments.update',
                'display_name' => 'Update Teacher Allocation',
                'group' => 'Teachers & Staff',
                'description' => 'Allows modifying teacher permissions (can_input_marks, can_take_attendance) for an assignment.',
            ],
            [
                'name' => 'teacher_subject_assignments.delete',
                'display_name' => 'Revoke Teacher Allocation',
                'group' => 'Teachers & Staff',
                'description' => 'Allows unassigning a teacher from a subject and class section.',
            ],

            // -------------------------------------------------------------------------
            // 11. Students (Student)
            // -------------------------------------------------------------------------
            [
                'name' => 'students.view',
                'display_name' => 'View Students Directory',
                'group' => 'Students & Admissions',
                'description' => 'Allows viewing student bio-data, admission numbers, state of origin, and personal details.',
            ],
            [
                'name' => 'students.create',
                'display_name' => 'Register New Student',
                'group' => 'Students & Admissions',
                'description' => 'Allows registering newly admitted students and generating admission numbers.',
            ],
            [
                'name' => 'students.update',
                'display_name' => 'Update Student Profile',
                'group' => 'Students & Admissions',
                'description' => 'Allows editing student bio-data, passport photos, and guardian linkages.',
            ],
            [
                'name' => 'students.delete',
                'display_name' => 'Delete Student',
                'group' => 'Students & Admissions',
                'description' => 'Allows deleting student records from the institutional database.',
            ],

            // -------------------------------------------------------------------------
            // 12. Guardians (Guardian)
            // -------------------------------------------------------------------------
            [
                'name' => 'guardians.view',
                'display_name' => 'View Guardians',
                'group' => 'Students & Admissions',
                'description' => 'Allows viewing parent and guardian contact profiles and their linked children.',
            ],
            [
                'name' => 'guardians.create',
                'display_name' => 'Register Guardian',
                'group' => 'Students & Admissions',
                'description' => 'Allows registering parent/guardian accounts with occupation and contact details.',
            ],
            [
                'name' => 'guardians.update',
                'display_name' => 'Update Guardian Profile',
                'group' => 'Students & Admissions',
                'description' => 'Allows editing guardian phone numbers, residential addresses, and relationships.',
            ],
            [
                'name' => 'guardians.delete',
                'display_name' => 'Delete Guardian',
                'group' => 'Students & Admissions',
                'description' => 'Allows deleting guardian records from the database.',
            ],

            // -------------------------------------------------------------------------
            // 13. Student Enrollments & Promotions (Enrollment)
            // -------------------------------------------------------------------------
            [
                'name' => 'enrollments.view',
                'display_name' => 'View Class Enrollments',
                'group' => 'Enrollments & Promotion',
                'description' => 'Allows viewing students enrolled in each class and section per academic session.',
            ],
            [
                'name' => 'enrollments.create',
                'display_name' => 'Enroll Student in Class',
                'group' => 'Enrollments & Promotion',
                'description' => 'Allows enrolling students into a specific class, section, and session with a roll number.',
            ],
            [
                'name' => 'enrollments.update',
                'display_name' => 'Update Enrollment Status',
                'group' => 'Enrollments & Promotion',
                'description' => 'Allows transferring a student to a different section or updating status (active, repeated, withdrawn).',
            ],
            [
                'name' => 'enrollments.delete',
                'display_name' => 'Cancel Enrollment',
                'group' => 'Enrollments & Promotion',
                'description' => 'Allows removing a student enrollment record from an academic session.',
            ],
            [
                'name' => 'enrollments.promote',
                'display_name' => 'Execute Student Promotions',
                'group' => 'Enrollments & Promotion',
                'description' => 'Allows bulk promoting successful students to the next class level for a new academic year.',
            ],

            // -------------------------------------------------------------------------
            // 14. Daily Attendance (DailyAttendance)
            // -------------------------------------------------------------------------
            [
                'name' => 'daily_attendances.view',
                'display_name' => 'View Daily Attendance Register',
                'group' => 'Daily Attendance',
                'description' => 'Allows inspecting attendance records, daily summaries, and student absence statistics.',
            ],
            [
                'name' => 'daily_attendances.take',
                'display_name' => 'Record Daily Attendance',
                'group' => 'Daily Attendance',
                'description' => 'Allows form masters to mark daily morning attendance (Present, Absent, Late, Excused) for assigned sections.',
            ],
            [
                'name' => 'daily_attendances.update',
                'display_name' => 'Update Attendance Register',
                'group' => 'Daily Attendance',
                'description' => 'Allows amending previously submitted student attendance statuses and remarks.',
            ],
            [
                'name' => 'daily_attendances.delete',
                'display_name' => 'Delete Attendance Register',
                'group' => 'Daily Attendance',
                'description' => 'Allows deleting recorded attendance entries for a given date.',
            ],

            // -------------------------------------------------------------------------
            // 15. Examination & Assessment Criteria (Exam)
            // -------------------------------------------------------------------------
            [
                'name' => 'exams.view',
                'display_name' => 'View Examinations',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows viewing configured assessment periods and examination milestones.',
            ],
            [
                'name' => 'exams.create',
                'display_name' => 'Create Examination',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows setting up examination sessions with custom score distribution limits (CA1, CA2, CA3, Exam).',
            ],
            [
                'name' => 'exams.update',
                'display_name' => 'Update Examination',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows modifying examination score weighting and assessment parameters.',
            ],
            [
                'name' => 'exams.delete',
                'display_name' => 'Delete Examination',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows removing examination milestones that do not have recorded student marks.',
            ],
            [
                'name' => 'exams.publish',
                'display_name' => 'Publish Examination Results',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows releasing compiled terminal assessment results so students and guardians can view them.',
            ],

            // -------------------------------------------------------------------------
            // 16. Grading Scales (Grade)
            // -------------------------------------------------------------------------
            [
                'name' => 'grades.view',
                'display_name' => 'View Grading Scales',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows viewing the institutional letter grading scale thresholds (e.g. A: 70–100, B: 60–69).',
            ],
            [
                'name' => 'grades.create',
                'display_name' => 'Create Grade Tier',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows adding new grade letters, grade point values, and performance remarks.',
            ],
            [
                'name' => 'grades.update',
                'display_name' => 'Update Grade Tier',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows adjusting score ranges, letter identifiers, and teacher remarks.',
            ],
            [
                'name' => 'grades.delete',
                'display_name' => 'Delete Grade Tier',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows deleting grading scale intervals.',
            ],

            // -------------------------------------------------------------------------
            // 17. Continuous Assessment & Marks (StudentMark)
            // -------------------------------------------------------------------------
            [
                'name' => 'student_marks.view',
                'display_name' => 'View Student Marks',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows viewing continuous assessment (CA1, CA2, CA3) and final examination marks.',
            ],
            [
                'name' => 'student_marks.enter',
                'display_name' => 'Enter Assessment Marks',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows subject teachers to record scores via the reactive Excel-style mark entry spreadsheet.',
            ],
            [
                'name' => 'student_marks.update',
                'display_name' => 'Update Assessment Marks',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows teachers or examination officers to modify submitted assessment marks.',
            ],
            [
                'name' => 'student_marks.delete',
                'display_name' => 'Delete Student Marks',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows resetting or deleting recorded mark entries.',
            ],
            [
                'name' => 'student_marks.lock',
                'display_name' => 'Lock / Unlock Mark Entry',
                'group' => 'Continuous Assessment & Exams',
                'description' => 'Allows exam officers to freeze marks against further modifications once submission deadlines pass.',
            ],

            // -------------------------------------------------------------------------
            // 18. Broadsheets & Report Cards (Terminal Reports)
            // -------------------------------------------------------------------------
            [
                'name' => 'reports.broadsheet.view',
                'display_name' => 'View Master Broadsheet',
                'group' => 'Reports & Broadsheets',
                'description' => 'Allows viewing class and section broadsheets showing all student scores across all subjects.',
            ],
            [
                'name' => 'reports.broadsheet.generate',
                'display_name' => 'Generate Master Broadsheet',
                'group' => 'Reports & Broadsheets',
                'description' => 'Allows compiling, ranking, and exporting class-wide broadsheet performance sheets.',
            ],
            [
                'name' => 'reports.report_card.view',
                'display_name' => 'View Student Report Card',
                'group' => 'Reports & Broadsheets',
                'description' => 'Allows viewing digital terminal report cards with subject scores, positions, and remarks.',
            ],
            [
                'name' => 'reports.report_card.generate',
                'display_name' => 'Generate Branded Report Cards',
                'group' => 'Reports & Broadsheets',
                'description' => 'Allows compiling, generating, and printing official institutional PDF report cards with stamps.',
            ],

            // -------------------------------------------------------------------------
            // 19. User Accounts (User)
            // -------------------------------------------------------------------------
            [
                'name' => 'users.view',
                'display_name' => 'View User Accounts',
                'group' => 'Access & Security',
                'description' => 'Allows viewing system user accounts, login usernames, email addresses, and active statuses.',
            ],
            [
                'name' => 'users.create',
                'display_name' => 'Create User Account',
                'group' => 'Access & Security',
                'description' => 'Allows creating new user login accounts with credentials and security roles.',
            ],
            [
                'name' => 'users.update',
                'display_name' => 'Update User Account',
                'group' => 'Access & Security',
                'description' => 'Allows updating user profiles, resetting passwords, and toggling active access statuses.',
            ],
            [
                'name' => 'users.delete',
                'display_name' => 'Delete User Account',
                'group' => 'Access & Security',
                'description' => 'Allows permanently deleting or deactivating user accounts.',
            ],

            // -------------------------------------------------------------------------
            // 20. Roles & Permissions Management (Role, Permission)
            // -------------------------------------------------------------------------
            [
                'name' => 'roles.view',
                'display_name' => 'View System Roles',
                'group' => 'Access & Security',
                'description' => 'Allows inspecting dynamic user roles and reviewing their assigned capabilities.',
            ],
            [
                'name' => 'roles.create',
                'display_name' => 'Create Custom Role',
                'group' => 'Access & Security',
                'description' => 'Allows administrators to dynamically create new roles (e.g. Vice Principal, Discipline Master).',
            ],
            [
                'name' => 'roles.update',
                'display_name' => 'Update Role & Permissions',
                'group' => 'Access & Security',
                'description' => 'Allows editing role titles, descriptions, and updating granted permission checklists.',
            ],
            [
                'name' => 'roles.delete',
                'display_name' => 'Delete Role',
                'group' => 'Access & Security',
                'description' => 'Allows deleting custom user roles that have no active users assigned.',
            ],
            [
                'name' => 'roles.assign',
                'display_name' => 'Assign Roles to Users',
                'group' => 'Access & Security',
                'description' => 'Allows granting or revoking roles on staff, teachers, administrators, and users.',
            ],
            [
                'name' => 'permissions.view',
                'display_name' => 'View Permission Directory',
                'group' => 'Access & Security',
                'description' => 'Allows inspecting the complete directory of system permissions and operational descriptions.',
            ],
        ];

        // 1. Seed or Update all permissions with display names, groups, and descriptions
        foreach ($permissionDefinitions as $permData) {
            Permission::query()->updateOrCreate(
                ['name' => $permData['name'], 'guard_name' => 'web'],
                [
                    'display_name' => $permData['display_name'],
                    'group' => $permData['group'],
                    'description' => $permData['description'],
                ]
            );
        }

        // On production, only sync new permissions — do not overwrite role assignments
        // that may have been customized via the admin UI.
        if (app()->isProduction()) {
            return;
        }

        // -------------------------------------------------------------------------
        // 2. Initialize Core Roles with Descriptions
        // -------------------------------------------------------------------------

        // 1. Super Administrator (Full access across all system modules)
        $superAdmin = Role::query()->updateOrCreate(
            ['name' => UserRole::SUPERADMIN->value, 'guard_name' => 'web'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Full unrestricted system access, administrative controls, tenant configuration, and security management.',
                'is_system' => true,
            ]
        );
        $superAdmin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

        // 2. School Administrator / Principal
        $admin = Role::query()->updateOrCreate(
            ['name' => UserRole::ADMIN->value, 'guard_name' => 'web'],
            [
                'display_name' => 'School Administrator / Principal',
                'description' => 'Day-to-day institutional administrator managing academic hierarchy, curriculum, teachers, students, and enrollments.',
                'is_system' => true,
            ]
        );
        $admin->syncPermissions([
            'school_settings.view',
            'school_settings.update',
            'academic_sessions.view',
            'academic_sessions.create',
            'academic_sessions.update',
            'academic_sessions.set_current',
            'academic_terms.view',
            'academic_terms.create',
            'academic_terms.update',
            'academic_terms.set_current',
            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',
            'classes.view',
            'classes.create',
            'classes.update',
            'classes.delete',
            'sections.view',
            'sections.create',
            'sections.update',
            'sections.delete',
            'subjects.view',
            'subjects.create',
            'subjects.update',
            'subjects.delete',
            'class_subjects.view',
            'class_subjects.create',
            'class_subjects.update',
            'class_subjects.delete',
            'teachers.view',
            'teachers.create',
            'teachers.update',
            'teachers.delete',
            'teacher_subject_assignments.view',
            'teacher_subject_assignments.create',
            'teacher_subject_assignments.update',
            'teacher_subject_assignments.delete',
            'students.view',
            'students.create',
            'students.update',
            'students.delete',
            'guardians.view',
            'guardians.create',
            'guardians.update',
            'guardians.delete',
            'enrollments.view',
            'enrollments.create',
            'enrollments.update',
            'enrollments.delete',
            'enrollments.promote',
            'daily_attendances.view',
            'daily_attendances.take',
            'daily_attendances.update',
            'exams.view',
            'exams.create',
            'exams.update',
            'exams.publish',
            'grades.view',
            'grades.create',
            'grades.update',
            'student_marks.view',
            'student_marks.enter',
            'student_marks.update',
            'student_marks.lock',
            'reports.broadsheet.view',
            'reports.broadsheet.generate',
            'reports.report_card.view',
            'reports.report_card.generate',
            'users.view',
            'users.create',
            'users.update',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.assign',
            'permissions.view',
        ]);

        // 3. Examination Officer
        $examOfficer = Role::query()->updateOrCreate(
            ['name' => UserRole::EXAM_OFFICER->value, 'guard_name' => 'web'],
            [
                'display_name' => 'Examination Officer',
                'description' => 'Manages examination scheduling, grade scale calibrations, continuous assessment reviews, mark locking, and broadsheet compilation.',
                'is_system' => true,
            ]
        );
        $examOfficer->syncPermissions([
            'academic_sessions.view',
            'academic_terms.view',
            'classes.view',
            'sections.view',
            'departments.view',
            'subjects.view',
            'class_subjects.view',
            'students.view',
            'enrollments.view',
            'exams.view',
            'exams.create',
            'exams.update',
            'exams.delete',
            'exams.publish',
            'grades.view',
            'grades.create',
            'grades.update',
            'grades.delete',
            'student_marks.view',
            'student_marks.enter',
            'student_marks.update',
            'student_marks.delete',
            'student_marks.lock',
            'reports.broadsheet.view',
            'reports.broadsheet.generate',
            'reports.report_card.view',
            'reports.report_card.generate',
        ]);

        // 4. Teacher / Form Master
        $teacher = Role::query()->updateOrCreate(
            ['name' => UserRole::TEACHER->value, 'guard_name' => 'web'],
            [
                'display_name' => 'Teacher / Form Master',
                'description' => 'Assigned staff conducting daily student attendance registers and entering continuous assessment and examination marks for allocated subjects.',
                'is_system' => true,
            ]
        );
        $teacher->syncPermissions([
            'academic_sessions.view',
            'academic_terms.view',
            'classes.view',
            'sections.view',
            'subjects.view',
            'class_subjects.view',
            'teacher_subject_assignments.view',
            'students.view',
            'enrollments.view',
            'daily_attendances.view',
            'daily_attendances.take',
            'daily_attendances.update',
            'student_marks.view',
            'student_marks.enter',
            'student_marks.update',
            'reports.broadsheet.view',
            'reports.report_card.view',
        ]);

        // 5. Student
        $student = Role::query()->updateOrCreate(
            ['name' => UserRole::STUDENT->value, 'guard_name' => 'web'],
            [
                'display_name' => 'Student',
                'description' => 'Read-only student access to personal attendance statistics, assessment marks, terminal positions, and report cards.',
                'is_system' => true,
            ]
        );
        $student->syncPermissions([
            'daily_attendances.view',
            'student_marks.view',
            'reports.report_card.view',
        ]);

        // 6. Guardian / Parent
        $guardian = Role::query()->updateOrCreate(
            ['name' => UserRole::GUARDIAN->value, 'guard_name' => 'web'],
            [
                'display_name' => 'Guardian / Parent',
                'description' => 'Parental portal access to view linked children attendance, continuous assessment scores, and terminal report sheets.',
                'is_system' => true,
            ]
        );
        $guardian->syncPermissions([
            'daily_attendances.view',
            'student_marks.view',
            'reports.report_card.view',
        ]);
    }
}
