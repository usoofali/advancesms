<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class RecordTeacherAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->can('teacher_attendances.take') || $user?->can('teacher_attendances.update'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attendance_date' => [
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $dayOfWeek = Carbon::parse($value)->dayOfWeek;
                    if ($dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY) {
                        $fail('Teacher attendance can only be recorded on weekdays (Monday to Friday).');
                    }
                },
            ],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.remark' => ['nullable', 'string', 'max:255'],
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
            'attendance_date.required' => 'The attendance date is required.',
            'attendance_date.date_format' => 'The attendance date must match YYYY-MM-DD.',
            'records.required' => 'At least one teacher attendance entry must be provided.',
            'records.*.teacher_id.exists' => 'The selected teacher profile does not exist.',
            'records.*.status.required' => 'Attendance status is required for each marked teacher.',
        ];
    }
}
