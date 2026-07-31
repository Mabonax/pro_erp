<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentItem extends Model
{
    protected $table = 'citizen_access_assessment_items';

    protected $fillable = ['support_case_id', 'requirement_definition_id', 'template_id', 'template_version_id', 'requirement_snapshot', 'status', 'is_blocking', 'evidence_type', 'reason', 'decided_by_user_id', 'decided_at'];

    protected $casts = ['requirement_snapshot' => 'array', 'is_blocking' => 'boolean', 'decided_at' => 'datetime'];

    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class, 'support_case_id');
    }
}
