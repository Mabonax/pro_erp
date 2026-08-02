<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeNeed extends Model
{
    protected $table = 'citizen_access_intake_needs';

    protected $fillable = ['intake_id', 'service_stream_id', 'opportunity_id', 'need_key', 'label'];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(ServiceStream::class, 'service_stream_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }
}
