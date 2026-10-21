<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('teachers.create') ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('staff_id') && is_string($this->input('staff_id'))) {
            $this->merge([
                'staff_id' => strtoupper(trim($this->input('staff_id'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['required', 'string', Rule::enum(Gender::class)],
            'role' => ['nullable', 'string', 'max:50', Rule::exists('roles', 'name')->whereNotIn('name', ['student', 'guardian'])],
            'staff_id' => ['required', 'string', 'max:50', 'unique:teachers,staff_id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation' => ['nullable', 'string', 'max:150'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'joining_date' => ['nullable', 'date'],
            'password' => ['nullable', 'string', 'min:8'],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username'],
        ];
    }
}
