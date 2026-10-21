<?php

namespace Database\Seeders;

use App\Enums\EducationalStage;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Exam;
use App\Models\FrontendAnnouncement;
use App\Models\FrontendFaq;
use App\Models\FrontendSlider;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StandardAcademicSeeder extends Seeder
{
    /**
     * Run the academic database seeds.
     */
    public function run(): void
    {
        // 1. Roles & Permissions Initialization
        $this->call(RolePermissionSeeder::class);

        // 2. Institutional White-Label Settings & CMS Profile
        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'school_name' => 'YUM Academy',
                'school_code' => 'YUM-SA',
                'motto' => 'Knowledge, Character, and Scientific Excellence',
                'address' => 'Zaria Road, Gusau, Zamfara State, Nigeria',
                'phone' => '+234 803 123 4567',
                'email' => 'admin@gsss.edu.ng',
                'currency_symbol' => '₦',
                'currency_code' => 'NGN',
                'principal_name' => 'Dr. Ibrahim Gusau',
                'about_text' => 'Established with a vision for academic rigor and moral uprightness, YUM Academy provides a holistic secondary education that equips students for global competitiveness in science, technology, arts, and leadership.',
                'mission' => 'To provide accessible, high-quality secondary education grounded in scientific curiosity, ethical leadership, and academic discipline.',
                'vision' => 'To be a premier institution recognized for producing innovative scholars, ethical innovators, and disciplined leaders.',
                'core_values' => ['Academic Excellence', 'Scientific Innovation', 'Integrity & Discipline', 'Community Leadership'],
                'principal_message' => 'Welcome to YUM Academy. Our committed faculty, robust curriculum, and state-of-the-art learning environments empower every young mind to realize their fullest potential.',
                'office_hours' => 'Mon - Fri: 8:00 AM - 3:30 PM',
                'social_links' => [
                    'facebook' => 'https://facebook.com',
                    'x_twitter' => 'https://x.com',
                    'instagram' => 'https://instagram.com',
                ],
            ]
        );

        // Minimal Public CMS Base Records
        FrontendSlider::updateOrCreate(
            ['title' => 'Inspiring Academic & Scientific Excellence'],
            [
                'subtitle' => 'Nurturing innovative minds, ethical character, and global leadership in Northern Nigeria.',
                'badge' => 'Welcome to YUM-SA',
                'primary_button_text' => 'Explore Academics',
                'primary_button_url' => '/academics',
                'secondary_button_text' => 'Contact Us',
                'secondary_button_url' => '/contact',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        FrontendAnnouncement::updateOrCreate(
            ['slug' => 'welcome-to-new-academic-session'],
            [
                'title' => 'Welcome to the New Academic Session',
                'category' => 'general',
                'summary' => 'Orientation schedule and commencement of lectures for all returning and newly admitted students.',
                'content' => "We are excited to welcome all students, teachers, and parents to the new academic term. Please ensure all registrations and class allocations are finalized before the close of the week.\n\nStudents are expected to be in full official uniform and adhere strictly to institutional routines.",
                'is_pinned' => true,
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        FrontendFaq::updateOrCreate(
            ['question' => 'How can prospective parents apply for admission?'],
            [
                'category' => 'Admissions',
                'answer' => 'Prospective candidates can obtain application forms at the administrative office during office hours (Mon - Fri: 8:00 AM - 3:30 PM) or download the syllabus and prospectus from the Academics page.',
                'sort_order' => 1,
                'is_published' => true,
            ]
        );

        // 3. Default Super Administrator
        $admin = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Administrator',
                'email' => 'admin@school.test',
                'password' => Hash::make('password'),
                'first_name' => 'System',
                'last_name' => 'Superadmin',
                'role' => UserRole::SUPERADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([UserRole::SUPERADMIN->value]);

        // 4. Academic Sessions & Terms
        $session = AcademicSession::updateOrCreate(
            ['name' => '2025/2026'],
            [
                'start_date' => '2025-09-01',
                'end_date' => '2026-07-31',
                'is_current' => true,
            ]
        );

        $term1 = AcademicTerm::updateOrCreate(
            ['academic_session_id' => $session->id, 'term_number' => 1],
            [
                'name' => 'First Term',
                'start_date' => '2025-09-08',
                'end_date' => '2025-12-19',
                'is_current' => false,
            ]
        );

        $term2 = AcademicTerm::updateOrCreate(
            ['academic_session_id' => $session->id, 'term_number' => 2],
            [
                'name' => 'Second Term',
                'start_date' => '2026-01-05',
                'end_date' => '2026-04-03',
                'is_current' => true,
            ]
        );

        $term3 = AcademicTerm::updateOrCreate(
            ['academic_session_id' => $session->id, 'term_number' => 3],
            [
                'name' => 'Third Term',
                'start_date' => '2026-04-27',
                'end_date' => '2026-07-24',
                'is_current' => false,
            ]
        );

        // 4. Academic Departments
        $deptSciences = Department::updateOrCreate(
            ['name' => 'Sciences'],
            ['description' => 'Physics, Chemistry, Biology, Mathematics and Agricultural Sciences']
        );
        $deptArts = Department::updateOrCreate(
            ['name' => 'Arts & Humanities'],
            ['description' => 'History, Civic Education, Government, and Religious Studies']
        );
        $deptLanguages = Department::updateOrCreate(
            ['name' => 'Languages'],
            ['description' => 'English Language, Hausa, Literature in English, and Arabic']
        );
        $deptVocational = Department::updateOrCreate(
            ['name' => 'Vocational & Technical'],
            ['description' => 'Basic Technology, Computer Studies / ICT, and Technical Drawing']
        );
        $deptCommercial = Department::updateOrCreate(
            ['name' => 'Commercial Studies'],
            ['description' => 'Economics, Commerce, Financial Accounting, and Business Studies']
        );

        // 5. Classes and Sections
        $classConfigs = [
            ['name' => 'JSS 1', 'level' => 1, 'stage' => EducationalStage::JUNIOR_SECONDARY->value],
            ['name' => 'JSS 2', 'level' => 2, 'stage' => EducationalStage::JUNIOR_SECONDARY->value],
            ['name' => 'JSS 3', 'level' => 3, 'stage' => EducationalStage::JUNIOR_SECONDARY->value],
            ['name' => 'SS 1', 'level' => 4, 'stage' => EducationalStage::SENIOR_SECONDARY->value],
            ['name' => 'SS 2', 'level' => 5, 'stage' => EducationalStage::SENIOR_SECONDARY->value],
            ['name' => 'SS 3', 'level' => 6, 'stage' => EducationalStage::SENIOR_SECONDARY->value],
        ];

        $classesMap = [];
        foreach ($classConfigs as $cfg) {
            $class = SchoolClass::updateOrCreate(
                ['name' => $cfg['name']],
                [
                    'level_order' => $cfg['level'],
                    'stage' => $cfg['stage'],
                ]
            );
            $classesMap[$cfg['name']] = $class;

            foreach (['A', 'B', 'C', 'D'] as $sectionName) {
                Section::updateOrCreate(
                    ['class_id' => $class->id, 'name' => $sectionName],
                    ['capacity' => 60]
                );
            }
        }

        // 6. Master Subjects Catalog
        $subjectsData = [
            ['code' => 'MTH', 'name' => 'Mathematics', 'dept' => $deptSciences->id, 'scope' => 'all'],
            ['code' => 'ENG', 'name' => 'English Language', 'dept' => $deptLanguages->id, 'scope' => 'all'],
            ['code' => 'CIV', 'name' => 'Civic Education', 'dept' => $deptArts->id, 'scope' => 'all'],
            ['code' => 'ICT', 'name' => 'Computer Studies (ICT)', 'dept' => $deptVocational->id, 'scope' => 'all'],
            ['code' => 'HAU', 'name' => 'Hausa Language', 'dept' => $deptLanguages->id, 'scope' => 'all'],
            ['code' => 'IRS', 'name' => 'Islamic Studies', 'dept' => $deptArts->id, 'scope' => 'all'],

            // Junior Only
            ['code' => 'BSC', 'name' => 'Basic Science', 'dept' => $deptSciences->id, 'scope' => 'junior'],
            ['code' => 'BTECH', 'name' => 'Basic Technology', 'dept' => $deptVocational->id, 'scope' => 'junior'],
            ['code' => 'BUS', 'name' => 'Business Studies', 'dept' => $deptCommercial->id, 'scope' => 'junior'],
            ['code' => 'AGR', 'name' => 'Agricultural Science', 'dept' => $deptSciences->id, 'scope' => 'junior'],

            // Senior Only
            ['code' => 'PHY', 'name' => 'Physics', 'dept' => $deptSciences->id, 'scope' => 'senior'],
            ['code' => 'CHM', 'name' => 'Chemistry', 'dept' => $deptSciences->id, 'scope' => 'senior'],
            ['code' => 'BIO', 'name' => 'Biology', 'dept' => $deptSciences->id, 'scope' => 'senior'],
            ['code' => 'ECO', 'name' => 'Economics', 'dept' => $deptCommercial->id, 'scope' => 'senior'],
            ['code' => 'GEO', 'name' => 'Geography', 'dept' => $deptArts->id, 'scope' => 'senior'],
            ['code' => 'GOV', 'name' => 'Government', 'dept' => $deptArts->id, 'scope' => 'senior'],
            ['code' => 'LIT', 'name' => 'Literature in English', 'dept' => $deptLanguages->id, 'scope' => 'senior'],
        ];

        foreach ($subjectsData as $s) {
            $subject = Subject::updateOrCreate(
                ['code' => $s['code']],
                [
                    'name' => $s['name'],
                    'department_id' => $s['dept'],
                ]
            );

            // Link to classes
            foreach ($classesMap as $className => $class) {
                $isJunior = in_array($className, ['JSS 1', 'JSS 2', 'JSS 3']);
                $shouldAttach = false;

                if ($s['scope'] === 'all') {
                    $shouldAttach = true;
                } elseif ($s['scope'] === 'junior' && $isJunior) {
                    $shouldAttach = true;
                } elseif ($s['scope'] === 'senior' && ! $isJunior) {
                    $shouldAttach = true;
                }

                if ($shouldAttach) {
                    $class->subjects()->syncWithoutDetaching([$subject->id => ['is_compulsory' => true]]);
                }
            }
        }

        // 7. Standard WAEC/NECO Grading Scale
        $grades = [
            ['name' => 'A', 'grade_point' => 5.0, 'mark_from' => 70.00, 'mark_to' => 100.00, 'remark' => 'Excellent'],
            ['name' => 'B', 'grade_point' => 4.0, 'mark_from' => 60.00, 'mark_to' => 69.99, 'remark' => 'Very Good'],
            ['name' => 'C', 'grade_point' => 3.0, 'mark_from' => 50.00, 'mark_to' => 59.99, 'remark' => 'Good'],
            ['name' => 'D', 'grade_point' => 2.0, 'mark_from' => 40.00, 'mark_to' => 49.99, 'remark' => 'Pass'],
            ['name' => 'E', 'grade_point' => 1.0, 'mark_from' => 35.00, 'mark_to' => 39.99, 'remark' => 'Fair'],
            ['name' => 'F', 'grade_point' => 0.0, 'mark_from' => 0.00, 'mark_to' => 34.99, 'remark' => 'Fail'],
        ];

        foreach ($grades as $g) {
            Grade::updateOrCreate(
                ['name' => $g['name']],
                [
                    'grade_point' => $g['grade_point'],
                    'mark_from' => $g['mark_from'],
                    'mark_to' => $g['mark_to'],
                    'remark' => $g['remark'],
                ]
            );
        }

        // 8. Default Examination
        Exam::updateOrCreate(
            ['academic_session_id' => $session->id, 'academic_term_id' => $term2->id, 'name' => 'Second Term Examination 2025/2026'],
            [
                'max_ca1' => 10.00,
                'max_ca2' => 10.00,
                'max_ca3' => 10.00,
                'max_exam' => 70.00,
                'is_published' => false,
            ]
        );
    }
}
