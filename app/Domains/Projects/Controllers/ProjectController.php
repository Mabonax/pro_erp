<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\ProgramMilestoneTemplate;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectClosureEvidence;
use App\Domains\Projects\Models\ProjectEnrollment;
use App\Domains\Projects\Models\ProjectHistory;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Projects\Models\ProjectMilestone;
use App\Domains\Projects\Models\ProjectReport;
use App\Domains\Projects\Requests\StoreProjectRequest;
use App\Domains\Projects\Requests\UpdateProjectRequest;
use App\Domains\Projects\Resources\ProjectResource;
use App\Domains\Projects\Services\ProjectGovernanceService;
use App\Domains\Projects\Services\ProjectProgressService;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Staff\Models\StaffMember;
use App\Domains\Stakeholders\Models\Stakeholder;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $service,
        protected ProjectProgressService $progressService,
        protected ProjectGovernanceService $governanceService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $projects = $this->service->paginateProjects();
        $programs = Program::select('id', 'title')->orderBy('title')->get();
        $stakeholders = Stakeholder::select('id', 'organization_name', 'name')
            ->orderBy('organization_name')
            ->get()
            ->map(fn ($stakeholder) => [
                'id' => $stakeholder->id,
                'name' => trim($stakeholder->organization_name.' - '.$stakeholder->name),
            ]);
        $staffMembers = StaffMember::select('id', 'first_name', 'last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($staff) => [
                'id' => $staff->id,
                'name' => trim($staff->first_name.' '.$staff->last_name),
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'programs' => $programs,
            'stakeholders' => $stakeholders,
            'partnerStakeholders' => $stakeholders,
            'staffMembers' => $staffMembers,
            'canManageProjects' => (bool) $request->user()?->can('create', Project::class),
        ]);
    }

    public function dashboard()
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::with([
            'projectManager',
            'locations.facilitator',
            'locations.province',
            'locations.enrollments.beneficiary',
            'locations.milestoneAssessments',
            'locations.attendanceRegisters.entries',
            'milestones',
        ])
            ->orderByDesc('created_at')
            ->get();
        $portfolio = $this->progressService->summarizePortfolio($projects);

        return Inertia::render('Projects/Dashboard', [
            'stats' => [
                'totalProjects' => Project::count(),
                'activeProjects' => Project::where('status', 'active')->count(),
                'completedProjects' => Project::where('status', 'completed')->count(),
                'totalBeneficiaries' => ProjectEnrollment::count(),
                'totalLocations' => ProjectLocation::count(),
            ],
            'portfolio' => $portfolio,
            'canManageProjects' => (bool) request()->user()?->can('create', Project::class),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Projects/Create', $this->formOptions());
    }

    public function store(StoreProjectRequest $request)
    {
        $this->authorize('create', Project::class);

        $project = $this->service->createProject($request->validated(), $request->user());

        return redirect()->route('projects.show', $project->id)->with('success', 'Project created');
    }

    public function show(Request $request, int $project)
    {
        $model = $this->loadProjectWorkspace($project);

        $this->authorize('view', $model);

        $milestones = ProjectMilestone::with('assessments')
            ->where('project_id', $model->id)
            ->orderBy('sort_order')
            ->get();
        $progress = $this->progressService->summarizeProject($model);

        return Inertia::render('Projects/Show', [
            'project' => new ProjectResource($model),
            'milestones' => $milestones,
            'progress' => $progress,
            'locations' => $progress['locations'],
            'beneficiaryJourney' => $progress['journey'],
            'attendanceTrend' => $this->attendanceTrend($model),
            'history' => $model->history->map(fn (ProjectHistory $history) => app(\App\Domains\Projects\Services\ProjectHistoryService::class)->map($history))->values(),
            'canManageProjects' => (bool) $request->user()?->can('update', $model),
            'finalization' => [
                'href' => route('projects.finalization', $model->id),
                'is_concluded' => (bool) $model->closure,
                'closure_date' => $model->closure?->closure_date?->format('Y-m-d'),
                'evidence_count' => $model->closureEvidence->count(),
                'report_count' => $model->reports->count(),
                'can_manage' => (bool) $request->user()?->can('createReport', $model),
            ],
        ]);
    }

    public function finalization(Request $request, int $project)
    {
        $model = $this->loadProjectWorkspace($project);

        $this->authorize('view', $model);

        return Inertia::render('Projects/Finalization', [
            'project' => new ProjectResource($model),
            'closure' => $this->governanceService->mapClosure($model->closure),
            'closureEvidence' => $model->closureEvidence->map(fn (ProjectClosureEvidence $evidence) => $this->governanceService->mapEvidence($evidence))->values(),
            'reports' => $model->reports->map(fn (ProjectReport $report) => $this->governanceService->mapReport($report))->values(),
            'history' => $model->history->map(fn (ProjectHistory $history) => app(\App\Domains\Projects\Services\ProjectHistoryService::class)->map($history))->values(),
            'canManageProjects' => (bool) $request->user()?->can('update', $model),
            'canManageGovernance' => (bool) $request->user()?->can('createReport', $model),
        ]);
    }

    public function conclude(Request $request, int $project)
    {
        $projectModel = Project::with('partners')->findOrFail($project);
        $this->authorize('conclude', $projectModel);

        $data = $request->validate([
            'closure_date' => 'required|date',
            'signoff_notes' => 'nullable|string|max:4000',
            'final_report_summary' => 'nullable|string|max:4000',
            'report_title' => 'nullable|string|max:255',
            'key_findings' => 'nullable|string|max:4000',
            'recommendations' => 'nullable|string|max:4000',
        ]);

        $this->governanceService->concludeProject($projectModel, $data, $request->user());

        return redirect()->back()->with('success', 'Project concluded and final report generated.');
    }

    public function createReport(Request $request, int $project)
    {
        $projectModel = Project::with('closure')->findOrFail($project);
        $this->authorize('createReport', $projectModel);

        $data = $request->validate([
            'report_type' => 'required|in:progress,final',
            'title' => 'nullable|string|max:255',
            'report_date' => 'required|date',
            'executive_summary' => 'nullable|string|max:4000',
            'key_findings' => 'nullable|string|max:4000',
            'recommendations' => 'nullable|string|max:4000',
        ]);

        $this->governanceService->createReport($projectModel, $data, $request->user());

        return redirect()->back()->with('success', ucfirst($data['report_type']).' report created.');
    }

    public function downloadReport(Request $request, int $project, int $report)
    {
        $projectModel = Project::findOrFail($project);
        $this->authorize('viewReport', $projectModel);

        $reportModel = ProjectReport::with(['project.projectManager', 'createdBy'])
            ->where('project_id', $projectModel->id)
            ->findOrFail($report);

        $pdf = Pdf::loadView('pdf.project-report', [
            'project' => $projectModel->loadMissing(['projectManager', 'program', 'sponsor', 'partners']),
            'report' => $reportModel,
        ])->setPaper('a4', 'portrait');

        $safeTitle = str($reportModel->title)->slug();

        return $pdf->download("project-report-{$safeTitle}.pdf");
    }

    public function addMilestone(Request $request, int $project)
    {
        $data = $request->validate([
            'milestone_template_id' => 'required|exists:program_milestone_templates,id',
        ]);

        $template = ProgramMilestoneTemplate::findOrFail($data['milestone_template_id']);

        $projectModel = Project::findOrFail($project);
        $this->authorize('update', $projectModel);

        if ($template->program_id !== $projectModel->program_id) {
            return redirect()->back()->withErrors([
                'milestone_template_id' => 'Template does not match project program.',
            ]);
        }

        ProjectMilestone::updateOrCreate(
            [
                'project_id' => $project,
                'program_milestone_template_id' => $template->id,
            ],
            [
                'title' => $template->title,
                'description' => $template->description,
                'sort_order' => $template->sort_order,
                'max_score' => $template->max_score,
            ]
        );

        return redirect()->back()->with('success', 'Milestone added');
    }

    public function syncMilestones(int $project)
    {
        $projectModel = Project::findOrFail($project);
        $this->authorize('update', $projectModel);
        $this->service->syncProgramMilestones($projectModel);

        return redirect()->back()->with('success', 'Program milestones synced');
    }

    public function edit(int $project)
    {
        $projectModel = Project::with(['program', 'sponsor', 'partners', 'projectManager'])->findOrFail($project);
        $this->authorize('update', $projectModel);

        return Inertia::render('Projects/Edit', [
            'project' => new ProjectResource($projectModel),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateProjectRequest $request, int $project)
    {
        $projectModel = Project::findOrFail($project);
        $this->authorize('update', $projectModel);

        $updated = $this->service->updateProject($project, $request->validated(), $request->user());

        return redirect()->route('projects.show', $updated->id)->with('success', 'Project updated');
    }

    public function destroy(int $project)
    {
        $projectModel = Project::findOrFail($project);
        $this->authorize('delete', $projectModel);

        $this->service->deleteProject($project);

        return redirect()->back()->with('success', 'Project deleted');
    }

    public function uploadClosureEvidence(Request $request, int $project)
    {
        $projectModel = Project::with('closure')->findOrFail($project);
        $this->authorize('conclude', $projectModel);

        $data = $request->validate([
            'category' => 'nullable|in:evidence,registers',
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'file' => 'required|file|mimes:pdf,doc,docx,png,jpg,jpeg,xlsx,csv|max:10240',
        ]);

        $this->governanceService->uploadClosureEvidence($projectModel, $data, $request->file('file'), $request->user());

        return redirect()->back()->with('success', 'Project evidence uploaded.');
    }

    public function downloadClosureEvidence(Request $request, int $project, int $evidence)
    {
        $projectModel = Project::findOrFail($project);
        $this->authorize('viewReport', $projectModel);

        $evidenceModel = ProjectClosureEvidence::query()
            ->where('project_id', $projectModel->id)
            ->findOrFail($evidence);

        return Storage::disk($evidenceModel->disk)->download($evidenceModel->path, $evidenceModel->file_name);
    }

    public function deleteClosureEvidence(Request $request, int $project, int $evidence)
    {
        $projectModel = Project::findOrFail($project);
        $this->authorize('conclude', $projectModel);

        $evidenceModel = ProjectClosureEvidence::query()
            ->where('project_id', $projectModel->id)
            ->findOrFail($evidence);

        $this->governanceService->deleteClosureEvidence($projectModel, $evidenceModel, $request->user());

        return redirect()->back()->with('success', 'Project evidence removed.');
    }

    protected function formOptions(): array
    {
        $stakeholders = Stakeholder::select('id', 'organization_name', 'name')
            ->orderBy('organization_name')
            ->get()
            ->map(fn ($stakeholder) => [
                'id' => $stakeholder->id,
                'name' => trim($stakeholder->organization_name.' - '.$stakeholder->name),
            ]);

        return [
            'programs' => Program::select('id', 'title')->orderBy('title')->get(),
            'stakeholders' => $stakeholders,
            'partnerStakeholders' => $stakeholders,
            'staffMembers' => StaffMember::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn ($staff) => [
                    'id' => $staff->id,
                    'name' => trim($staff->first_name.' '.$staff->last_name),
                ]),
        ];
    }

    protected function loadProjectWorkspace(int $project): Project
    {
        return Project::with([
            'program',
            'sponsor',
            'partners',
            'projectManager',
            'closure.requestedBy',
            'closure.concludedBy',
            'closure.evidence.uploadedBy',
            'closureEvidence.uploadedBy',
            'history.actor',
            'reports.createdBy',
            'locations.facilitator',
            'locations.province',
            'locations.enrollments.beneficiary',
            'locations.attendanceRegisters.entries',
            'milestones',
        ])->findOrFail($project);
    }

    protected function attendanceTrend(Project $project): array
    {
        return $project->locations
            ->flatMap(fn (ProjectLocation $location) => $location->attendanceRegisters ?? collect())
            ->filter(fn ($register) => ! $register->is_holiday && $register->attendance_date)
            ->groupBy(fn ($register) => $register->attendance_date->format('Y-m-d'))
            ->sortKeys()
            ->map(function ($registers, string $date) {
                $totalEntries = 0;
                $attendedEntries = 0;

                foreach ($registers as $register) {
                    $totalEntries += $register->entries->count();
                    $attendedEntries += $register->entries
                        ->whereIn('status', ['present', 'excused'])
                        ->count();
                }

                $rate = $totalEntries > 0
                    ? round(($attendedEntries / $totalEntries) * 100, 2)
                    : 0;

                return [
                    'date' => $date,
                    'attendance_rate' => $rate,
                ];
            })
            ->values()
            ->all();
    }
}
