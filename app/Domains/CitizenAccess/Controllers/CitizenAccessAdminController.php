<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\CitizenAccess\Models\ApplicationCycle;
use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\OutcomeDefinition;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\PathwayStage;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServicePathway;
use App\Domains\CitizenAccess\Models\ServicePathwayVersion;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Services\CitizenAccessAuditService;
use App\Domains\CitizenAccess\Services\OpportunityPublicationReadinessService;
use App\Domains\Enterprises\Models\Enterprise;
use App\Domains\Enterprises\Models\EnterprisePersonRole;
use App\Domains\Programs\Models\ProgramCategory;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CitizenAccessAdminController extends Controller
{
    public function __construct(
        private CitizenAccessAuditService $audit,
        private OpportunityPublicationReadinessService $publicationReadiness,
    ) {}

    public function index(): Response
    {
        return Inertia::render('CitizenAccess/Admin/Index', [
            'serviceStreams' => ServiceStream::query()->withCount('opportunities')->orderBy('sort_order')->orderBy('name')->get(),
            'programCategories' => ProgramCategory::query()->orderBy('display_order')->orderBy('name')->get(),
            'servicePathways' => ServicePathway::query()->with(['category:id,name', 'serviceStream:id,name', 'versions.stages', 'versions.outcomeDefinitions'])->orderBy('display_order')->orderBy('name')->get(),
            'servicePathwayVersions' => ServicePathwayVersion::query()->with('pathway:id,name,recipient_type')->where('status', 'active')->orderByDesc('id')->get(),
            'enterprises' => Enterprise::query()->with('people.beneficiary:id,name,surname')->latest()->limit(100)->get(),
            'institutions' => Institution::query()->orderBy('name')->get(),
            'programs' => Program::query()->select('id', 'title', 'status')->orderBy('title')->get(),
            'projects' => Project::query()->select('id', 'program_id', 'name', 'status')->orderBy('name')->get(),
            'projectLocations' => ProjectLocation::query()->with(['project:id,name', 'province:id,name'])->select('id', 'project_id', 'province_id', 'training_venue_address')->orderBy('id')->get(),
            'opportunities' => Opportunity::query()->with(['serviceStream:id,name', 'institution:id,name', 'program:id,title', 'project:id,name,program_id', 'projectLocation:id,project_id,province_id', 'requirementTemplate:id,name', 'servicePathway:id,name', 'servicePathwayVersion:id,label,status'])->orderBy('display_order')->orderBy('name')->get(),
            'cycles' => ApplicationCycle::query()->with('opportunity:id,name')->orderByDesc('id')->get(),
            'templates' => RequirementTemplate::query()->with(['serviceStream:id,name', 'versions.definitions'])->latest()->get(),
        ]);
    }

    public function storeStream(Request $request): RedirectResponse
    {
        $stream = ServiceStream::query()->create($request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'unique:citizen_access_service_streams,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]));
        $this->audit->record('service_stream.created', $stream, $request->user());

        return back()->with('success', 'Service stream created.');
    }

    public function storeInstitution(Request $request): RedirectResponse
    {
        $institution = Institution::query()->create($request->validate([
            'name' => ['required', 'string', 'max:160'],
            'institution_type' => ['required', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:120'],
            'official_website' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));
        $this->audit->record('institution.created', $institution, $request->user());

        return back()->with('success', 'Institution created.');
    }

    public function storeProgramCategory(Request $request): RedirectResponse
    {
        $category = ProgramCategory::query()->create($request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'unique:program_categories,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]));
        $this->audit->record('program_category.created', $category, $request->user());

        return back()->with('success', 'Program category created.');
    }

    public function storePathway(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'program_category_id' => ['nullable', 'integer', 'exists:program_categories,id'],
            'service_stream_id' => ['nullable', 'integer', 'exists:citizen_access_service_streams,id'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', 'unique:citizen_access_service_pathways,slug'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:4000'],
            'recipient_type' => ['required', Rule::in(['person', 'enterprise', 'both'])],
        ]);

        $validated['slug'] = filled($validated['slug'] ?? null) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        $pathway = ServicePathway::query()->create($validated + [
            'status' => 'draft',
            'is_active' => true,
        ]);
        $this->audit->record('service_pathway.created', $pathway, $request->user());

        return back()->with('success', 'Service pathway created.');
    }

    public function storePathwayVersion(Request $request, ServicePathway $pathway): RedirectResponse
    {
        $validated = $request->validate([
            'requirement_template_version_id' => ['nullable', 'integer', 'exists:citizen_access_requirement_template_versions,id'],
            'label' => ['required', 'string', 'max:120'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
            'stages' => ['nullable', 'string', 'max:4000'],
            'outcomes' => ['nullable', 'string', 'max:4000'],
            'activate' => ['sometimes', 'boolean'],
        ]);

        $versionNumber = ((int) $pathway->versions()->max('version_number')) + 1;
        $version = $pathway->versions()->create([
            'requirement_template_version_id' => $validated['requirement_template_version_id'] ?? null,
            'version_number' => $versionNumber,
            'label' => $validated['label'],
            'status' => ($validated['activate'] ?? false) ? 'active' : 'draft',
            'effective_from' => $validated['effective_from'] ?? null,
            'effective_until' => $validated['effective_until'] ?? null,
            'activated_at' => ($validated['activate'] ?? false) ? now() : null,
            'activated_by_user_id' => ($validated['activate'] ?? false) ? $request->user()->id : null,
            'change_summary' => $validated['change_summary'] ?? null,
        ]);

        $this->syncLineItems($version, $validated['stages'] ?? '', 'stage');
        $this->syncLineItems($version, $validated['outcomes'] ?? '', 'outcome');
        $this->audit->record('service_pathway_version.created', $version, $request->user(), ['service_pathway_id' => $pathway->id]);

        return back()->with('success', 'Service pathway version created.');
    }

    public function storeEnterprise(Request $request): RedirectResponse
    {
        $enterprise = Enterprise::query()->create($request->validate([
            'legal_name' => ['required', 'string', 'max:180'],
            'trading_name' => ['nullable', 'string', 'max:180'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'enterprise_type' => ['nullable', 'string', 'max:80'],
            'sector' => ['nullable', 'string', 'max:120'],
            'registration_status' => ['nullable', 'string', 'max:80'],
            'trading_status' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'physical_address' => ['nullable', 'string', 'max:2000'],
            'primary_email' => ['nullable', 'email', 'max:180'],
            'primary_telephone' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));
        $this->audit->record('enterprise.created', $enterprise, $request->user());

        return back()->with('success', 'Enterprise created.');
    }

    public function storeEnterprisePerson(Request $request, Enterprise $enterprise): RedirectResponse
    {
        $validated = $request->validate([
            'beneficiary_id' => ['nullable', 'integer', 'exists:beneficiaries,id'],
            'person_name' => ['nullable', 'string', 'max:180'],
            'person_email' => ['nullable', 'email', 'max:180'],
            'person_telephone' => ['nullable', 'string', 'max:80'],
            'role' => ['required', Rule::in(['owner', 'director', 'primary_contact', 'authorised_representative', 'employee', 'mentor_advisor'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_primary_contact' => ['sometimes', 'boolean'],
            'is_authorised_representative' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $role = $enterprise->people()->create($validated + [
            'is_primary_contact' => (bool) ($validated['is_primary_contact'] ?? false),
            'is_authorised_representative' => (bool) ($validated['is_authorised_representative'] ?? false),
        ]);
        $this->audit->record('enterprise_person_role.created', $role, $request->user(), ['enterprise_id' => $enterprise->id]);

        return back()->with('success', 'Enterprise person role linked.');
    }

    public function storeOpportunity(Request $request): RedirectResponse
    {
        $validated = $this->validateOpportunity($request);
        $opportunity = Opportunity::query()->create($validated);
        $this->audit->record('opportunity.created', $opportunity, $request->user());

        return back()->with('success', 'Opportunity created.');
    }

    public function updateOpportunity(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $validated = $this->validateOpportunity($request, $opportunity);
        $opportunity->update($validated);
        $this->audit->record('opportunity.updated', $opportunity, $request->user(), [
            'is_published' => $opportunity->is_published,
            'public_slug' => $opportunity->public_slug,
        ]);

        return back()->with('success', 'Opportunity updated.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_stream_id' => ['required', 'integer', 'exists:citizen_access_service_streams,id'],
            'institution_id' => ['nullable', 'integer', 'exists:citizen_access_institutions,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:citizen_access_opportunities,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
        ]);

        $template = RequirementTemplate::query()->create($validated + ['status' => 'draft']);
        $version = $template->versions()->create([
            'version_number' => 1,
            'status' => 'published',
            'source_reference' => $validated['source_reference'] ?? 'Development/sample configuration',
            'source_url' => $validated['source_url'] ?? null,
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);
        RequirementDefinition::query()->create([
            'template_version_id' => $version->id,
            'name' => 'Identity and contact information confirmed',
            'description' => 'Confirm minimum identity and contact details before application or referral support.',
            'category' => 'screening',
            'requirement_status' => 'mandatory',
            'evidence_type' => 'identity_or_contact_record',
            'is_blocking' => true,
            'staff_guidance' => 'Use official channels and do not treat internal screening as an institution decision.',
        ]);
        $this->audit->record('template.published', $template, $request->user(), ['version_id' => $version->id]);

        return back()->with('success', 'Requirement template created with an initial published version.');
    }

    private function validateOpportunity(Request $request, ?Opportunity $opportunity = null): array
    {
        $validated = $request->validate([
            'service_stream_id' => ['required', 'integer', 'exists:citizen_access_service_streams,id'],
            'institution_id' => ['nullable', 'integer', 'exists:citizen_access_institutions,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_location_id' => ['nullable', 'integer', 'exists:project_locations,id'],
            'requirement_template_id' => ['nullable', 'integer', 'exists:citizen_access_requirement_templates,id'],
            'service_pathway_id' => ['nullable', 'integer', 'exists:citizen_access_service_pathways,id'],
            'service_pathway_version_id' => ['nullable', 'integer', 'exists:citizen_access_service_pathway_versions,id'],
            'name' => ['required', 'string', 'max:180'],
            'opportunity_type' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'official_url' => ['nullable', 'url', 'max:255'],
            'public_slug' => [
                'nullable',
                'string',
                'max:160',
                Rule::unique('citizen_access_opportunities', 'public_slug')->ignore($opportunity),
            ],
            'public_title' => ['nullable', 'string', 'max:180'],
            'public_summary' => ['nullable', 'string', 'max:2000'],
            'public_help_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'opens_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['is_published'] = (bool) ($validated['is_published'] ?? false);
        $validated['display_order'] = (int) ($validated['display_order'] ?? 0);
        $validated['public_slug'] = filled($validated['public_slug'] ?? null) ? Str::slug($validated['public_slug']) : null;

        if (! empty($validated['service_pathway_version_id']) && empty($validated['service_pathway_id'])) {
            $validated['service_pathway_id'] = ServicePathwayVersion::query()
                ->whereKey($validated['service_pathway_version_id'])
                ->value('service_pathway_id');
        }

        $validated['published_at'] = $validated['is_published']
            ? ($opportunity?->published_at ?? now())
            : null;
        $validated['status'] = $validated['is_published']
            ? 'published'
            : ($validated['is_active'] ? 'ready' : 'draft');
        $validated['archived_at'] = null;

        $this->assertPublishable($validated, $opportunity);

        return $validated;
    }

    private function assertPublishable(array $data, ?Opportunity $opportunity = null): void
    {
        if (! (bool) ($data['is_published'] ?? false)) {
            return;
        }

        $readiness = $this->publicationReadiness->evaluateDraft($data, $opportunity);

        if (! $readiness->ready) {
            throw ValidationException::withMessages($readiness->validationMessages());
        }
    }

    private function syncLineItems(ServicePathwayVersion $version, string $lines, string $type): void
    {
        collect(preg_split('/\r\n|\r|\n/', $lines))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->each(function (string $line, int $index) use ($version, $type) {
                if ($type === 'stage') {
                    PathwayStage::query()->create([
                        'service_pathway_version_id' => $version->id,
                        'name' => $line,
                        'slug' => Str::slug($line),
                        'display_order' => $index + 1,
                    ]);

                    return;
                }

                $parts = array_map('trim', explode('|', $line));
                OutcomeDefinition::query()->create([
                    'service_pathway_version_id' => $version->id,
                    'name' => $parts[0],
                    'outcome_type' => $parts[1] ?? 'service_output',
                    'display_order' => $index + 1,
                ]);
            });
    }
}
