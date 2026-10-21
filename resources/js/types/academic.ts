export interface AcademicTerm {
    id: number;
    academic_session_id: number;
    name: string;
    term_number: number;
    start_date: string;
    end_date: string;
    is_current: boolean;
    created_at?: string;
    updated_at?: string;
    academic_session?: AcademicSession;
}

export interface AcademicSession {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_current: boolean;
    terms?: AcademicTerm[];
    enrollments_count?: number;
    exams_count?: number;
    teacher_subject_assignments_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface AcademicStats {
    total_sessions: number;
    total_terms: number;
    active_session_name: string;
    active_term_name: string;
}

export type SecondaryStageValue = string;

export interface StageOption {
    value: string;
    label: string;
}

export interface Section {
    id: number;
    class_id: number;
    name: string;
    capacity: number;
    full_name?: string;
    enrollments_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface SubjectPivot {
    id: number;
    class_id: number;
    subject_id: number;
    is_compulsory: boolean;
}

export interface Subject {
    id: number;
    department_id: number | null;
    code: string;
    name: string;
    department?: Department | null;
    pivot?: SubjectPivot;
    classes_count?: number;
    teacher_subject_assignments_count?: number;
    student_marks_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface ClassSubject {
    id: number;
    class_id: number;
    subject_id: number;
    is_compulsory: boolean;
    school_class?: SchoolClass;
    subject?: Subject;
    created_at?: string;
    updated_at?: string;
}

export interface SchoolClass {
    id: number;
    name: string;
    level_order: number;
    stage: string;
    stage_label?: string;
    sections?: Section[];
    subjects?: Subject[];
    enrollments_count?: number;
    subjects_count?: number;
    teacher_subject_assignments_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface ClassStats {
    total_classes: number;
    total_sections: number;
    total_stages?: number;
    junior_classes?: number;
    senior_classes?: number;
}

export interface Department {
    id: number;
    name: string;
    description: string | null;
    teachers_count?: number;
    subjects_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface DepartmentStats {
    total_departments: number;
    total_teachers_assigned: number;
    total_subjects_assigned: number;
}

export interface SubjectStats {
    total_subjects: number;
    assigned_subjects?: number;
    unassigned_subjects?: number;
    total_curriculum_allocations: number;
}

export interface Grade {
    id: number;
    name: string;
    grade_point: number | string;
    mark_from: number | string;
    mark_to: number | string;
    remark: string;
    created_at?: string;
    updated_at?: string;
}

export interface ExamMilestone {
    id: number;
    academic_session_id: number;
    academic_term_id: number;
    name: string;
    ca_count: number;
    max_ca1: string | number;
    max_ca2: string | number;
    max_ca3: string | number;
    max_exam: string | number;
    ca1_label?: string | null;
    ca2_label?: string | null;
    ca3_label?: string | null;
    is_published: boolean;
    created_at?: string;
    updated_at?: string;
}

