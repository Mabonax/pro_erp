<?php

namespace App\Domains\CitizenAccess\Requests;

class StoreOfficerIntakeRequest extends PublicIntakeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('domain.citizen-access.manage') ?? false;
    }
}
