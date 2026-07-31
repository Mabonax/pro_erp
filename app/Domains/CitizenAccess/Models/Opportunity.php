<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    protected $table = 'citizen_access_opportunities';

    protected $fillable = ['service_stream_id', 'institution_id', 'name', 'opportunity_type', 'description', 'official_url', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function serviceStream(): BelongsTo
    {
        return $this->belongsTo(ServiceStream::class, 'service_stream_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(ApplicationCycle::class, 'opportunity_id');
    }
}
