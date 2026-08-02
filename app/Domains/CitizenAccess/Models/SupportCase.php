<?php

namespace App\Domains\CitizenAccess\Models;

use App\Domains\Beneficiaries\Models\Beneficiary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportCase extends Model
{
    protected $table = 'citizen_access_support_cases';

    protected $fillable = ['case_reference', 'beneficiary_id', 'intake_id', 'program_id', 'project_id', 'project_location_id', 'service_stream_id', 'institution_id', 'opportunity_id', 'application_cycle_id', 'assigned_to_user_id', 'priority', 'stage', 'readiness_state', 'readiness_percentage', 'eligibility_indication', 'readiness_reasons', 'important_deadline', 'closure_reason', 'closed_at', 'closed_by_user_id'];

    protected $casts = ['readiness_percentage' => 'integer', 'readiness_reasons' => 'array', 'important_deadline' => 'date:Y-m-d', 'closed_at' => 'datetime'];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function intake(): BelongsTo
    {
        return $this->belongsTo(Intake::class, 'intake_id');
    }

    public function serviceStream(): BelongsTo
    {
        return $this->belongsTo(ServiceStream::class, 'service_stream_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function assessmentItems(): HasMany
    {
        return $this->hasMany(AssessmentItem::class, 'support_case_id');
    }

    public function readinessActions(): HasMany
    {
        return $this->hasMany(ReadinessAction::class, 'support_case_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CaseApplication::class, 'support_case_id');
    }
}
