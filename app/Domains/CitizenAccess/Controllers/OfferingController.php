<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\CitizenAccess\Models\ApplicationCycle;
use App\Domains\CitizenAccess\Models\AuditEvent;
use App\Domains\CitizenAccess\Models\IntakeNeed;
use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServicePathway;
use App\Domains\CitizenAccess\Models\ServicePathwayVersion;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\CitizenAccess\Services\CitizenAccessAuditService;
use App\Domains\CitizenAccess\Services\OpportunityPublicationReadinessService;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Staff\Models\StaffMember;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfferingController extends Controller
{
    public function __construct(
        private CitizenAccessAuditService $audit,
        private OpportunityPublicationReadinessService $readiness,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'service_stream_id' => ['nullable', 'integer', 'exists:citizen_access_service_streams,id'],
            'project_location_id' => ['nullable', 'integer', 'exists:project_locations,id'],
            'status' => ['nullable', 'string', 'max:40'],
            'active' => ['nullable', Rule::in(['active', 'inactive'])],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'readiness' => ['nullable', Rule::in(['publishable', 'not_publishable'])],
            'archived' => ['nullable', Rule::in(['with', 'only'])],
        ]);

        $offerings = Opportunity::query()
            ->with($this->offeringRelations())
            ->when(($filters['archived'] ?? null) !== 'with' && ($filters['archived'] ?? null) !== 'only', fn (Builder $query) => $query->whereNull('archived_at'))
            ->when(($filters['archived'] ?? null) === 'only', fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters) {
                $search = $filters['search'];
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('public_slug', 'like', "%{$search}%")
                        ->orWhere('public_title', 'like', "%{$search}%")
                        ->orWhere('public_summary', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['program_id'] ?? null), fn (Builder $query) => $query->where('program_id', $filters['program_id']))
            ->when(filled($filters['project_id'] ?? null), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->when(filled($filters['service_stream_id'] ?? null), fn (Builder $query) => $query->where('service_stream_id', $filters['service_stream_id']))
            ->when(filled($filters['project_location_id'] ?? null), fn (Builder $query) => $query->where('project_location_id', $filters['project_location_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['active'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['active'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when(($filters['visibility'] ?? null) === 'public', fn (Builder $query) => $query->whereNotNull('public_slug')->whereNotNull('public_title'))
            ->when(($filters['visibility'] ?? null) === 'private', fn (Builder $query) => $query->where(function (Builder $builder) {
                $builder->whereNull('public_slug')->orWhereNull('public_title');
            }))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Opportunity $opportunity): array => $this->serializeOffering($opportunity));

        if (($filters['readiness'] ?? null) === 'publishable') {
            $offerings = $offerings->where('readiness.ready', true)->values();
        }

        if (($filters['readiness'] ?? null) === 'not_publishable') {
            $offerings = $offerings->where('readiness.ready', false)->values();
        }

        return Inertia::render('CitizenAccess/Admin/Offerings/Index', [
            'offerings' => $offerings->values(),
            'filters' => $filters,
            'options' => $this->options(),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('CitizenAccess/Admin/Offerings/Form', [
            'mode' => 'create',
            'offering' => null,
            'options' => $this->options(),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedOffering($request);
        $validated['status'] = $validated['status'] ?? 'draft';
        $validated['is_published'] = false;
        $validated['published_at'] = null;
        $validated['archived_at'] = null;

        $opportunity = Opportunity::query()->create($validated);
        $this->audit->record('opportunity.created', $opportunity, $request->user(), [
            'attributes' => Arr::only($validated, $this->auditedFields()),
        ]);

        return redirect()->route('citizen-access.admin.offerings.show', $opportunity)->with('success', 'Offering created as a draft.');
    }

    public function show(Request $request, Opportunity $offering): Response
    {
        $offering->load($this->offeringRelations());

        return Inertia::render('CitizenAccess/Admin/Offerings/Show', [
            'offering' => $this->serializeOffering($offering, includeAudit: true),
            'options' => $this->options(),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function edit(Request $request, Opportunity $offering): Response
    {
        $offering->load($this->offeringRelations());

        return Inertia::render('CitizenAccess/Admin/Offerings/Form', [
            'mode' => 'edit',
            'offering' => $this->serializeOffering($offering),
            'options' => $this->options(),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function update(Request $request, Opportunity $offering): RedirectResponse
    {
        $validated = $this->validatedOffering($request, $offering);
        $before = Arr::only($offering->fresh()->toArray(), $this->auditedFields());

        if ($offering->is_published && ($validated['status'] ?? $offering->status) !== 'published') {
            $validated['is_published'] = false;
            $validated['published_at'] = null;
        }

        $offering->update($validated);
        $after = Arr::only($offering->fresh()->toArray(), $this->auditedFields());

        $this->audit->record('opportunity.updated', $offering, $request->user(), [
            'before' => $before,
            'after' => $after,
        ]);

        return redirect()->route('citizen-access.admin.offerings.show', $offering)->with('success', 'Offering updated.');
    }

    public function publish(Request $request, Opportunity $offering): RedirectResponse
    {
        $offering->forceFill([
            'is_active' => true,
            'is_published' => true,
            'status' => 'published',
            'published_at' => $offering->published_at ?? now(),
            'archived_at' => null,
        ]);

        $readiness = $this->readiness->evaluate($offering);

        if (! $readiness->ready) {
            throw ValidationException::withMessages($readiness->validationMessages());
        }

        $offering->save();
        $this->audit->record('opportunity.published', $offering, $request->user(), ['readiness' => $readiness->toArray()]);

        return back()->with('success', 'Offering published.');
    }

    public function unpublish(Request $request, Opportunity $offering): RedirectResponse
    {
        $offering->update([
            'is_published' => false,
            'published_at' => null,
            'status' => 'unpublished',
        ]);
        $this->audit->record('opportunity.unpublished', $offering, $request->user());

        return back()->with('success', 'Offering unpublished.');
    }

    public function activate(Request $request, Opportunity $offering): RedirectResponse
    {
        $offering->update([
            'is_active' => true,
            'status' => $offering->status === 'draft' ? 'ready' : $offering->status,
        ]);
        $this->audit->record('opportunity.activated', $offering, $request->user());

        return back()->with('success', 'Offering activated.');
    }

    public function deactivate(Request $request, Opportunity $offering): RedirectResponse
    {
        $offering->update([
            'is_active' => false,
            'is_published' => false,
            'published_at' => null,
            'status' => 'unpublished',
        ]);
        $this->audit->record('opportunity.deactivated', $offering, $request->user());

        return back()->with('success', 'Offering deactivated.');
    }

    public function archive(Request $request, Opportunity $offering): RedirectResponse
    {
        $offering->update([
            'is_active' => false,
            'is_published' => false,
            'published_at' => null,
            'status' => 'archived',
            'archived_at' => now(),
        ]);
        $this->audit->record('opportunity.archived', $offering, $request->user());

        return redirect()->route('citizen-access.admin.offerings.index')->with('success', 'Offering archived.');
    }

    public function restore(Request $request, Opportunity $offering): RedirectResponse
    {
        $offering->update([
            'status' => 'draft',
            'archived_at' => null,
            'is_published' => false,
            'published_at' => null,
        ]);
        $this->audit->record('opportunity.restored', $offering, $request->user());

        return back()->with('success', 'Offering restored as a draft.');
    }

    public function duplicate(Request $request, Opportunity $offering): RedirectResponse
    {
        $copy = $offering->replicate([
            'public_slug',
            'public_title',
            'is_published',
            'published_at',
            'archived_at',
            'status',
        ]);
        $copy->name = $offering->name.' copy';
        $copy->public_slug = null;
        $copy->public_title = $offering->public_title ? $offering->public_title.' copy' : null;
        $copy->is_published = false;
        $copy->published_at = null;
        $copy->archived_at = null;
        $copy->status = 'draft';
        $copy->save();

        $this->audit->record('opportunity.cloned', $copy, $request->user(), ['source_opportunity_id' => $offering->id]);

        return redirect()->route('citizen-access.admin.offerings.edit', $copy)->with('success', 'Offering cloned as a draft.');
    }

    public function destroy(Request $request, Opportunity $offering): RedirectResponse
    {
        $validated = $request->validate(['hard_delete' => ['sometimes', 'boolean']]);

        if (! (bool) ($validated['hard_delete'] ?? false)) {
            return $this->archive($request, $offering);
        }

        $references = $this->historicalReferences($offering);
        if ($references !== []) {
            throw ValidationException::withMessages([
                'hard_delete' => ['This offering cannot be deleted because it has historical references: '.implode(', ', $references).'. Archive it instead.'],
            ]);
        }

        $properties = Arr::only($offering->toArray(), $this->auditedFields());
        $this->audit->record('opportunity.deleted', $offering, $request->user(), $properties);
        $offering->delete();

        return redirect()->route('citizen-access.admin.offerings.index')->with('success', 'Offering deleted.');
    }

    private function validatedOffering(Request $request, ?Opportunity $offering = null): array
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
            'owner_staff_id' => ['nullable', 'integer', 'exists:staff_members,id'],
            'facilitator_id' => ['nullable', 'integer', 'exists:facilitators,id'],
            'name' => ['required', 'string', 'max:180'],
            'opportunity_type' => ['required', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['draft', 'ready', 'published', 'unpublished', 'archived'])],
            'description' => ['nullable', 'string', 'max:4000'],
            'delivery_channel' => ['nullable', 'string', 'max:80'],
            'delivery_mode' => ['nullable', Rule::in(['physical', 'online', 'hybrid'])],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:160'],
            'official_url' => ['nullable', 'url', 'max:255'],
            'external_provider' => ['nullable', 'string', 'max:255'],
            'contact_reference' => ['nullable', 'string', 'max:255'],
            'public_slug' => [
                'nullable',
                'string',
                'max:160',
                Rule::unique('citizen_access_opportunities', 'public_slug')->ignore($offering),
            ],
            'public_title' => ['nullable', 'string', 'max:180'],
            'public_summary' => ['nullable', 'string', 'max:2000'],
            'public_help_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'opens_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['display_order'] = (int) ($validated['display_order'] ?? 0);
        $validated['public_slug'] = filled($validated['public_slug'] ?? null) ? Str::slug($validated['public_slug']) : null;
        $validated['metadata'] = array_filter($validated['metadata'] ?? [], fn ($value): bool => filled($value));

        if (! empty($validated['service_pathway_version_id']) && empty($validated['service_pathway_id'])) {
            $validated['service_pathway_id'] = ServicePathwayVersion::query()
                ->whereKey($validated['service_pathway_version_id'])
                ->value('service_pathway_id');
        }

        if (($validated['status'] ?? null) === 'published') {
            $draft = array_merge($offering?->toArray() ?? [], $validated, [
                'is_published' => true,
                'published_at' => $offering?->published_at ?? now(),
                'archived_at' => null,
            ]);
            $readiness = $this->readiness->evaluateDraft($draft, $offering);

            if (! $readiness->ready) {
                throw ValidationException::withMessages($readiness->validationMessages());
            }

            $validated['is_published'] = true;
            $validated['published_at'] = $offering?->published_at ?? now();
            $validated['archived_at'] = null;
        } elseif (($validated['status'] ?? null) === 'archived') {
            $validated['is_active'] = false;
            $validated['is_published'] = false;
            $validated['published_at'] = null;
            $validated['archived_at'] = $offering?->archived_at ?? now();
        } else {
            $validated['is_published'] = false;
            $validated['published_at'] = null;
            $validated['archived_at'] = null;
        }

        return $validated;
    }

    private function options(): array
    {
        return [
            'serviceStreams' => ServiceStream::query()->select('id', 'name', 'slug', 'is_active')->orderBy('sort_order')->orderBy('name')->get(),
            'institutions' => Institution::query()->select('id', 'name', 'institution_type')->orderBy('name')->get(),
            'programs' => Program::query()->select('id', 'title', 'code', 'status')->orderBy('title')->get(),
            'projects' => Project::query()->select('id', 'program_id', 'name', 'project_code', 'status')->orderBy('name')->get(),
            'projectLocations' => ProjectLocation::query()->with(['project:id,name', 'province:id,name'])->select('id', 'project_id', 'province_id', 'training_venue_address')->orderBy('id')->get(),
            'templates' => RequirementTemplate::query()->with('latestPublishedVersion:id,template_id,version_number,status')->select('id', 'service_stream_id', 'name', 'status')->orderBy('name')->get(),
            'servicePathways' => ServicePathway::query()->select('id', 'name', 'slug', 'recipient_type', 'status')->orderBy('name')->get(),
            'servicePathwayVersions' => ServicePathwayVersion::query()->with('pathway:id,name')->select('id', 'service_pathway_id', 'label', 'status')->orderByDesc('id')->get(),
            'staffOwners' => StaffMember::query()->select('id', 'first_name', 'last_name', 'email', 'status')->orderBy('first_name')->orderBy('last_name')->get(),
            'facilitators' => Facilitator::query()->select('id', 'name', 'surname', 'email')->orderBy('name')->orderBy('surname')->get(),
        ];
    }

    private function serializeOffering(Opportunity $opportunity, bool $includeAudit = false): array
    {
        $data = $opportunity->toArray();
        $data['readiness'] = $this->readiness->evaluate($opportunity)->toArray();
        $data['historical_references'] = $this->historicalReferences($opportunity);

        if ($includeAudit) {
            $data['audit_events'] = AuditEvent::query()
                ->where('subject_type', Opportunity::class)
                ->where('subject_id', $opportunity->id)
                ->latest()
                ->limit(25)
                ->get(['id', 'event_type', 'actor_user_id', 'properties', 'created_at']);
        }

        return $data;
    }

    private function historicalReferences(Opportunity $offering): array
    {
        $references = [];

        if (SupportCase::query()->where('opportunity_id', $offering->id)->exists()) {
            $references[] = 'support cases';
        }

        if (IntakeNeed::query()->where('opportunity_id', $offering->id)->exists()) {
            $references[] = 'intake needs';
        }

        if (ApplicationCycle::query()->where('opportunity_id', $offering->id)->exists()) {
            $references[] = 'application cycles';
        }

        return $references;
    }

    private function permissions(Request $request): array
    {
        return [
            'view' => $request->user()?->can('citizen-access.offerings.view') || $request->user()?->can('domain.citizen-access.view') || $request->user()?->can('domain.citizen-access.manage'),
            'create' => $request->user()?->can('citizen-access.offerings.create') || $request->user()?->can('domain.citizen-access.manage'),
            'update' => $request->user()?->can('citizen-access.offerings.update') || $request->user()?->can('domain.citizen-access.manage'),
            'publish' => $request->user()?->can('citizen-access.offerings.publish') || $request->user()?->can('domain.citizen-access.manage'),
            'archive' => $request->user()?->can('citizen-access.offerings.archive') || $request->user()?->can('domain.citizen-access.manage'),
            'delete' => $request->user()?->can('citizen-access.offerings.delete') || $request->user()?->can('domain.citizen-access.manage'),
        ];
    }

    private function offeringRelations(): array
    {
        return [
            'serviceStream:id,name,slug,is_active',
            'institution:id,name,institution_type',
            'program:id,title,code,status',
            'project:id,name,project_code,program_id,status',
            'projectLocation:id,project_id,province_id,training_venue_address',
            'projectLocation.province:id,name',
            'requirementTemplate:id,name,status',
            'requirementTemplate.versions:id,template_id,version_number,status',
            'servicePathway:id,name,slug,recipient_type,status',
            'servicePathwayVersion:id,label,status,service_pathway_id',
            'ownerStaff:id,first_name,last_name,email,status',
            'facilitator:id,name,surname,email',
        ];
    }

    private function auditedFields(): array
    {
        return [
            'service_stream_id',
            'institution_id',
            'program_id',
            'project_id',
            'project_location_id',
            'requirement_template_id',
            'service_pathway_id',
            'service_pathway_version_id',
            'owner_staff_id',
            'facilitator_id',
            'name',
            'opportunity_type',
            'status',
            'description',
            'delivery_channel',
            'delivery_mode',
            'target_audience',
            'province',
            'municipality',
            'official_url',
            'external_provider',
            'contact_reference',
            'public_slug',
            'public_title',
            'public_summary',
            'public_help_text',
            'is_active',
            'is_published',
            'published_at',
            'archived_at',
            'opens_on',
            'closes_on',
            'capacity',
            'display_order',
            'notes',
            'metadata',
        ];
    }
}
