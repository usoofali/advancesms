<?php

namespace App\Http\Requests;

use App\Models\SchoolSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSchoolSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('school_settings.update') ?? false;
    }

    /**
     * Prepare inputs for validation with current fallbacks.
     */
    protected function prepareForValidation(): void
    {
        $current = SchoolSetting::current();

        $this->merge([
            'ca_count' => $this->input('ca_count', $current->ca_count ?? 3),
            'default_max_ca1' => $this->input('default_max_ca1', $current->default_max_ca1 ?? 10.00),
            'default_max_ca2' => $this->input('default_max_ca2', $current->default_max_ca2 ?? 10.00),
            'default_max_ca3' => $this->input('default_max_ca3', $current->default_max_ca3 ?? 10.00),
            'default_max_exam' => $this->input('default_max_exam', $current->default_max_exam ?? 70.00),
            'ca1_label' => $this->input('ca1_label', $current->ca1_label ?? '1st CA'),
            'ca2_label' => $this->input('ca2_label', $current->ca2_label ?? '2nd CA'),
            'ca3_label' => $this->input('ca3_label', $current->ca3_label ?? '3rd CA'),
            'letterhead_top_margin' => $this->input('letterhead_top_margin', $current->letterhead_top_margin ?? 120),
            'letterhead_bottom_margin' => $this->input('letterhead_bottom_margin', $current->letterhead_bottom_margin ?? 50),
            'letterhead_left_margin' => $this->input('letterhead_left_margin', $current->letterhead_left_margin ?? 40),
            'letterhead_right_margin' => $this->input('letterhead_right_margin', $current->letterhead_right_margin ?? 40),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:255'],
            'school_code' => ['required', 'string', 'max:50'],
            'motto' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_code' => ['required', 'string', 'max:10'],
            'principal_name' => ['nullable', 'string', 'max:255'],
            'ca_count' => ['required', 'integer', 'between:1,3'],
            'default_max_ca1' => ['required', 'numeric', 'min:1', 'max:100'],
            'default_max_ca2' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_max_ca3' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_max_exam' => ['required', 'numeric', 'min:1', 'max:100'],
            'ca1_label' => ['required', 'string', 'max:50'],
            'ca2_label' => ['nullable', 'string', 'max:50'],
            'ca3_label' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'stamp' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'principal_photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'prospectus' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'letterhead_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'letterhead_base64' => ['nullable', 'string'],
            'letterhead_top_margin' => ['required', 'integer', 'min:0', 'max:500'],
            'letterhead_bottom_margin' => ['required', 'integer', 'min:0', 'max:500'],
            'letterhead_left_margin' => ['required', 'integer', 'min:0', 'max:500'],
            'letterhead_right_margin' => ['required', 'integer', 'min:0', 'max:500'],
            'about_text' => ['nullable', 'string'],
            'mission' => ['nullable', 'string'],
            'vision' => ['nullable', 'string'],
            'core_values' => ['nullable', 'array'],
            'core_values.*' => ['string', 'max:100'],
            'principal_message' => ['nullable', 'string'],
            'office_hours' => ['nullable', 'string', 'max:100'],
            'map_embed_url' => ['nullable', 'string'],
            'social_links' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $caCount = (int) $this->input('ca_count', 3);
            $ca1 = (float) $this->input('default_max_ca1', 0);
            $ca2 = $caCount >= 2 ? (float) $this->input('default_max_ca2', 0) : 0.0;
            $ca3 = $caCount >= 3 ? (float) $this->input('default_max_ca3', 0) : 0.0;
            $exam = (float) $this->input('default_max_exam', 0);

            $total = round($ca1 + $ca2 + $ca3 + $exam, 2);
            if (abs($total - 100.0) > 0.01) {
                $validator->errors()->add(
                    'default_max_exam',
                    "The assessment weights must sum to exactly 100%. (Current total: {$total}%)."
                );
            }
        });
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.max' => 'The school logo file size cannot exceed 2MB.',
            'logo.image' => 'The school logo must be a valid image file (PNG, JPG, WEBP, or SVG).',
            'stamp.max' => 'The principal stamp / signature file size cannot exceed 2MB.',
            'stamp.image' => 'The principal stamp must be a valid image file (PNG, JPG, or WEBP).',
        ];
    }
}
