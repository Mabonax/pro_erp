<?php

namespace App\Domains\Enterprises\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnterpriseEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('domain.citizen-access.manage') ?? false;
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
}
