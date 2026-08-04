<?php

namespace App\Domains\Enterprises\Models;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnterprisePersonRole extends Model
{
    protected $fillable = [
        'enterprise_id',
        'beneficiary_id',
        'user_id',
        'person_name',
        'person_email',
        'person_telephone',
        'role',
        'starts_on',
        'ends_on',
        'is_primary_contact',
        'is_authorised_representative',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'is_primary_contact' => 'boolean',
        'is_authorised_representative' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
