<?php

namespace App\Domains\Enterprises\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnterpriseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('domain.citizen-access.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:180'],
            'trading_name' => ['nullable', 'string', 'max:180'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'enterprise_type' => ['nullable', 'string', 'max:80'],
            'sector' => ['nullable', 'string', 'max:120'],
            'registration_status' => ['nullable', 'string', 'max:80'],
            'trading_status' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'physical_address' => ['nullable', 'string', 'max:2000'],
            'primary_email' => ['nullable', 'email', 'max:180'],
            'primary_telephone' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
