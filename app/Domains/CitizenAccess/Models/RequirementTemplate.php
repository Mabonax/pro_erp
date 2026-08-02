<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequirementTemplate extends Model
{
    protected $table = 'citizen_access_requirement_templates';

    protected $fillable = ['service_stream_id', 'institution_id', 'opportunity_id', 'name', 'description', 'status'];

    public function serviceStream(): BelongsTo
    {
        return $this->belongsTo(ServiceStream::class, 'service_stream_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RequirementTemplateVersion::class, 'template_id');
    }

    public function latestPublishedVersion()
    {
        return $this->hasOne(RequirementTemplateVersion::class, 'template_id')
            ->where('status', 'published')
            ->latestOfMany('version_number');
    }
}
