<?php

namespace App\Domains\Programs\Models;

use App\Domains\Committees\Models\Committee;
use App\Domains\Projects\Models\ProgramMilestoneTemplate;
use App\Domains\Projects\Models\Project;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $table = 'programs';

    protected $fillable = [
        'title',
        'program_category_id',
        'code',
        'description',
        'strategic_objective',
        'start_date',
        'end_date',
        'status',
        'budget',
        'funding_source',
        'responsible_committee_id',
        'programme_manager_id',
        'slug',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'budget' => 'decimal:2',
    ];

    public function milestoneTemplates(): HasMany
    {
        return $this->hasMany(ProgramMilestoneTemplate::class, 'program_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProgramCategory::class, 'program_category_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'program_id');
    }

    public function responsibleCommittee(): BelongsTo
    {
        return $this->belongsTo(Committee::class, 'responsible_committee_id');
    }

    public function programmeManager(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'programme_manager_id');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(ProgrammeOutcome::class, 'program_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProgrammeDocument::class, 'program_id');
    }

    public function partnerships(): BelongsToMany
    {
        return $this->belongsToMany(
            ProgrammePartnership::class,
            'programme_partnership_program',
            'program_id',
            'programme_partnership_id'
        )->withTimestamps();
    }
}
