<?php

namespace App\Http\Requests;

use App\Models\AcademicSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('academic_sessions.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $session = $this->route('session');
        $sessionId = $session instanceof AcademicSession ? $session->id : (int) $session;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('academic_sessions', 'name')->ignore($sessionId),
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
            'name.unique' => 'An academic session with this name already exists.',
            'end_date.after' => 'The session end date must be after the start date.',
        ];
    }
}
