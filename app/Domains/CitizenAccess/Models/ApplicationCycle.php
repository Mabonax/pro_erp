<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationCycle extends Model
{
    protected $table = 'citizen_access_application_cycles';

    protected $fillable = ['opportunity_id', 'name', 'opens_on', 'closes_on', 'official_reference', 'source_url', 'is_active'];

    protected $casts = ['opens_on' => 'date:Y-m-d', 'closes_on' => 'date:Y-m-d', 'is_active' => 'boolean'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }
}
