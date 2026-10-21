<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDailyAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('daily_attendances.take')
            || $this->user()?->can('daily_attendances.update'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => [
                'required',
                'integer',
                Rule::exists('sections', 'id')->where('class_id', (int) $this->input('class_id')),
            ],
            'records' => ['required', 'array'],
            'records.*.enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
