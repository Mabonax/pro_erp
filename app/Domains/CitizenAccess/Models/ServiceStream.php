<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceStream extends Model
{
    protected $table = 'citizen_access_service_streams';

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'service_stream_id');
    }
}
