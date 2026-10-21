<?php

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BatchSaveStudentMarksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('student_marks.enter')
            || $this->user()?->can('student_marks.update'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Exam|null $exam */
        $exam = Exam::find($this->input('exam_id'));
        $caCount = $exam?->ca_count ?? 3;

        $maxCa1 = $exam ? (float) $exam->max_ca1 : 10.0;
        $maxCa2 = $exam ? (float) $exam->max_ca2 : 10.0;
        $maxCa3 = $exam ? (float) $exam->max_ca3 : 10.0;
        $maxExam = $exam ? (float) $exam->max_exam : 70.0;

        $rules = [
            'exam_id' => ['required', 'exists:exams,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'marks' => ['required', 'array'],
            'marks.*.enrollment_id' => ['required', 'exists:enrollments,id'],
            'marks.*.ca1' => ['nullable', 'numeric', 'min:0', "max:{$maxCa1}"],
            'marks.*.exam_score' => ['nullable', 'numeric', 'min:0', "max:{$maxExam}"],
            'marks.*.teacher_comment' => ['nullable', 'string', 'max:255'],
        ];

        if ($caCount >= 2) {
            $rules['marks.*.ca2'] = ['nullable', 'numeric', 'min:0', "max:{$maxCa2}"];
        } else {
            $rules['marks.*.ca2'] = ['nullable'];
        }

        if ($caCount >= 3) {
            $rules['marks.*.ca3'] = ['nullable', 'numeric', 'min:0', "max:{$maxCa3}"];
        } else {
            $rules['marks.*.ca3'] = ['nullable'];
        }

        return $rules;
    }
}
