<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $table = 'citizen_access_audit_events';

    protected $fillable = ['event_type', 'subject_type', 'subject_id', 'actor_user_id', 'public_reference', 'correlation_id', 'properties'];

    protected $casts = ['properties' => 'array'];
}
