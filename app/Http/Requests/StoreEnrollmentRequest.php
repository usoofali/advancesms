<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('enrollments.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sessionId = $this->input('academic_session_id');
        $classId = (int) $this->input('class_id');

        return [
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'student_id' => [
                'required',
                'exists:students,id',
                Rule::unique('enrollments', 'student_id')
                    ->where('academic_session_id', $sessionId),
            ],
            'class_id' => ['required', 'exists:classes,id'],
            'section_id' => [
                'required',
                Rule::exists('sections', 'id')->where('class_id', $classId),
            ],
            'roll_number' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.unique' => 'This student is already enrolled in the selected academic session.',
            'section_id.exists' => 'The selected section does not belong to the chosen class.',
        ];
    }
}
