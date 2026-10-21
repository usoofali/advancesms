<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('sections.create') ?? false;
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
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sections', 'name')->where('class_id', $classId),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A section with this name already exists in the selected class.',
            'capacity.min' => 'Capacity must be at least 1 student.',
        ];
    }
}
