<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\ApplicationCycle;
use App\Domains\CitizenAccess\Models\AssessmentItem;
use App\Domains\CitizenAccess\Models\CaseApplication;
use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\ReadinessAction;
use App\Domains\CitizenAccess\Models\RequirementTemplateVersion;
use App\Domains\CitizenAccess\Models\ServicePathwayVersion;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\CitizenAccess\Services\CitizenAccessAuditService;
use App\Domains\CitizenAccess\Services\CitizenAccessCaseService;
use App\Domains\Enterprises\Models\Enterprise;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\TaskManagement\Models\WorkTask;
use App\Domains\TaskManagement\Services\WorkTaskService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupportCaseController extends Controller
{
    public function __construct(
        private CitizenAccessCaseService $service,
        private CitizenAccessAuditService $audit,
        private WorkTaskService $taskService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('CitizenAccess/Cases/Index', [
            'cases' => SupportCase::query()
                ->with(['beneficiary:id,name,surname', 'enterprise:id,legal_name,trading_name', 'serviceStream:id,name', 'servicePathwayVersion.pathway:id,name'])
                ->latest()
                ->paginate(15)
                ->through(fn (SupportCase $case) => $this->mapCase($case)),
        ]);
    }

    public function show(SupportCase $case): Response
    {
        $case->load([
            'beneficiary',
            'enterprise.people.beneficiary:id,name,surname,email,phone',
            'serviceStream',
            'opportunity:id,name,service_pathway_version_id',
            'servicePathwayVersion.pathway:id,name,recipient_type',
            'servicePathwayVersion.stages',
            'servicePathwayVersion.outcomeDefinitions',
            'assessmentItems',
            'readinessActions.workTask:id,title,status,priority,due_date',
            'applications' => fn ($query) => $query->latest(),
        ]);

        return Inertia::render('CitizenAccess/Cases/Show', [
            'caseRecord' => $this->mapCase($case) + [
                'pathway' => $case->servicePathwayVersion ? [
                    'version_id' => $case->servicePathwayVersion->id,
                    'version_label' => $case->servicePathwayVersion->label,
                    'version_number' => $case->servicePathwayVersion->version_number,
                    'pathway_name' => $case->servicePathwayVersion->pathway?->name,
                    'recipient_type' => $case->servicePathwayVersion->pathway?->recipient_type,
                    'stages' => $case->servicePathwayVersion->stages->map(fn ($stage) => [
                        'id' => $stage->id,
                        'name' => $stage->name,
                        'description' => $stage->description,
                    ]),
                    'outcomes' => $case->servicePathwayVersion->outcomeDefinitions->map(fn ($outcome) => [
                        'id' => $outcome->id,
                        'name' => $outcome->name,
                        'outcome_type' => $outcome->outcome_type,
                    ]),
                ] : null,
                'assessment_items' => $case->assessmentItems->map(fn (AssessmentItem $item) => [
                    'id' => $item->id,
                    'name' => $item->requirement_snapshot['name'] ?? 'Requirement',
                    'status' => $item->status,
                    'is_blocking' => $item->is_blocking,
                    'reason' => $item->reason,
                ]),
                'readiness_actions' => $case->readinessActions->map(fn (ReadinessAction $action) => [
                    'id' => $action->id,
                    'description' => $action->description,
                    'status' => $action->status,
                    'priority' => $action->priority,
                    'due_date' => $action->due_date?->format('Y-m-d'),
                    'assigned_to_user_id' => $action->assigned_to_user_id,
                    'work_task_id' => $action->work_task_id,
                    'work_task' => $action->workTask ? [
                        'id' => $action->workTask->id,
                        'title' => $action->workTask->title,
                        'status' => $action->workTask->status,
                        'priority' => $action->workTask->priority,
                        'due_date' => $action->workTask->due_date?->format('Y-m-d'),
                    ] : null,
                ]),
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
            'taskAssignees' => User::query()
                ->whereHas('staffMember')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'taskDepartments' => StaffDepartment::query()->orderBy('name')->get(['id', 'name']),
            'canCreateReadinessTask' => request()->user()?->can('create', WorkTask::class) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('CitizenAccess/Cases/Create', $this->options() + [
            'selectedBeneficiaryId' => $request->integer('beneficiary_id') ?: null,
            'selectedEnterpriseId' => $request->integer('enterprise_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'recipient_type' => $request->input('recipient_type', 'person'),
        ]);

        $validated = $request->validate([
            'recipient_type' => ['required', Rule::in(['person', 'enterprise'])],
            'beneficiary_id' => ['nullable', 'integer', 'exists:beneficiaries,id', 'required_if:recipient_type,person', 'prohibited_if:recipient_type,enterprise'],
            'enterprise_id' => ['nullable', 'integer', 'exists:enterprises,id', 'required_if:recipient_type,enterprise', 'prohibited_if:recipient_type,person'],
            'intake_id' => ['nullable', 'integer', 'exists:citizen_access_intakes,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_location_id' => ['nullable', 'integer', 'exists:project_locations,id'],
            'service_stream_id' => ['required', 'integer', 'exists:citizen_access_service_streams,id'],
            'institution_id' => ['nullable', 'integer', 'exists:citizen_access_institutions,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:citizen_access_opportunities,id'],
            'service_pathway_version_id' => ['nullable', 'integer', 'exists:citizen_access_service_pathway_versions,id'],
            'application_cycle_id' => ['nullable', 'integer', 'exists:citizen_access_application_cycles,id'],
            'template_version_id' => ['nullable', 'integer', 'exists:citizen_access_requirement_template_versions,id'],
            'priority' => ['nullable', 'string', 'max:30'],
        ]);

        if ($validated['recipient_type'] === 'enterprise') {
            $enterprise = Enterprise::query()->findOrFail($validated['enterprise_id']);
            $case = $this->service->createEnterpriseCase($enterprise, $validated, $request->user());
        } else {
            $beneficiary = Beneficiary::query()
                ->with(['projectEnrollments' => fn ($query) => $query->latest('enrolled_at')])
                ->findOrFail($validated['beneficiary_id']);
            $currentEnrollment = $beneficiary->projectEnrollments->firstWhere('project_id', $beneficiary->project_id)
                ?? $beneficiary->projectEnrollments->first();

            $validated['program_id'] = $validated['program_id'] ?? $beneficiary->program_id ?? $beneficiary->project?->program_id;
            $validated['project_id'] = $validated['project_id'] ?? $beneficiary->project_id;
            $validated['project_location_id'] = $validated['project_location_id'] ?? $currentEnrollment?->project_location_id;

            $case = $this->service->createCase($beneficiary, $validated, $request->user());
        }

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

    public function createReadinessTask(Request $request, SupportCase $case, ReadinessAction $action): RedirectResponse
    {
        abort_unless((int) $action->support_case_id === (int) $case->id, 404);
        $this->authorize('create', WorkTask::class);

        if ($action->work_task_id) {
            return redirect()
                ->route('task-management.tasks.show', $action->work_task_id)
                ->with('success', 'Readiness action already has a linked task.');
        }

        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_department_id' => ['nullable', 'integer', 'exists:staff_departments,id'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => ['nullable', 'date'],
        ]);

        $assignedToUserId = $validated['assigned_to_user_id'] ?? $action->assigned_to_user_id ?? $case->assigned_to_user_id;
        $assignedDepartmentId = $validated['assigned_department_id'] ?? null;

        if (! $assignedToUserId && ! $assignedDepartmentId) {
            throw ValidationException::withMessages([
                'assigned_to_user_id' => ['Select an assignee or target department before creating the task.'],
            ]);
        }

        $task = $this->taskService->createTask([
            'title' => 'Resolve readiness gap: '.$case->case_reference,
            'description' => $this->readinessTaskDescription($case, $action),
            'priority' => $validated['priority'],
            'due_date' => $validated['due_date'] ?? $action->due_date?->format('Y-m-d'),
            'project_id' => $case->project_id,
            'program_id' => $case->program_id,
            'assigned_to_user_id' => $assignedToUserId,
            'assigned_department_id' => $assignedDepartmentId,
        ], $request->user());

        $action->update([
            'work_task_id' => $task->id,
            'assigned_to_user_id' => $assignedToUserId,
            'due_date' => $validated['due_date'] ?? $action->due_date,
            'priority' => $validated['priority'],
        ]);

        $this->audit->record('readiness_action.task_created', $action->refresh(), $request->user(), [
            'support_case_id' => $case->id,
            'work_task_id' => $task->id,
        ]);

        return back()->with('success', 'Readiness task created.');
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
            'beneficiaries' => Beneficiary::query()
                ->with(['projectEnrollments' => fn ($query) => $query->latest('enrolled_at')])
                ->select('id', 'name', 'surname', 'program_id', 'project_id')
                ->orderBy('name')
                ->limit(250)
                ->get()
                ->map(function (Beneficiary $beneficiary) {
                    $currentEnrollment = $beneficiary->projectEnrollments->firstWhere('project_id', $beneficiary->project_id)
                        ?? $beneficiary->projectEnrollments->first();

                    return [
                        'id' => $beneficiary->id,
                        'name' => $beneficiary->name,
                        'surname' => $beneficiary->surname,
                        'program_id' => $beneficiary->program_id,
                        'project_id' => $beneficiary->project_id,
                        'project_location_id' => $currentEnrollment?->project_location_id,
                    ];
                }),
            'serviceStreams' => ServiceStream::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'enterprises' => Enterprise::query()->where('is_active', true)->orderBy('legal_name')->limit(250)->get(['id', 'legal_name', 'trading_name', 'primary_email']),
            'institutions' => Institution::query()->where('is_active', true)->orderBy('name')->get(),
            'opportunities' => Opportunity::query()->with('servicePathwayVersion:id,label,service_pathway_id,status')->where('is_active', true)->orderBy('name')->get(),
            'cycles' => ApplicationCycle::query()->where('is_active', true)->orderByDesc('id')->get(),
            'templateVersions' => RequirementTemplateVersion::query()->with('template:id,name')->where('status', 'published')->orderByDesc('id')->get(),
            'pathwayVersions' => ServicePathwayVersion::query()->with('pathway:id,name,recipient_type')->where('status', 'active')->orderByDesc('id')->get(),
        ];
    }

    private function mapCase(SupportCase $case): array
    {
        return [
            'id' => $case->id,
            'case_reference' => $case->case_reference,
            'beneficiary' => $case->beneficiary ? trim($case->beneficiary->name.' '.$case->beneficiary->surname) : null,
            'enterprise' => $case->enterprise ? ($case->enterprise->trading_name ?: $case->enterprise->legal_name) : null,
            'recipient_type' => $case->recipient_type,
            'recipient_name' => $case->recipientName(),
            'service_stream' => $case->serviceStream?->name,
            'service_offering' => $case->opportunity?->name,
            'service_pathway' => $case->servicePathwayVersion?->pathway?->name,
            'service_pathway_version' => $case->servicePathwayVersion?->label,
            'stage' => $case->stage,
            'readiness_state' => $case->readiness_state,
            'readiness_percentage' => $case->readiness_percentage,
            'eligibility_indication' => $case->eligibility_indication,
            'readiness_reasons' => $case->readiness_reasons ?? [],
            'created_at' => $case->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function readinessTaskDescription(SupportCase $case, ReadinessAction $action): string
    {
        return implode("\n\n", array_filter([
            $action->description,
            "Support case: {$case->case_reference}",
            "Recipient: {$case->recipientName()}",
            $case->serviceStream?->name ? "Service stream: {$case->serviceStream->name}" : null,
            $case->servicePathwayVersion?->pathway?->name ? "Service pathway: {$case->servicePathwayVersion->pathway->name}" : null,
            'Complete the readiness gap, update the Citizen Access case, and attach any supporting evidence to the recipient record or task.',
        ]));
    }
}
