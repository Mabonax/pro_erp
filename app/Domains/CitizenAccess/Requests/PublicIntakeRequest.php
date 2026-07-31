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
            'first_name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'province' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:160'],
            'ward_area' => ['nullable', 'string', 'max:80'],
            'preferred_delivery_channel' => ['nullable', Rule::in(['phone', 'online', 'community_site', 'office_visit'])],
            'preferred_contact_method' => ['required', Rule::in(['phone', 'sms', 'whatsapp', 'email'])],
            'selected_needs' => ['required', 'array', 'min:1', 'max:12'],
            'selected_needs.*' => ['string', 'max:100'],
            'current_position' => ['nullable', 'string', 'max:120'],
            'assistance_description' => ['nullable', 'string', 'max:2000'],
            'preferred_contact_time' => ['nullable', 'string', 'max:80'],
            'heard_about_poa' => ['nullable', 'string', 'max:120'],
            'consent_to_contact' => ['accepted'],
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
