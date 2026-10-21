<?php

namespace App\Http\Requests;

use App\Models\AcademicTerm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicTermRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('academic_terms.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $term = $this->route('term');
        $termModel = $term instanceof AcademicTerm ? $term : AcademicTerm::findOrFail((int) $term);

        return [
            'name' => ['required', 'string', 'max:50'],
            'term_number' => [
                'required',
                'integer',
                'in:1,2,3',
                Rule::unique('academic_terms', 'term_number')
                    ->where('academic_session_id', $termModel->academic_session_id)
                    ->ignore($termModel->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'term_number.unique' => 'A term with this term number already exists in this academic session.',
            'end_date.after' => 'The term end date must be after the start date.',
        ];
    }
}
