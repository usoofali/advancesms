<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('students.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // User Bio-data
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', new Enum(Gender::class)],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'username' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:8'],

            // Student Record
            'admission_number' => ['nullable', 'string', 'max:50', 'unique:students,admission_number'],
            'admission_date' => ['required', 'date'],
            'date_of_birth' => ['nullable', 'date'],
            'state_of_origin' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
            'guardian_id' => ['nullable', 'exists:guardians,id'],

            // Initial Class Enrollment (optional)
            'class_id' => ['nullable', 'required_with:section_id', 'exists:classes,id'],
            'section_id' => [
                'nullable',
                'required_with:class_id',
                Rule::when(
                    $this->filled('class_id'),
                    Rule::exists('sections', 'id')->where('class_id', (int) $this->input('class_id')),
                    'exists:sections,id'
                ),
            ],
            'roll_number' => ['nullable', 'string', 'max:30'],
        ];
    }
}
