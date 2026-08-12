<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OutcomeDefinition extends Model
{
    protected $table = 'citizen_access_outcome_definitions';

    protected $fillable = [
        'service_pathway_version_id',
        'name',
        'outcome_type',
        'description',
        'requires_evidence',
        'is_success_indicator',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'requires_evidence' => 'boolean',
        'is_success_indicator' => 'boolean',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ServicePathwayVersion::class, 'service_pathway_version_id');
    }

    protected static function booted(): void
    {
        static::updating(function (OutcomeDefinition $outcome) {
            if ($outcome->version?->isInUse()) {
                throw new LogicException('Outcomes on an in-use pathway version are immutable. Create a new version instead.');
            }
        });

        static::deleting(function (OutcomeDefinition $outcome) {
            if ($outcome->version?->isInUse()) {
                throw new LogicException('Outcomes on an in-use pathway version cannot be deleted.');
            }
        });
    }
}
