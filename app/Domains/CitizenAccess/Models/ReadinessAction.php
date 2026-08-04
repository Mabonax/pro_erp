<?php

namespace App\Domains\CitizenAccess\Models;

use App\Domains\TaskManagement\Models\WorkTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadinessAction extends Model
{
    protected $table = 'citizen_access_readiness_actions';

    protected $fillable = ['support_case_id', 'assessment_item_id', 'work_task_id', 'description', 'responsible_party', 'assigned_to_user_id', 'due_date', 'priority', 'status', 'completion_evidence', 'notes'];

    protected $casts = ['due_date' => 'date:Y-m-d'];

    public function workTask(): BelongsTo
    {
        return $this->belongsTo(WorkTask::class, 'work_task_id');
    }

    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class, 'support_case_id');
    }
}
