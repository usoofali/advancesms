<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherSubjectAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('teacher_subject_assignments.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $termId = $this->input('academic_term_id');
        $sectionId = $this->input('section_id');

        return [
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'academic_term_id' => ['required', 'exists:academic_terms,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'section_id' => [
                'required',
                Rule::exists('sections', 'id')->where('class_id', (int) $this->input('class_id')),
            ],
            'subject_id' => [
                'required',
                'exists:subjects,id',
                Rule::unique('teacher_subject_assignments', 'subject_id')
                    ->where('academic_term_id', $termId)
                    ->where('section_id', $sectionId),
            ],
            'can_input_marks' => ['nullable', 'boolean'],
            'can_take_attendance' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.unique' => 'A teacher is already assigned to this subject in the selected section for this term.',
        ];
    }
}
