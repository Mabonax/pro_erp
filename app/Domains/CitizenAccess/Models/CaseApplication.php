<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;

class CaseApplication extends Model
{
    protected $table = 'citizen_access_case_applications';

    protected $fillable = ['support_case_id', 'activity_type', 'official_channel', 'external_reference', 'submission_date', 'assisted_by_user_id', 'submission_evidence_id', 'referral_institution', 'referral_contact', 'follow_up_date', 'external_status', 'outcome_category', 'outcome_date', 'outcome_evidence_id', 'outcome_verification_status', 'closure_reason'];

    protected $casts = ['submission_date' => 'date:Y-m-d', 'follow_up_date' => 'date:Y-m-d', 'outcome_date' => 'date:Y-m-d'];
}
