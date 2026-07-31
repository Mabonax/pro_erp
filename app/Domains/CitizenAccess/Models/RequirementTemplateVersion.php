<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequirementTemplateVersion extends Model
{
    protected $table = 'citizen_access_requirement_template_versions';

    protected $fillable = ['template_id', 'version_number', 'status', 'source_reference', 'source_url', 'effective_from', 'effective_until', 'published_at', 'published_by', 'readiness_rules'];

    protected $casts = [
        'version_number' => 'integer',
        'effective_from' => 'date:Y-m-d',
        'effective_until' => 'date:Y-m-d',
        'published_at' => 'datetime',
        'readiness_rules' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(RequirementTemplate::class, 'template_id');
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(RequirementDefinition::class, 'template_version_id')->orderBy('display_order');
    }
}
