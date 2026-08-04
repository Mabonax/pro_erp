<?php

namespace App\Domains\CitizenAccess\Models;

use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    protected $table = 'citizen_access_opportunities';

    protected $fillable = [
        'service_stream_id',
        'institution_id',
        'program_id',
        'project_id',
        'project_location_id',
        'requirement_template_id',
        'service_pathway_id',
        'service_pathway_version_id',
        'name',
        'opportunity_type',
        'description',
        'official_url',
        'public_slug',
        'public_title',
        'public_summary',
        'public_help_text',
        'is_active',
        'is_published',
        'published_at',
        'opens_on',
        'closes_on',
        'capacity',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'opens_on' => 'date:Y-m-d',
        'closes_on' => 'date:Y-m-d',
        'capacity' => 'integer',
        'display_order' => 'integer',
    ];

    public function serviceStream(): BelongsTo
    {
        return $this->belongsTo(ServiceStream::class, 'service_stream_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function projectLocation(): BelongsTo
    {
        return $this->belongsTo(ProjectLocation::class, 'project_location_id');
    }

    public function requirementTemplate(): BelongsTo
    {
        return $this->belongsTo(RequirementTemplate::class, 'requirement_template_id');
    }

    public function servicePathway(): BelongsTo
    {
        return $this->belongsTo(ServicePathway::class, 'service_pathway_id');
    }

    public function servicePathwayVersion(): BelongsTo
    {
        return $this->belongsTo(ServicePathwayVersion::class, 'service_pathway_version_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(ApplicationCycle::class, 'opportunity_id');
    }

    public function scopePublishedPublic(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_published', true)
            ->whereNotNull('public_slug')
            ->whereNotNull('public_title')
            ->whereNotNull('program_id')
            ->whereNotNull('project_id')
            ->whereNotNull('project_location_id')
            ->whereNotNull('requirement_template_id')
            ->whereHas('serviceStream', fn (Builder $builder) => $builder->where('is_active', true))
            ->whereHas('project', fn (Builder $builder) => $builder->whereColumn('projects.program_id', 'citizen_access_opportunities.program_id'))
            ->whereHas('projectLocation', fn (Builder $builder) => $builder->whereColumn('project_locations.project_id', 'citizen_access_opportunities.project_id'))
            ->whereHas('requirementTemplate.versions', fn (Builder $builder) => $builder->where('status', 'published'))
            ->where(function (Builder $builder) {
                $builder
                    ->whereNull('service_pathway_version_id')
                    ->orWhereHas('servicePathwayVersion', fn (Builder $version) => $version->where('status', 'active'));
            })
            ->orderBy('display_order')
            ->orderBy('public_title')
            ->orderBy('id');
    }

    public function latestPublishedTemplateVersion(): ?RequirementTemplateVersion
    {
        if (! $this->requirement_template_id) {
            return null;
        }

        return RequirementTemplateVersion::query()
            ->where('template_id', $this->requirement_template_id)
            ->where('status', 'published')
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->first();
    }
}
