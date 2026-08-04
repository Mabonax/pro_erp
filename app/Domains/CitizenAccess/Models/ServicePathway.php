<?php

namespace App\Domains\CitizenAccess\Models;

use App\Domains\Programs\Models\ProgramCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePathway extends Model
{
    protected $table = 'citizen_access_service_pathways';

    protected $fillable = [
        'program_category_id',
        'service_stream_id',
        'name',
        'slug',
        'purpose',
        'description',
        'recipient_type',
        'status',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProgramCategory::class, 'program_category_id');
    }

    public function serviceStream(): BelongsTo
    {
        return $this->belongsTo(ServiceStream::class, 'service_stream_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ServicePathwayVersion::class, 'service_pathway_id');
    }
}
