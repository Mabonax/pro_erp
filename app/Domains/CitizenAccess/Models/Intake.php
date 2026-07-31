<?php

namespace App\Domains\CitizenAccess\Models;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intake extends Model
{
    protected $table = 'citizen_access_intakes';

    protected $fillable = ['public_reference', 'status', 'source_channel', 'campaign_source', 'first_name', 'surname', 'identity_hash', 'identity_last_four', 'date_of_birth', 'mobile_number', 'email', 'preferred_contact_method', 'province', 'municipality', 'ward_area', 'assistance_description', 'preferred_delivery_channel', 'consent_to_contact', 'privacy_notice_accepted', 'consent_recorded_at', 'privacy_notice_version', 'submission_ip_hash', 'user_agent', 'assigned_to_user_id', 'priority', 'duplicate_candidates', 'converted_beneficiary_id', 'linked_beneficiary_id', 'converted_at', 'converted_by_user_id', 'idempotency_key', 'correlation_id', 'meta'];

    protected $casts = ['date_of_birth' => 'date:Y-m-d', 'consent_to_contact' => 'boolean', 'privacy_notice_accepted' => 'boolean', 'consent_recorded_at' => 'datetime', 'duplicate_candidates' => 'array', 'converted_at' => 'datetime', 'meta' => 'array'];

    public function needs(): HasMany
    {
        return $this->hasMany(IntakeNeed::class, 'intake_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function convertedBeneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class, 'converted_beneficiary_id');
    }
}
