<?php

namespace App\Http\Requests;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolClassRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('classes.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $class = $this->route('class');
        $classId = $class instanceof SchoolClass ? $class->id : (int) $class;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('classes', 'name')->ignore($classId),
            ],
            'stage' => ['required', 'string', 'max:50'],
            'level_order' => ['nullable', 'integer', 'min:1', 'max:100'],
            'position_mode' => ['nullable', 'string', 'in:start,end,before,after,custom'],
            'relative_class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A class with this name already exists in the school registry.',
            'level_order.min' => 'The grade level order must be at least 1.',
        ];
    }
}
