<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequirementDefinition extends Model
{
    protected $table = 'citizen_access_requirement_definitions';

    protected $fillable = ['template_version_id', 'name', 'description', 'applicant_guidance', 'category', 'requirement_status', 'evidence_type', 'applicability_rules', 'verification_method', 'source_url', 'deadline', 'display_order', 'is_blocking', 'expiry_rule', 'staff_guidance'];

    protected $casts = ['applicability_rules' => 'array', 'expiry_rule' => 'array', 'deadline' => 'date:Y-m-d', 'is_blocking' => 'boolean', 'display_order' => 'integer'];

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(RequirementTemplateVersion::class, 'template_version_id');
    }
}
