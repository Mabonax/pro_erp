<?php

namespace App\Domains\Enterprises\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnterprisePersonRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('domain.citizen-access.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'beneficiary_id' => ['nullable', 'integer', 'exists:beneficiaries,id'],
            'person_name' => ['nullable', 'string', 'max:180'],
            'person_email' => ['nullable', 'email', 'max:180'],
            'person_telephone' => ['nullable', 'string', 'max:80'],
            'role' => ['required', Rule::in(['owner', 'director', 'primary_contact', 'authorised_representative', 'employee', 'mentor_advisor'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_primary_contact' => ['sometimes', 'boolean'],
            'is_authorised_representative' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
