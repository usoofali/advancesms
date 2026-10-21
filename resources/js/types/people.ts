import type { AcademicSession, Department, SchoolClass, Section } from './academic';

export interface RoleSummary {
    id: number;
    name: string;
    display_name?: string | null;
}

export interface UserSummary {
    id: number;
    name: string;
    username: string | null;
    email: string | null;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    gender: 'male' | 'female';
    role?: string | null;
    roles?: RoleSummary[];
    is_active: boolean;
}

export interface Teacher {
    id: number;
    user_id: number;
    department_id: number | null;
    staff_id: string;
    designation: string | null;
    qualification: string | null;
    joining_date: string | null;
    user: UserSummary;
    department?: Department | null;
    subject_assignments_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface TeacherStats {
    total_staff: number;
    total_teachers: number;
    total_other_staff: number;
    active_staff: number;
    total_departments: number;
    total_assignments: number;
}

export interface GuardianSummary {
    id: number;
    name: string;
    phone: string;
    relationship: string;
}

export interface Guardian {
    id: number;
    user_id: number | null;
    name: string;
    relationship: string;
    phone: string;
    email: string | null;
    address: string | null;
    occupation: string | null;
    user?: UserSummary | null;
    students?: StudentSummary[];
    students_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface GuardianStats {
    total_guardians: number;
    total_linked_students: number;
    portal_active_guardians: number;
}

export interface EnrollmentSummary {
    id: number;
    student_id: number;
    academic_session_id: number;
    previous_enrollment_id?: number | null;
    class_id: number;
    section_id: number;
    roll_number: string | null;
    status: 'active' | 'promoted' | 'repeated' | 'withdrawn' | 'graduated';
    school_class?: SchoolClass | null;
    section?: Section | null;
    academic_session?: AcademicSession | null;
    previous_enrollment?: (EnrollmentSummary & {
        school_class?: SchoolClass | null;
        section?: Section | null;
        academic_session?: AcademicSession | null;
    }) | null;
}

export interface StudentSummary {
    id: number;
    user_id: number;
    guardian_id: number | null;
    admission_number: string;
    user?: {
        id: number;
        name: string;
    };
    current_enrollment?: {
        school_class?: {
            id: number;
            name: string;
        };
        section?: {
            id: number;
            name: string;
        };
    };
}

export interface Student {
    id: number;
    user_id: number;
    guardian_id: number | null;
    admission_number: string;
    admission_date: string;
    date_of_birth: string | null;
    state_of_origin: string | null;
    lga: string | null;
    user: UserSummary;
    guardian?: GuardianSummary | null;
    current_enrollment?: EnrollmentSummary | null;
    enrollments?: EnrollmentSummary[] | null;
    student_marks_count?: number;
    attendances_count?: number;
    enrollments_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface StudentStats {
    total_students: number;
    active_students: number;
    male_students: number;
    female_students: number;
    enrolled_students: number;
}

export interface Alumnus {
    id: number;
    user_id: number;
    guardian_id: number | null;
    admission_number: string;
    admission_date: string;
    date_of_birth: string | null;
    state_of_origin: string | null;
    lga: string | null;
    user: UserSummary;
    guardian?: GuardianSummary | null;
    graduating_enrollment?: EnrollmentSummary | null;
    current_enrollment?: EnrollmentSummary | null;
    student_marks_count?: number;
    attendances_count?: number;
    enrollments_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface AlumniStats {
    total_alumni: number;
    male_alumni: number;
    female_alumni: number;
    graduating_batches: number;
}
