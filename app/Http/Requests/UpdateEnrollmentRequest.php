<?php

namespace App\Http\Requests;

use App\Enums\EnrollmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('enrollments.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $classId = (int) $this->input('class_id');

        return [
            'class_id' => ['required', 'exists:classes,id'],
            'section_id' => [
                'required',
                Rule::exists('sections', 'id')->where('class_id', $classId),
            ],
            'roll_number' => ['nullable', 'string', 'max:30'],
            'status' => ['required', new Enum(EnrollmentStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'section_id.exists' => 'The selected section does not belong to the chosen class.',
        ];
    }
}
