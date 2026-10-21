<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteStudentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('enrollments.promote') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetClassId = (int) $this->input('target_class_id');

        return [
            'source_session_id' => ['required', 'exists:academic_sessions,id'],
            'target_session_id' => ['required', 'exists:academic_sessions,id', 'different:source_session_id'],
            'action' => ['required', 'string', 'in:promote,repeat,graduate'],
            'target_class_id' => ['required_unless:action,graduate', 'nullable', 'exists:classes,id'],
            'target_section_id' => [
                'required_unless:action,graduate',
                'nullable',
                Rule::when(
                    $this->input('action') !== 'graduate' && $this->filled('target_class_id'),
                    Rule::exists('sections', 'id')->where('class_id', $targetClassId),
                    'exists:sections,id'
                ),
            ],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'exists:students,id'],
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
            'target_session_id.different' => 'Target academic session must be different from the source session.',
            'target_class_id.required_unless' => 'Target class is required when promoting or repeating students.',
            'target_section_id.required_unless' => 'Target section is required when promoting or repeating students.',
            'student_ids.required' => 'Please select at least one student to promote, repeat, or graduate.',
            'student_ids.min' => 'Please select at least one student to promote, repeat, or graduate.',
        ];
    }
}
