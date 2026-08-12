<?php

namespace App\Domains\CitizenAccess\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hash_equals((string) config('services.citizen_access.public_intake_token'), (string) $this->bearerToken())
            && filled(config('services.citizen_access.public_intake_token'));
    }

    public function rules(): array
    {
        return [
            'submission_context' => ['nullable', Rule::in(['self', 'parent_guardian', 'responsible_person', 'enterprise_representative'])],
            'recipient_context' => ['nullable', Rule::in(['person', 'child', 'enterprise'])],
            'first_name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'beneficiary_first_name' => ['nullable', 'required_if:recipient_context,child', 'string', 'max:100'],
            'beneficiary_surname' => ['nullable', 'required_if:recipient_context,child', 'string', 'max:100'],
            'beneficiary_date_of_birth' => ['nullable', 'date', 'before:today'],
            'beneficiary_grade' => ['nullable', 'string', 'max:40'],
            'beneficiary_school_year' => ['nullable', 'string', 'max:20'],
            'beneficiary_school_name' => ['nullable', 'string', 'max:180'],
            'beneficiary_relationship' => ['nullable', 'required_if:recipient_context,child', 'string', 'max:80'],
            'enterprise_name' => ['nullable', 'required_if:recipient_context,enterprise', 'string', 'max:180'],
            'enterprise_registration_number' => ['nullable', 'string', 'max:120'],
            'enterprise_sector' => ['nullable', 'string', 'max:120'],
            'enterprise_registration_status' => ['nullable', 'string', 'max:80'],
            'province' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:160'],
            'ward_area' => ['nullable', 'string', 'max:80'],
            'preferred_delivery_channel' => ['nullable', Rule::in(['phone', 'online', 'community_site', 'office_visit'])],
            'preferred_contact_method' => ['required', Rule::in(['phone', 'sms', 'whatsapp', 'email'])],
            'selected_needs' => ['required', 'array', 'min:1', 'max:12'],
            'selected_needs.*' => ['string', 'max:160', 'distinct'],
            'current_position' => ['nullable', 'string', 'max:120'],
            'assistance_description' => ['nullable', 'string', 'max:2000'],
            'preferred_contact_time' => ['nullable', 'string', 'max:80'],
            'heard_about_poa' => ['nullable', 'string', 'max:120'],
            'consent_to_contact' => ['accepted'],
            'consent_to_process_data' => ['accepted'],
            'privacy_notice_accepted' => ['accepted'],
            'information_accuracy_confirmed' => ['accepted'],
            'source_channel' => ['nullable', 'string', 'max:80'],
            'campaign_source' => ['nullable', 'string', 'max:120'],
            'privacy_notice_version' => ['nullable', 'string', 'max:40'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'correlation_id' => ['nullable', 'string', 'max:120'],
            'honeypot' => ['nullable', 'size:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->clean($this->input('first_name')),
            'surname' => $this->clean($this->input('surname')),
            'mobile_number' => $this->clean($this->input('mobile_number')),
            'email' => $this->cleanEmail($this->input('email')),
            'submission_context' => $this->input('submission_context') ?: 'self',
            'recipient_context' => $this->input('recipient_context') ?: 'person',
            'beneficiary_first_name' => $this->clean($this->input('beneficiary_first_name')),
            'beneficiary_surname' => $this->clean($this->input('beneficiary_surname')),
            'beneficiary_relationship' => $this->clean($this->input('beneficiary_relationship')),
            'enterprise_name' => $this->clean($this->input('enterprise_name')),
            'source_channel' => $this->input('source_channel') ?: 'public_website',
            'privacy_notice_version' => $this->input('privacy_notice_version') ?: '2026-07',
            'idempotency_key' => $this->header('Idempotency-Key') ?: $this->input('idempotency_key'),
            'correlation_id' => $this->header('X-Correlation-ID') ?: $this->input('correlation_id'),
        ]);
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function cleanEmail(mixed $value): ?string
    {
        $value = $this->clean($value);

        return $value ? strtolower($value) : null;
    }
}
