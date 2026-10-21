<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Award,
    BookOpen,
    CalendarCheck,
    CalendarDays,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    HeartHandshake,
    LayoutGrid,
    Library,
    PenTool,
    ShieldCheck,
    TrendingUp,
    UserCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as academicAllocations } from '@/routes/academic/allocations';
import { index as academicAttendance } from '@/routes/academic/attendance';
import { index as academicBroadsheet } from '@/routes/academic/broadsheet';
import { index as academicClasses } from '@/routes/academic/classes';
import { index as academicDepartments } from '@/routes/academic/departments';
import { index as academicMarks } from '@/routes/academic/marks';
import { index as academicPromotions } from '@/routes/academic/promotions';
import { index as academicReportCards } from '@/routes/academic/report-cards';
import { attendance as academicAttendanceReport } from '@/routes/academic/reports';
import { index as academicSessions } from '@/routes/academic/sessions';
import { index as academicSubjects } from '@/routes/academic/subjects';
import { index as adminActivityLogs } from '@/routes/admin/activity-logs';
import { index as adminRoles } from '@/routes/admin/roles';
import { index as peopleAlumni } from '@/routes/people/alumni';
import { index as peopleGuardians } from '@/routes/people/guardians';
import { index as peopleStudents } from '@/routes/people/students';
import { index as peopleTeachers } from '@/routes/people/teachers';
import { index as teacherAttendanceIndex, report as teacherAttendanceReport } from '@/routes/people/teachers/attendance';
import type { NavItem, NavSubItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const permissions = page.props.auth?.permissions as string[] | undefined;
    const roles = page.props.auth?.roles as string[] | undefined;
    const isSuperAdmin = permissions?.includes('*') || roles?.includes('superadmin');

    const can = (permission: string) => isSuperAdmin || permissions?.includes(permission);

    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
            color: 'text-indigo-500 dark:text-indigo-400',
        },
    ];

    // 1. Academics Management
    const academicItems: NavSubItem[] = [];
    if (can('academic_sessions.view')) {
        academicItems.push({
            title: 'Academic Sessions',
            href: academicSessions(),
            icon: CalendarDays,
            color: 'text-sky-500 dark:text-sky-400',
        });
    }
    if (can('classes.view')) {
        academicItems.push({
            title: 'Classes & Sections',
            href: academicClasses(),
            icon: GraduationCap,
            color: 'text-blue-600 dark:text-blue-400',
        });
    }
    if (can('departments.view')) {
        academicItems.push({
            title: 'Departments',
            href: academicDepartments(),
            icon: Library,
            color: 'text-purple-500 dark:text-purple-400',
        });
    }
    if (can('subjects.view')) {
        academicItems.push({
            title: 'Subjects',
            href: academicSubjects(),
            icon: BookOpen,
            color: 'text-amber-500 dark:text-amber-400',
        });
    }
    if (can('teacher_subject_assignments.view')) {
        academicItems.push({
            title: 'Teacher Allocations',
            href: academicAllocations(),
            icon: ClipboardList,
            color: 'text-orange-500 dark:text-orange-400',
        });
    }
    if (academicItems.length > 0) {
        items.push({
            title: 'Academics',
            icon: GraduationCap,
            color: 'text-blue-600 dark:text-blue-400',
            items: academicItems,
        });
    }

    // 2. Students & Learning
    const studentItems: NavSubItem[] = [];
    if (can('students.view')) {
        studentItems.push({
            title: 'Students & Enrollment',
            href: peopleStudents(),
            icon: UserCheck,
            color: 'text-teal-600 dark:text-teal-400',
        });
        studentItems.push({
            title: 'Alumni Directory',
            href: peopleAlumni(),
            icon: Award,
            color: 'text-purple-600 dark:text-purple-400',
        });
    }
    if (can('daily_attendances.view') || can('daily_attendances.take')) {
        studentItems.push({
            title: 'Daily Attendance',
            href: academicAttendance(),
            icon: CalendarCheck,
            color: 'text-green-600 dark:text-green-400',
        });
    }
    if (can('student_marks.view') || can('student_marks.enter')) {
        studentItems.push({
            title: 'Marks & Assessment',
            href: academicMarks(),
            icon: PenTool,
            color: 'text-rose-500 dark:text-rose-400',
        });
    }
    if (can('enrollments.promote')) {
        studentItems.push({
            title: 'Promotions',
            href: academicPromotions(),
            icon: TrendingUp,
            color: 'text-emerald-500 dark:text-emerald-400',
        });
    }
    if (studentItems.length > 0) {
        items.push({
            title: 'Students & Learning',
            icon: UserCheck,
            color: 'text-teal-600 dark:text-teal-400',
            items: studentItems,
        });
    }

    // 3. Academic Reports & Broadsheets
    const reportItems: NavSubItem[] = [];
    if (can('reports.broadsheet.view')) {
        reportItems.push({
            title: 'Result Broadsheet',
            href: academicBroadsheet(),
            icon: FileSpreadsheet,
            color: 'text-cyan-600 dark:text-cyan-400',
        });
    }
    if (can('reports.report_card.view')) {
        reportItems.push({
            title: 'Terminal Report Cards',
            href: academicReportCards(),
            icon: FileText,
            color: 'text-violet-500 dark:text-violet-400',
        });
    }
    if (can('daily_attendances.view')) {
        reportItems.push({
            title: 'Attendance Reports',
            href: academicAttendanceReport(),
            icon: CalendarCheck,
            color: 'text-amber-500 dark:text-amber-400',
        });
    }
    if (reportItems.length > 0) {
        items.push({
            title: 'Academic Reports',
            icon: FileSpreadsheet,
            color: 'text-cyan-600 dark:text-cyan-400',
            items: reportItems,
        });
    }

    // 4. Staff & Guardians
    const peopleItems: NavSubItem[] = [];
    if (can('teachers.view')) {
        peopleItems.push({
            title: 'Teachers & Staff',
            href: peopleTeachers(),
            icon: Users,
            color: 'text-blue-500 dark:text-blue-400',
        });
    }
    if (can('teacher_attendances.view') || can('teacher_attendances.take')) {
        peopleItems.push({
            title: 'Teacher Attendance Register',
            href: teacherAttendanceIndex(),
            icon: CalendarCheck,
            color: 'text-emerald-500 dark:text-emerald-400',
        });
    }
    if (can('teacher_attendances.view')) {
        peopleItems.push({
            title: 'Teacher Attendance Reports',
            href: teacherAttendanceReport(),
            icon: FileSpreadsheet,
            color: 'text-indigo-500 dark:text-indigo-400',
        });
    }
    if (can('guardians.view')) {
        peopleItems.push({
            title: 'Guardians Directory',
            href: peopleGuardians(),
            icon: HeartHandshake,
            color: 'text-pink-500 dark:text-pink-400',
        });
    }
    if (peopleItems.length > 0) {
        items.push({
            title: 'Staff & Guardians',
            icon: Users,
            color: 'text-pink-500 dark:text-pink-400',
            items: peopleItems,
        });
    }

    // 5. System Administration & Security
    const adminItems: NavSubItem[] = [];
    if (can('roles.view')) {
        adminItems.push({
            title: 'Roles & Permissions',
            href: adminRoles(),
            icon: ShieldCheck,
            color: 'text-red-500 dark:text-red-400',
        });
    }
    if (can('activity_logs.view')) {
        adminItems.push({
            title: 'Audit & Activity Logs',
            href: adminActivityLogs(),
            icon: Activity,
            color: 'text-amber-500 dark:text-amber-400',
        });
    }
    if (adminItems.length > 0) {
        items.push({
            title: 'System & Security',
            icon: ShieldCheck,
            color: 'text-red-500 dark:text-red-400',
            items: adminItems,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
