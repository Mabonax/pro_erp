<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ServicePathwayVersion extends Model
{
    protected $table = 'citizen_access_service_pathway_versions';

    protected $fillable = [
        'service_pathway_id',
        'requirement_template_version_id',
        'version_number',
        'label',
        'status',
        'effective_from',
        'effective_until',
        'activated_at',
        'activated_by_user_id',
        'change_summary',
        'metadata',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'effective_from' => 'date:Y-m-d',
        'effective_until' => 'date:Y-m-d',
        'activated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function pathway(): BelongsTo
    {
        return $this->belongsTo(ServicePathway::class, 'service_pathway_id');
    }

    public function requirementTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(RequirementTemplateVersion::class, 'requirement_template_version_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PathwayStage::class, 'service_pathway_version_id')->orderBy('display_order');
    }

    public function outcomeDefinitions(): HasMany
    {
        return $this->hasMany(OutcomeDefinition::class, 'service_pathway_version_id')->orderBy('display_order');
    }

    public function supportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class, 'service_pathway_version_id');
    }

    public function isInUse(): bool
    {
        return $this->supportCases()->exists();
    }

    protected static function booted(): void
    {
        static::updating(function (ServicePathwayVersion $version) {
            if ($version->isInUse()) {
                throw new LogicException('Service pathway versions used by support cases are immutable. Create a new version instead.');
            }
        });

        static::deleting(function (ServicePathwayVersion $version) {
            if ($version->isInUse()) {
                throw new LogicException('Service pathway versions used by support cases cannot be deleted.');
            }
        });
    }
}
