<?php

namespace App\Domains\Enterprises\Models;

use App\Domains\CitizenAccess\Models\EvidenceItem;
use App\Domains\CitizenAccess\Models\SupportCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enterprise extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'legal_name',
        'trading_name',
        'registration_number',
        'enterprise_type',
        'sector',
        'registration_status',
        'trading_status',
        'province',
        'municipality',
        'physical_address',
        'primary_email',
        'primary_telephone',
        'website',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function people(): HasMany
    {
        return $this->hasMany(EnterprisePersonRole::class);
    }

    public function supportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class);
    }

    public function evidenceItems(): HasMany
    {
        return $this->hasMany(EvidenceItem::class);
    }
}
