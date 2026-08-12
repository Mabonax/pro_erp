<?php

namespace App\Domains\CitizenAccess\Services;

use App\Domains\CitizenAccess\Data\OpportunityPublicationReadiness;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServicePathwayVersion;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;

class OpportunityPublicationReadinessService
{
    public function evaluate(Opportunity $opportunity): OpportunityPublicationReadiness
    {
        $opportunity->loadMissing([
            'serviceStream:id,is_active,name',
            'program:id,title',
            'project:id,program_id,name,status',
            'projectLocation:id,project_id,province_id',
            'requirementTemplate.versions:id,template_id,status,version_number',
            'servicePathwayVersion:id,service_pathway_id,status,label',
        ]);

        $checks = [
            $this->check('is_active', 'Offering is active', (bool) $opportunity->is_active, 'Activate the offering before publishing.', 'Status section'),
            $this->check('public_slug', 'Public slug present', filled($opportunity->public_slug), 'Add a public slug in Public Details.', 'Public Details section'),
            $this->check('public_title', 'Public title present', filled($opportunity->public_title), 'Add a public title in Public Details.', 'Public Details section'),
            $this->check('is_published', 'Publish action selected', (bool) $opportunity->is_published, 'Use Publish Offering when the other readiness checks pass.', 'Publish Offering action'),
            $this->check('status', 'Publication status is Published', $opportunity->status === 'published', 'Use Publish Offering instead of only saving the form as a draft.', 'Publish Offering action'),
            $this->check('archived_at', 'Offering is not archived', blank($opportunity->archived_at), 'Restore the offering before publishing.', 'Restore Offering action'),
            $this->check('service_stream_id', 'Active service stream assigned', $this->hasActiveServiceStream($opportunity), 'Choose an active service stream.', 'Relationships section'),
            $this->check('program_id', 'Program assigned', filled($opportunity->program_id), 'Choose a program.', 'Relationships section'),
            $this->check('project_id', 'Project assigned to program', $this->hasMatchingProject($opportunity), 'Choose a project that belongs to the selected program.', 'Relationships section'),
            $this->check('project_location_id', 'Project location assigned', $this->hasMatchingProjectLocation($opportunity), 'Choose a project location for the selected project.', 'Relationships section'),
            $this->check('requirement_template_id', 'Published requirement template assigned', $this->hasPublishedRequirementTemplate($opportunity), 'Choose a requirement template with a published version.', 'Relationships section'),
            $this->check('service_pathway_version_id', 'Active pathway/version requirements satisfied', $this->hasValidPathwayVersion($opportunity), 'Choose an active pathway version linked to the pathway.', 'Relationships section'),
            $this->check('dates', 'Required dates valid', $this->hasValidDates($opportunity), 'Set a closing date on or after the opening date.', 'Public Details section'),
        ];

        $errors = collect($checks)
            ->reject(fn (array $check): bool => $check['passes'])
            ->map(fn (array $check): array => [
                'field' => $check['field'],
                'message' => $check['message'],
            ])
            ->values()
            ->all();

        return new OpportunityPublicationReadiness($errors === [], $checks, $errors);
    }

    public function evaluateDraft(array $data, ?Opportunity $opportunity = null): OpportunityPublicationReadiness
    {
        $draft = $opportunity ? $opportunity->replicate() : new Opportunity();
        $draft->forceFill($data);

        return $this->evaluate($draft);
    }

    private function check(string $field, string $label, bool $passes, string $message, string $action): array
    {
        return compact('field', 'label', 'passes', 'message', 'action');
    }

    private function hasActiveServiceStream(Opportunity $opportunity): bool
    {
        return filled($opportunity->service_stream_id)
            && ServiceStream::query()->whereKey($opportunity->service_stream_id)->where('is_active', true)->exists();
    }

    private function hasMatchingProject(Opportunity $opportunity): bool
    {
        return filled($opportunity->program_id)
            && filled($opportunity->project_id)
            && Project::query()
                ->whereKey($opportunity->project_id)
                ->where('program_id', $opportunity->program_id)
                ->exists();
    }

    private function hasMatchingProjectLocation(Opportunity $opportunity): bool
    {
        return filled($opportunity->project_id)
            && filled($opportunity->project_location_id)
            && ProjectLocation::query()
                ->whereKey($opportunity->project_location_id)
                ->where('project_id', $opportunity->project_id)
                ->exists();
    }

    private function hasPublishedRequirementTemplate(Opportunity $opportunity): bool
    {
        return filled($opportunity->requirement_template_id)
            && RequirementTemplate::query()
                ->whereKey($opportunity->requirement_template_id)
                ->whereHas('versions', fn ($query) => $query->where('status', 'published'))
                ->exists();
    }

    private function hasValidPathwayVersion(Opportunity $opportunity): bool
    {
        if (blank($opportunity->service_pathway_version_id)) {
            return true;
        }

        return ServicePathwayVersion::query()
            ->whereKey($opportunity->service_pathway_version_id)
            ->where('status', 'active')
            ->when(
                filled($opportunity->service_pathway_id),
                fn ($query) => $query->where('service_pathway_id', $opportunity->service_pathway_id)
            )
            ->exists();
    }

    private function hasValidDates(Opportunity $opportunity): bool
    {
        if (blank($opportunity->opens_on) || blank($opportunity->closes_on)) {
            return true;
        }

        return $opportunity->closes_on >= $opportunity->opens_on;
    }
}
