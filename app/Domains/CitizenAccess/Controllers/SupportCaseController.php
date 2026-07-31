<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\ApplicationCycle;
use App\Domains\CitizenAccess\Models\AssessmentItem;
use App\Domains\CitizenAccess\Models\CaseApplication;
use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementTemplateVersion;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\CitizenAccess\Services\CitizenAccessAuditService;
use App\Domains\CitizenAccess\Services\CitizenAccessCaseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportCaseController extends Controller
{
    public function __construct(
        private CitizenAccessCaseService $service,
        private CitizenAccessAuditService $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('CitizenAccess/Cases/Index', [
            'cases' => SupportCase::query()
                ->with(['beneficiary:id,name,surname', 'serviceStream:id,name'])
                ->latest()
                ->paginate(15)
                ->through(fn (SupportCase $case) => $this->mapCase($case)),
        ]);
    }

    public function show(SupportCase $case): Response
    {
        $case->load(['beneficiary', 'serviceStream', 'assessmentItems', 'readinessActions', 'applications' => fn ($query) => $query->latest()]);

        return Inertia::render('CitizenAccess/Cases/Show', [
            'caseRecord' => $this->mapCase($case) + [
                'assessment_items' => $case->assessmentItems->map(fn (AssessmentItem $item) => [
                    'id' => $item->id,
                    'name' => $item->requirement_snapshot['name'] ?? 'Requirement',
                    'status' => $item->status,
                    'is_blocking' => $item->is_blocking,
                    'reason' => $item->reason,
                ]),
                'readiness_actions' => $case->readinessActions,
                'activities' => $case->applications->map(fn (CaseApplication $activity) => [
                    'id' => $activity->id,
                    'activity_type' => $activity->activity_type,
                    'official_channel' => $activity->official_channel,
                    'external_reference' => $activity->external_reference,
                    'submission_date' => $activity->submission_date?->format('Y-m-d'),
                    'referral_institution' => $activity->referral_institution,
                    'referral_contact' => $activity->referral_contact,
                    'follow_up_date' => $activity->follow_up_date?->format('Y-m-d'),
                    'external_status' => $activity->external_status,
                    'outcome_category' => $activity->outcome_category,
                    'outcome_date' => $activity->outcome_date?->format('Y-m-d'),
                    'outcome_verification_status' => $activity->outcome_verification_status,
                    'closure_reason' => $activity->closure_reason,
                    'created_at' => $activity->created_at?->format('Y-m-d H:i'),
                ]),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CitizenAccess/Cases/Create', $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'beneficiary_id' => ['required', 'integer', 'exists:beneficiaries,id'],
            'intake_id' => ['nullable', 'integer', 'exists:citizen_access_intakes,id'],
            'service_stream_id' => ['required', 'integer', 'exists:citizen_access_service_streams,id'],
            'institution_id' => ['nullable', 'integer', 'exists:citizen_access_institutions,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:citizen_access_opportunities,id'],
            'application_cycle_id' => ['nullable', 'integer', 'exists:citizen_access_application_cycles,id'],
            'template_version_id' => ['nullable', 'integer', 'exists:citizen_access_requirement_template_versions,id'],
            'priority' => ['nullable', 'string', 'max:30'],
        ]);

        $case = $this->service->createCase(Beneficiary::query()->findOrFail($validated['beneficiary_id']), $validated, $request->user());

        return redirect()->route('citizen-access.cases.show', $case)->with('success', 'Support case created.');
    }

    public function applyTemplate(Request $request, SupportCase $case): RedirectResponse
    {
        $validated = $request->validate(['template_version_id' => ['required', 'integer', 'exists:citizen_access_requirement_template_versions,id']]);
        $this->service->applyTemplate($case, (int) $validated['template_version_id'], $request->user());

        return back()->with('success', 'Requirement snapshot applied.');
    }

    public function assessmentStatus(Request $request, AssessmentItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->updateAssessmentItem($item, $validated['status'], $validated['reason'] ?? null, $request->user());

        return back()->with('success', 'Assessment updated.');
    }

    public function recalculate(Request $request, SupportCase $case): RedirectResponse
    {
        $this->service->recalculateReadiness($case, $request->user());

        return back()->with('success', 'Readiness recalculated.');
    }

    public function storeActivity(Request $request, SupportCase $case): RedirectResponse
    {
        $validated = $request->validate([
            'activity_type' => ['required', 'string', 'in:application,referral,follow_up,outcome'],
            'official_channel' => ['nullable', 'string', 'max:160'],
            'external_reference' => ['nullable', 'string', 'max:160'],
            'submission_date' => ['nullable', 'date'],
            'referral_institution' => ['nullable', 'string', 'max:255'],
            'referral_contact' => ['nullable', 'string', 'max:255'],
            'follow_up_date' => ['nullable', 'date'],
            'external_status' => ['nullable', 'string', 'max:120'],
            'outcome_category' => ['nullable', 'string', 'max:120'],
            'outcome_date' => ['nullable', 'date'],
            'outcome_verification_status' => ['nullable', 'string', 'in:unverified,awaiting_verification,verified,rejected'],
            'closure_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $activity = $case->applications()->create($validated + [
            'assisted_by_user_id' => $request->user()->id,
            'outcome_verification_status' => $validated['outcome_verification_status'] ?? 'unverified',
        ]);

        if ($validated['activity_type'] === 'application') {
            $case->update(['stage' => 'application_submitted']);
        } elseif ($validated['activity_type'] === 'referral') {
            $case->update(['stage' => 'referral_issued']);
        } elseif ($validated['activity_type'] === 'outcome') {
            $case->update(['stage' => 'outcome_recorded']);
        }

        $this->audit->record('case.activity_recorded', $activity, $request->user(), [
            'support_case_id' => $case->id,
            'activity_type' => $validated['activity_type'],
        ]);

        return back()->with('success', 'Case activity recorded.');
    }

    private function options(): array
    {
        return [
            'beneficiaries' => Beneficiary::query()->select('id', 'name', 'surname')->orderBy('name')->limit(250)->get(),
            'serviceStreams' => ServiceStream::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'institutions' => Institution::query()->where('is_active', true)->orderBy('name')->get(),
            'opportunities' => Opportunity::query()->where('is_active', true)->orderBy('name')->get(),
            'cycles' => ApplicationCycle::query()->where('is_active', true)->orderByDesc('id')->get(),
            'templateVersions' => RequirementTemplateVersion::query()->with('template:id,name')->where('status', 'published')->orderByDesc('id')->get(),
        ];
    }

    private function mapCase(SupportCase $case): array
    {
        return [
            'id' => $case->id,
            'case_reference' => $case->case_reference,
            'beneficiary' => $case->beneficiary ? trim($case->beneficiary->name.' '.$case->beneficiary->surname) : null,
            'service_stream' => $case->serviceStream?->name,
            'stage' => $case->stage,
            'readiness_state' => $case->readiness_state,
            'readiness_percentage' => $case->readiness_percentage,
            'eligibility_indication' => $case->eligibility_indication,
            'readiness_reasons' => $case->readiness_reasons ?? [],
            'created_at' => $case->created_at?->format('Y-m-d H:i'),
        ];
    }
}
