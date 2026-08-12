<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PathwayStage extends Model
{
    protected $table = 'citizen_access_pathway_stages';

    protected $fillable = [
        'service_pathway_version_id',
        'name',
        'slug',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ServicePathwayVersion::class, 'service_pathway_version_id');
    }

    protected static function booted(): void
    {
        static::updating(function (PathwayStage $stage) {
            if ($stage->version?->isInUse()) {
                throw new LogicException('Stages on an in-use pathway version are immutable. Create a new version instead.');
            }
        });

        static::deleting(function (PathwayStage $stage) {
            if ($stage->version?->isInUse()) {
                throw new LogicException('Stages on an in-use pathway version cannot be deleted.');
            }
        });
    }
}
