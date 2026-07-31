<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\CitizenAccess\Models\ApplicationCycle;
use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Services\CitizenAccessAuditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'opportunities' => Opportunity::query()->with(['serviceStream:id,name', 'institution:id,name'])->orderBy('name')->get(),
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
        $opportunity = Opportunity::query()->create($request->validate([
            'service_stream_id' => ['required', 'integer', 'exists:citizen_access_service_streams,id'],
            'institution_id' => ['nullable', 'integer', 'exists:citizen_access_institutions,id'],
            'name' => ['required', 'string', 'max:180'],
            'opportunity_type' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'official_url' => ['nullable', 'url', 'max:255'],
        ]));
        $this->audit->record('opportunity.created', $opportunity, $request->user());

        return back()->with('success', 'Opportunity created.');
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
}
