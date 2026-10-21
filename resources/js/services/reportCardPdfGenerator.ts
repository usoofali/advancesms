import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';
import timesFont from 'pdfmake/build/standard-fonts/Times';
import helveticaFont from 'pdfmake/build/standard-fonts/Helvetica';

// Configure vfs and standard rich fonts safely for Vite/ESM
const vfs = (pdfFonts as any)?.pdfMake?.vfs || (pdfFonts as any)?.default?.pdfMake?.vfs || pdfFonts;
if (vfs) {
    (pdfMake as any).vfs = vfs;
}

if ((pdfMake as any).addFontContainer) {
    try {
        const resolvedTimes = (timesFont as any)?.default || timesFont;
        const resolvedHelvetica = (helveticaFont as any)?.default || helveticaFont;
        if (resolvedTimes) (pdfMake as any).addFontContainer(resolvedTimes);
        if (resolvedHelvetica) (pdfMake as any).addFontContainer(resolvedHelvetica);
    } catch {
        // Fallback to built-in Roboto if standard font container registration fails
    }
}

export interface StudentMarkItem {
    id: number;
    subject_name: string;
    subject_code: string;
    ca1: number;
    ca2: number;
    ca3: number;
    exam_score: number;
    total_score: number;
    grade: string;
    grade_point?: number;
    remark: string;
    teacher_comment?: string | null;
}

export interface StudentReportItem {
    id: number;
    admission_number: string;
    student_name: string;
    gender: string;
    roll_number: string | null;
    date_of_birth?: string | null;
    state_of_origin?: string | null;
    guardian_name?: string | null;
    guardian_phone?: string | null;
    marks: StudentMarkItem[];
    total_score: number;
    average_score: number;
    subjects_count: number;
    class_position: string;
    section_position?: string;
    attendance: {
        days_present: number;
        days_late: number;
        days_absent: number;
        days_excused?: number;
        total_days: number;
        rate: number;
    };
    remarks: {
        teacher: string;
        principal: string;
    };
}

export interface BatchReportCardData {
    session_name: string;
    term_name: string;
    class_name: string;
    section_name: string;
    class_total_students: number;
    section_total_students?: number;
    highest_class_average: number;
    lowest_class_average: number;
    class_total_average: number;
    school: {
        name: string;
        code: string;
        motto: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        principal_name: string | null;
        logo_url: string | null;
        logo_base64?: string | null;
        stamp_url: string | null;
        stamp_base64?: string | null;
        letterhead_base64: string | null;
        letterhead_top_margin: number;
        letterhead_bottom_margin: number;
        letterhead_left_margin: number;
        letterhead_right_margin: number;
    };
    exam: {
        id: number;
        name: string;
        ca_count: number;
        max_ca1: number;
        max_ca2: number;
        max_ca3: number;
        max_exam: number;
        ca1_label: string;
        ca2_label: string;
        ca3_label: string;
    };
    students: StudentReportItem[];
    grades: Array<{
        name: string;
        grade_point: string | number;
        mark_from: string | number;
        mark_to: string | number;
        remark: string;
    }>;
}

export interface BatchPdfOptions {
    useLetterheadImage: boolean;
    style: 'minimal' | 'comprehensive';
    fontPairing?: 'academic_serif' | 'modern_sans' | 'classic_formal';
    topMargin?: number;
    bottomMargin?: number;
    leftMargin?: number;
    rightMargin?: number;
}

/**
 * Generate and download a consolidated, multi-page batch report card PDF for a class.
 */
export function generateClassBatchReportPdf(
    data: BatchReportCardData,
    options: BatchPdfOptions
): void {
    if (!data.students || data.students.length === 0) {
        throw new Error('No students found to generate report cards for.');
    }

    const topMargin = options.topMargin ?? (data.school.letterhead_top_margin || 120);
    const bottomMargin = options.bottomMargin ?? (data.school.letterhead_bottom_margin || 50);
    const leftMargin = options.leftMargin ?? (data.school.letterhead_left_margin || 40);
    const rightMargin = options.rightMargin ?? (data.school.letterhead_right_margin || 40);

    const caCount = Number(data.exam?.ca_count || 3);
    const ca1Label = data.exam?.ca1_label || 'CA 1';
    const ca2Label = data.exam?.ca2_label || 'CA 2';
    const ca3Label = data.exam?.ca3_label || 'CA 3';

    // Rich Typography & Font Styling Configuration
    const fontPairing = options.fontPairing || 'academic_serif';
    const titleFont = fontPairing === 'modern_sans' ? 'Helvetica' : 'Times';
    const bodyFont = fontPairing === 'classic_formal' ? 'Times' : 'Helvetica';
    const studentNameFont = fontPairing === 'modern_sans' ? 'Helvetica' : 'Times';
    const remarksFont = fontPairing === 'modern_sans' ? 'Helvetica' : 'Times';

    const pdfContent: any[] = [];
    const totalStudentsInClass = data.class_total_students || data.students.length;
    const totalStudentsInSection = data.section_total_students || totalStudentsInClass;

    data.students.forEach((student, index) => {
        const isLastStudent = index === data.students.length - 1;
        const overallAverage = student.average_score.toFixed(1);

        if (options.style === 'comprehensive') {
            // =========================================================================
            // COMPREHENSIVE STYLE (Full Bio-Data, Side-by-Side Standings, Signatures)
            // =========================================================================

            // 1. Institutional Header (If not using pre-printed letterhead)
            if (!options.useLetterheadImage || !data.school.letterhead_base64) {
                const headerCols: any[] = [];

                if (data.school.logo_base64) {
                    headerCols.push({
                        image: data.school.logo_base64,
                        fit: [44, 44],
                        width: 52,
                    });
                }

                headerCols.push({
                    stack: [
                        { text: data.school.name.toUpperCase(), font: titleFont, fontSize: 13.5, bold: true, color: '#0f172a', characterSpacing: 0.5 },
                        data.school.motto ? { text: `"${data.school.motto}"`, font: titleFont, fontSize: 8, italics: true, color: '#475569', margin: [0, 1, 0, 1] } : {},
                        {
                            text: [
                                data.school.address ? `${data.school.address} ` : '',
                                data.school.phone ? `• Tel: ${data.school.phone} ` : '',
                                data.school.email ? `• ${data.school.email}` : '',
                            ],
                            fontSize: 8,
                            color: '#64748b',
                        },
                    ],
                    width: '*',
                });

                headerCols.push({
                    stack: [
                        { text: 'ACADEMIC YEAR', fontSize: 7.5, bold: true, color: '#64748b', alignment: 'right' },
                        { text: data.session_name, fontSize: 11, bold: true, color: '#0f172a', alignment: 'right' },
                        { text: data.term_name, fontSize: 9.5, bold: true, color: '#334155', alignment: 'right', margin: [0, 2, 0, 0] },
                    ],
                    width: 115,
                });

                pdfContent.push({
                    columns: headerCols,
                    margin: [0, 0, 0, 6],
                });
            }

            // 2. Official Title Banner (Report Title = 13.5pt, clean with no background fill or border)
            pdfContent.push({
                text: 'OFFICIAL TERMINAL EXAMINATION REPORT CARD',
                font: titleFont,
                fontSize: 13.5,
                bold: true,
                alignment: 'center',
                color: '#0f172a',
                characterSpacing: titleFont === 'Times' ? 0.75 : 0.5,
                margin: [0, 0, 0, 8],
            });

            // 3. Student Bio-Data Matrix (Student & Exam Headers = 11pt, clean transparent background)
            pdfContent.push({
                table: {
                    widths: ['*', '*', '*', '*'],
                    body: [
                        [
                            {
                                stack: [
                                    { text: 'STUDENT FULL NAME', fontSize: 7, bold: true, color: '#64748b' },
                                    { text: student.student_name.toUpperCase(), font: studentNameFont, fontSize: 11, bold: true, color: '#0f172a', margin: [0, 1, 0, 0] },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'ADMISSION NUMBER', fontSize: 7, bold: true, color: '#64748b' },
                                    { text: student.admission_number, fontSize: 11, bold: true, color: '#0f172a', margin: [0, 1, 0, 0] },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'CLASS & SECTION', fontSize: 7, bold: true, color: '#64748b' },
                                    { text: `${data.class_name} (${data.section_name})`, fontSize: 11, bold: true, color: '#0f172a', margin: [0, 1, 0, 0] },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'GENDER', fontSize: 7, bold: true, color: '#64748b' },
                                    { text: (student.gender || '—').toUpperCase(), fontSize: 11, bold: true, color: '#0f172a', margin: [0, 1, 0, 0] },
                                ],
                            },
                        ],
                        [
                            {
                                stack: [
                                    { text: 'DATE OF BIRTH', fontSize: 7, bold: true, color: '#64748b' },
                                    { text: student.date_of_birth || 'Not Recorded', fontSize: 9.5, color: '#0f172a', margin: [0, 1, 0, 0] },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'STATE OF ORIGIN', fontSize: 7, bold: true, color: '#64748b' },
                                    { text: student.state_of_origin || 'N/A', fontSize: 9.5, color: '#0f172a', margin: [0, 1, 0, 0] },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'PARENT / GUARDIAN', fontSize: 7, bold: true, color: '#64748b' },
                                    {
                                        text: `${student.guardian_name || 'N/A'}${student.guardian_phone ? ` (${student.guardian_phone})` : ''}`,
                                        fontSize: 9.5,
                                        color: '#0f172a',
                                        margin: [0, 1, 0, 0],
                                    },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'ACADEMIC PERIOD', fontSize: 7, bold: true, color: '#64748b' },
                                    { text: `${data.term_name} (${data.session_name})`, fontSize: 9.5, bold: true, color: '#0f172a', margin: [0, 1, 0, 0] },
                                ],
                            },
                        ],
                    ],
                },
                layout: {
                    hLineColor: () => '#cbd5e1',
                    vLineColor: () => '#cbd5e1',
                },
                margin: [0, 0, 0, 7],
            });

            // 4. Subject Marks Table (Subject Table = 10pt, clean with no heavy individual cell fills)
            const headerRow: any[] = [
                { text: '#', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: 'SUBJECT', style: 'tableHeader', fontSize: 10 },
            ];

            headerRow.push({ text: ca1Label.toUpperCase(), style: 'tableHeader', alignment: 'center', fontSize: 10 });
            if (caCount >= 2) {
                headerRow.push({ text: ca2Label.toUpperCase(), style: 'tableHeader', alignment: 'center', fontSize: 10 });
            }
            if (caCount >= 3) {
                headerRow.push({ text: ca3Label.toUpperCase(), style: 'tableHeader', alignment: 'center', fontSize: 10 });
            }

            headerRow.push(
                { text: 'EXAM', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: 'TOTAL', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: 'GRADE', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: 'PT', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: "TEACHER'S REMARK", style: 'tableHeader', fontSize: 10 }
            );

            const tableBody: any[] = [headerRow];

            student.marks.forEach((mark, subIndex) => {
                const row: any[] = [
                    { text: (subIndex + 1).toString(), alignment: 'center', fontSize: 10 },
                    { text: mark.subject_name, fontSize: 10, bold: true },
                ];

                row.push({ text: mark.ca1.toString(), alignment: 'center', fontSize: 10 });
                if (caCount >= 2) {
                    row.push({ text: mark.ca2.toString(), alignment: 'center', fontSize: 10 });
                }
                if (caCount >= 3) {
                    row.push({ text: mark.ca3.toString(), alignment: 'center', fontSize: 10 });
                }

                row.push(
                    { text: mark.exam_score.toString(), alignment: 'center', fontSize: 10 },
                    { text: mark.total_score.toString(), alignment: 'center', fontSize: 10, bold: true },
                    { text: mark.grade || '—', alignment: 'center', fontSize: 10, bold: true },
                    { text: (mark.grade_point ?? '—').toString(), alignment: 'center', fontSize: 9.5 },
                    { text: mark.teacher_comment || mark.remark || '—', fontSize: 9.5, italics: true }
                );

                tableBody.push(row);
            });

            const widths: (number | string)[] = [18, '*'];
            widths.push(36); // CA1
            if (caCount >= 2) widths.push(36); // CA2
            if (caCount >= 3) widths.push(36); // CA3
            widths.push(40); // Exam
            widths.push(42); // Total
            widths.push(36); // Grade
            widths.push(24); // Pt
            widths.push(75); // Teacher Remark

            pdfContent.push({
                table: {
                    headerRows: 1,
                    widths,
                    body: tableBody,
                },
                layout: {
                    fillColor: (rowIndex: number) => {
                        if (rowIndex === 0) return '#f8fafc';
                        return rowIndex % 2 === 0 ? '#fafafa' : null;
                    },
                    hLineColor: () => '#cbd5e1',
                    vLineColor: () => '#e2e8f0',
                },
                margin: [0, 0, 0, 7],
            });

            // 5. Side-by-Side: Terminal Academic Standing & Attendance Profile (Summary Metrics = 9pt)
            const academicStandingTable = {
                table: {
                    widths: ['*', '*'],
                    body: [
                        [
                            {
                                text: 'TERMINAL ACADEMIC STANDING',
                                font: titleFont,
                                fontSize: 9,
                                bold: true,
                                color: '#1e293b',
                                fillColor: '#f1f5f9',
                                colSpan: 2,
                                margin: [0, 2, 0, 2],
                            },
                            {},
                        ],
                        [
                            {
                                stack: [
                                    { text: 'TOTAL SCORE', fontSize: 7, color: '#64748b', bold: true },
                                    { text: `${student.total_score} / ${student.marks.length * 100}`, fontSize: 9, bold: true, color: '#0f172a' },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'TERMINAL AVERAGE', fontSize: 7, color: '#64748b', bold: true },
                                    { text: `${overallAverage}%`, fontSize: 9, bold: true, color: '#0f172a' },
                                ],
                            },
                        ],
                        [
                            {
                                stack: [
                                    { text: 'POSITION IN SECTION', fontSize: 7, color: '#64748b', bold: true },
                                    { text: `${student.section_position || student.class_position} of ${totalStudentsInSection}`, fontSize: 9, bold: true, color: '#0f172a' },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'POSITION IN CLASS', fontSize: 7, color: '#64748b', bold: true },
                                    { text: `${student.class_position} of ${totalStudentsInClass}`, fontSize: 9, bold: true, color: '#0f172a' },
                                ],
                            },
                        ],
                        [
                            {
                                text: `Class Avg: ${data.class_total_average}%  •  Highest: ${data.highest_class_average}%  •  Lowest: ${data.lowest_class_average}%`,
                                fontSize: 7.5,
                                color: '#475569',
                                colSpan: 2,
                                margin: [0, 2, 0, 2],
                            },
                            {},
                        ],
                    ],
                },
                layout: {
                    hLineColor: () => '#cbd5e1',
                    vLineColor: () => '#e2e8f0',
                },
            };

            const attendanceTable = {
                table: {
                    widths: ['*', '*'],
                    body: [
                        [
                            {
                                text: 'ATTENDANCE & PUNCTUALITY PROFILE',
                                font: titleFont,
                                fontSize: 9,
                                bold: true,
                                color: '#1e293b',
                                fillColor: '#f1f5f9',
                                colSpan: 2,
                                margin: [0, 2, 0, 2],
                            },
                            {},
                        ],
                        [
                            {
                                stack: [
                                    { text: 'RECORDED SCHOOL DAYS', fontSize: 7, color: '#64748b', bold: true },
                                    { text: `${student.attendance.total_days} Days`, fontSize: 9, bold: true, color: '#0f172a' },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'ATTENDANCE RATE', fontSize: 7, color: '#64748b', bold: true },
                                    { text: `${student.attendance.rate}%`, fontSize: 9, bold: true, color: '#0f172a' },
                                ],
                            },
                        ],
                        [
                            {
                                stack: [
                                    { text: 'DAYS PRESENT / LATE', fontSize: 7, color: '#64748b', bold: true },
                                    { text: `${student.attendance.days_present} Present, ${student.attendance.days_late} Late`, fontSize: 8.5, bold: true, color: '#0f172a' },
                                ],
                            },
                            {
                                stack: [
                                    { text: 'DAYS ABSENT / EXCUSED', fontSize: 7, color: '#64748b', bold: true },
                                    { text: `${student.attendance.days_absent} Absent, ${student.attendance.days_excused || 0} Excused`, fontSize: 8.5, bold: true, color: '#0f172a' },
                                ],
                            },
                        ],
                        [
                            {
                                text: 'Minimum Term Attendance Threshold: 85% Required',
                                fontSize: 7.5,
                                color: '#475569',
                                colSpan: 2,
                                margin: [0, 2, 0, 2],
                            },
                            {},
                        ],
                    ],
                },
                layout: {
                    hLineColor: () => '#cbd5e1',
                    vLineColor: () => '#e2e8f0',
                },
            };

            pdfContent.push({
                columns: [
                    { width: '*', ...academicStandingTable },
                    { width: 8, text: '' },
                    { width: '*', ...attendanceTable },
                ],
                margin: [0, 0, 0, 6],
            });

            // 6. Grading Scale Reference Benchmark Strip
            if (data.grades && data.grades.length > 0) {
                const gradeParts = data.grades.map(
                    (g) => `${g.name}: ${g.mark_from}-${g.mark_to}% (${g.remark})`
                );
                pdfContent.push({
                    table: {
                        widths: ['*'],
                        body: [
                            [
                                {
                                    text: [
                                        { text: 'Grading Benchmark: ', bold: true, color: '#334155' },
                                        { text: gradeParts.join('   •   '), color: '#475569' },
                                    ],
                                    fontSize: 7.5,
                                    margin: [0, 2, 0, 2],
                                },
                            ],
                        ],
                    },
                    layout: {
                        hLineColor: () => '#e2e8f0',
                        vLineColor: () => '#e2e8f0',
                    },
                    margin: [0, 0, 0, 6],
                });
            }

            // 7. Remarks & Endorsement Box (Remarks = 9pt, Principal Name & Title = 9pt)
            const principalSealStack: any[] = [];
            if (data.school.stamp_base64) {
                principalSealStack.push({
                    image: data.school.stamp_base64,
                    fit: [140, 52],
                    alignment: 'center',
                    margin: [0, 0, 0, 4],
                });
            } else {
                principalSealStack.push({
                    text: '[ Official Seal / Signature ]',
                    fontSize: 8,
                    italics: true,
                    color: '#94a3b8',
                    alignment: 'center',
                    margin: [0, 14, 0, 14],
                });
            }
            principalSealStack.push(
                { text: '_______________________________', fontSize: 8, alignment: 'center', color: '#64748b' },
                { text: (data.school.principal_name || 'The Principal').toUpperCase(), font: titleFont, fontSize: 9, bold: true, alignment: 'center', color: '#0f172a', margin: [0, 3, 0, 1] },
                { text: 'Principal / Head of School', fontSize: 9, color: '#475569', alignment: 'center' }
            );

            pdfContent.push({
                table: {
                    widths: ['*', 160],
                    body: [
                        [
                            {
                                stack: [
                                    {
                                        text: [
                                            { text: "FORM MASTER / CLASS TEACHER'S REMARK:\n", bold: true, fontSize: 8.5, color: '#334155' },
                                            { text: student.remarks.teacher || 'Satisfactory academic performance and character.', font: remarksFont, fontSize: 9, italics: true, color: '#0f172a' },
                                        ],
                                        margin: [0, 0, 0, 6],
                                    },
                                    {
                                        text: [
                                            { text: "PRINCIPAL'S OFFICIAL RECOMMENDATION:\n", bold: true, fontSize: 8.5, color: '#334155' },
                                            { text: student.remarks.principal || 'Promising results shown. Keep striving for academic excellence.', font: remarksFont, fontSize: 9, italics: true, color: '#0f172a' },
                                        ],
                                    },
                                ],
                                margin: [4, 4, 4, 4],
                            },
                            {
                                stack: principalSealStack,
                                margin: [2, 2, 2, 2],
                            },
                        ],
                    ],
                },
                layout: {
                    hLineColor: () => '#cbd5e1',
                    vLineColor: () => '#cbd5e1',
                },
                margin: [0, 0, 0, 4],
            });

        } else {
            // =========================================================================
            // MINIMALIST STYLE (Aligned with Typography Requirements)
            // =========================================================================

            // Student & Exam Metadata Card (Report Title = 13.5pt, Headers = 11pt)
            pdfContent.push(
                {
                    text: 'STUDENT TERMINAL REPORT CARD',
                    font: titleFont,
                    style: 'mainTitle',
                    fontSize: 13.5,
                    bold: true,
                    alignment: 'center',
                    characterSpacing: titleFont === 'Times' ? 0.75 : 0.5,
                    margin: [0, 0, 0, 10],
                },
                {
                    columns: [
                        [
                            { text: `STUDENT NAME: ${student.student_name.toUpperCase()}`, font: studentNameFont, fontSize: 11, bold: true, margin: [0, 0, 0, 3] },
                            { text: `ADMISSION NUMBER: ${student.admission_number}`, fontSize: 11, bold: true, margin: [0, 0, 0, 3] },
                            { text: `CLASS & SECTION: ${data.class_name} - ${data.section_name}`, fontSize: 11, bold: true, margin: [0, 0, 0, 3] },
                        ],
                        [
                            { text: `EXAMINATION: ${data.exam.name || 'Terminal Exam'}`, fontSize: 11, bold: true, alignment: 'right', margin: [0, 0, 0, 3] },
                            { text: `ACADEMIC TERM: ${data.term_name}`, fontSize: 11, bold: true, alignment: 'right', margin: [0, 0, 0, 3] },
                            { text: `SESSION: ${data.session_name}`, fontSize: 11, bold: true, alignment: 'right', margin: [0, 0, 0, 3] },
                        ],
                    ],
                    margin: [0, 0, 0, 10],
                }
            );

            // Dynamic Subject Table Headers based on CA Count (Subject Table = 10pt)
            const headerRow: any[] = [
                { text: '#', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: 'SUBJECT', style: 'tableHeader', fontSize: 10 },
            ];

            headerRow.push({ text: ca1Label.toUpperCase(), style: 'tableHeader', alignment: 'center', fontSize: 10 });
            if (caCount >= 2) {
                headerRow.push({ text: ca2Label.toUpperCase(), style: 'tableHeader', alignment: 'center', fontSize: 10 });
            }
            if (caCount >= 3) {
                headerRow.push({ text: ca3Label.toUpperCase(), style: 'tableHeader', alignment: 'center', fontSize: 10 });
            }

            headerRow.push(
                { text: 'EXAM', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: 'TOTAL', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: 'GRADE', style: 'tableHeader', alignment: 'center', fontSize: 10 },
                { text: 'REMARK', style: 'tableHeader', alignment: 'center', fontSize: 10 }
            );

            const tableBody: any[] = [headerRow];

            student.marks.forEach((subject, subIndex) => {
                const row: any[] = [
                    { text: (subIndex + 1).toString(), alignment: 'center', fontSize: 10 },
                    { text: subject.subject_name, fontSize: 10, bold: true },
                ];

                row.push({ text: subject.ca1.toString(), alignment: 'center', fontSize: 10 });
                if (caCount >= 2) {
                    row.push({ text: subject.ca2.toString(), alignment: 'center', fontSize: 10 });
                }
                if (caCount >= 3) {
                    row.push({ text: subject.ca3.toString(), alignment: 'center', fontSize: 10 });
                }

                row.push(
                    { text: subject.exam_score.toString(), alignment: 'center', fontSize: 10 },
                    { text: subject.total_score.toString(), alignment: 'center', fontSize: 10, bold: true },
                    { text: subject.grade || '—', alignment: 'center', fontSize: 10, bold: true },
                    { text: subject.remark || '—', font: remarksFont, fontSize: 9.5, italics: true }
                );

                tableBody.push(row);
            });

            const widths: (number | string)[] = [20, '*'];
            widths.push(40); // CA1
            if (caCount >= 2) widths.push(40); // CA2
            if (caCount >= 3) widths.push(40); // CA3
            widths.push(45); // Exam
            widths.push(45); // Total
            widths.push(40); // Grade
            widths.push(65); // Remark

            pdfContent.push({
                table: {
                    headerRows: 1,
                    widths,
                    body: tableBody,
                },
                layout: {
                    fillColor: (rowIndex: number) => {
                        if (rowIndex === 0) return '#f8fafc';
                        return rowIndex % 2 === 0 ? '#fafafa' : null;
                    },
                    hLineColor: () => '#cbd5e1',
                    vLineColor: () => '#e2e8f0',
                },
                margin: [0, 0, 0, 10],
            });

            // Minimalist Summary (Summary Metrics = 9pt)
            pdfContent.push({
                columns: [
                    [
                        { text: `TOTAL SCORE: ${student.total_score}`, fontSize: 9, bold: true, margin: [0, 0, 0, 2] },
                        { text: `AVERAGE SCORE: ${overallAverage}%`, fontSize: 9, bold: true, margin: [0, 0, 0, 2] },
                    ],
                    [
                        { text: `POSITION IN CLASS: ${student.class_position} of ${totalStudentsInClass}`, fontSize: 9, bold: true, alignment: 'right', margin: [0, 0, 0, 2] },
                        { text: `SUBJECTS EVALUATED: ${student.subjects_count}`, fontSize: 9, bold: true, alignment: 'right', margin: [0, 0, 0, 2] },
                    ],
                ],
                margin: [0, 0, 0, 8],
            });

            // Minimalist Remarks & Attached Signatures (Remarks = 9pt, Principal Name & Title = 9pt)
            const minPrincipalStack: any[] = [
                { text: "PRINCIPAL'S SIGNATURE & STAMP", fontSize: 9, bold: true, alignment: 'right', margin: [0, 0, 0, 4] },
            ];

            if (data.school.stamp_base64) {
                minPrincipalStack.push({
                    image: data.school.stamp_base64,
                    fit: [130, 48],
                    alignment: 'right',
                    margin: [0, 0, 0, 2],
                });
            } else {
                minPrincipalStack.push({
                    text: '',
                    margin: [0, 0, 0, 18],
                });
            }

            minPrincipalStack.push(
                { text: '__________________________________', fontSize: 8, alignment: 'right' },
                { text: (data.school.principal_name || 'The Principal').toUpperCase(), font: titleFont, fontSize: 9, bold: true, alignment: 'right', margin: [0, 3, 0, 1] },
                { text: 'Principal / Head of School', fontSize: 9, color: '#475569', alignment: 'right' }
            );

            pdfContent.push(
                {
                    table: {
                        widths: ['*'],
                        body: [
                            [
                                {
                                    text: [
                                        { text: "FORM MASTER'S REMARK: ", bold: true, fontSize: 9 },
                                        { text: student.remarks.teacher || 'Satisfactory academic performance.', font: remarksFont, fontSize: 9, italics: true },
                                    ],
                                },
                            ],
                            [
                                {
                                    text: [
                                        { text: "PRINCIPAL'S REMARK: ", bold: true, fontSize: 9 },
                                        { text: student.remarks.principal || 'Promising results shown. Keep striving for excellence.', font: remarksFont, fontSize: 9, italics: true },
                                    ],
                                },
                            ],
                        ],
                    },
                    layout: {
                        hLineColor: () => '#e2e8f0',
                        vLineColor: () => '#e2e8f0',
                    },
                    margin: [0, 0, 0, 12],
                },
                {
                    columns: [
                        {
                            stack: [
                                { text: 'FORM MASTER SIGNATURE', fontSize: 9, bold: true, margin: [0, 0, 0, 32] },
                                { text: '__________________________________', fontSize: 8 },
                            ],
                        },
                        {
                            stack: minPrincipalStack,
                        },
                    ],
                    margin: [0, 0, 0, 0],
                }
            );
        }

        // Page break after each student except the last one
        if (!isLastStudent) {
            pdfContent.push({ text: '', pageBreak: 'after' });
        }
    });

    // Document Definition
    const docDefinition: any = {
        pageSize: 'A4',
        pageMargins: [leftMargin, topMargin, rightMargin, bottomMargin],
        content: pdfContent,
        styles: {
            mainTitle: {
                font: titleFont,
                fontSize: 13.5,
                bold: true,
                color: '#0f172a',
            },
            tableHeader: {
                fontSize: 10,
                bold: true,
                color: '#1e293b',
            },
            summaryLabel: {
                fontSize: 8,
                bold: true,
                color: '#64748b',
                alignment: 'center',
            },
            summaryValue: {
                fontSize: 9,
                bold: true,
                color: '#0f172a',
                alignment: 'center',
            },
        },
        defaultStyle: {
            font: bodyFont,
            fontSize: 9,
            color: '#0f172a',
            lineHeight: 1.15,
        },
    };

    // Embed Letterhead background image if requested
    if (options.useLetterheadImage && data.school.letterhead_base64) {
        docDefinition.background = () => [
            {
                image: data.school.letterhead_base64,
                width: 595.28,
                height: 841.89,
                margin: [0, 0],
            },
        ];
    }

    const safeClassName = data.class_name.replace(/[^a-zA-Z0-9_-]/g, '_');
    const safeSectionName = data.section_name.replace(/[^a-zA-Z0-9_-]/g, '_');
    const safeTermName = data.term_name.replace(/[^a-zA-Z0-9_-]/g, '_');
    const filename = `Report_Cards_${safeClassName}_${safeSectionName}_${safeTermName}.pdf`;

    pdfMake.createPdf(docDefinition).download(filename);
}
