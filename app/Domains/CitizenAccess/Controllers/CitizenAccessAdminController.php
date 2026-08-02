<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\CitizenAccess\Models\ApplicationCycle;
use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Services\CitizenAccessAuditService;
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
    public function __construct(private CitizenAccessAuditService $audit) {}

    public function index(): Response
    {
        return Inertia::render('CitizenAccess/Admin/Index', [
            'serviceStreams' => ServiceStream::query()->withCount('opportunities')->orderBy('sort_order')->orderBy('name')->get(),
            'institutions' => Institution::query()->orderBy('name')->get(),
            'programs' => Program::query()->select('id', 'title', 'status')->orderBy('title')->get(),
            'projects' => Project::query()->select('id', 'program_id', 'name', 'status')->orderBy('name')->get(),
            'projectLocations' => ProjectLocation::query()->with(['project:id,name', 'province:id,name'])->select('id', 'project_id', 'province_id', 'training_venue_address')->orderBy('id')->get(),
            'opportunities' => Opportunity::query()->with(['serviceStream:id,name', 'institution:id,name', 'program:id,title', 'project:id,name,program_id', 'projectLocation:id,project_id,province_id', 'requirementTemplate:id,name'])->orderBy('display_order')->orderBy('name')->get(),
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
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['is_published'] = (bool) ($validated['is_published'] ?? false);
        $validated['display_order'] = (int) ($validated['display_order'] ?? 0);
        $validated['public_slug'] = filled($validated['public_slug'] ?? null) ? Str::slug($validated['public_slug']) : null;
        $validated['published_at'] = $validated['is_published']
            ? ($opportunity?->published_at ?? now())
            : null;

        $this->assertPublishable($validated, $opportunity);

        return $validated;
    }

    private function assertPublishable(array $data, ?Opportunity $opportunity = null): void
    {
        if (! (bool) ($data['is_published'] ?? false)) {
            return;
        }

        $messages = [];

        foreach ([
            'service_stream_id' => 'Choose an active service stream before publishing.',
            'program_id' => 'Choose a programme before publishing.',
            'project_id' => 'Choose a project before publishing.',
            'project_location_id' => 'Choose a project location before publishing.',
            'requirement_template_id' => 'Choose a requirement template before publishing.',
            'public_slug' => 'Set a public slug before publishing.',
            'public_title' => 'Set a public title before publishing.',
        ] as $field => $message) {
            if (blank($data[$field] ?? null)) {
                $messages[$field][] = $message;
            }
        }

        if (! ($data['is_active'] ?? false)) {
            $messages['is_active'][] = 'An offering must be active before it can be published.';
        }

        if (! empty($data['service_stream_id']) && ! ServiceStream::query()->whereKey($data['service_stream_id'])->where('is_active', true)->exists()) {
            $messages['service_stream_id'][] = 'The selected service stream is not active.';
        }

        if (! empty($data['project_id']) && ! empty($data['program_id']) && ! Project::query()->whereKey($data['project_id'])->where('program_id', $data['program_id'])->exists()) {
            $messages['project_id'][] = 'The selected project must belong to the selected programme.';
        }

        if (! empty($data['project_location_id']) && ! empty($data['project_id']) && ! ProjectLocation::query()->whereKey($data['project_location_id'])->where('project_id', $data['project_id'])->exists()) {
            $messages['project_location_id'][] = 'The selected project location must belong to the selected project.';
        }

        if (! empty($data['requirement_template_id']) && ! RequirementTemplate::query()
            ->whereKey($data['requirement_template_id'])
            ->whereHas('versions', fn ($query) => $query->where('status', 'published'))
            ->exists()) {
            $messages['requirement_template_id'][] = 'The selected requirement template must have a published version.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }
}
