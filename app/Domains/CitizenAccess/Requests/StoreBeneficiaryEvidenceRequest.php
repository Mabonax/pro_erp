<?php

namespace App\Domains\CitizenAccess\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBeneficiaryEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evidence_type' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'verification_status' => ['nullable', 'string', 'in:pending,awaiting_verification,verified,rejected'],
            'sensitivity_classification' => ['nullable', 'string', 'max:80'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp', 'max:25600'],
        ];
    }

    public function messages(): array
    {
        return [
            'evidence_type.required' => 'Choose the kind of evidence being uploaded.',
            'file.required' => 'Choose an evidence file to upload.',
            'file.mimes' => 'Evidence must be a PDF, Office document, or image file.',
            'expiry_date.after_or_equal' => 'The expiry date cannot be before the issue date.',
        ];
    }
}
