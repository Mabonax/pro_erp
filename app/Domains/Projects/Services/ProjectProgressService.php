<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectEnrollment;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\CitizenAccess\Models\AssessmentItem;
use App\Domains\CitizenAccess\Models\SupportCase;
use Illuminate\Support\Collection;

class ProjectProgressService
{
    public function summarizeProject(Project $project): array
    {
        $project->loadMissing([
            'projectManager',
            'locations.facilitator',
            'locations.province',
            'locations.enrollments.beneficiary.evidenceItems',
            'locations.enrollments.beneficiary.supportCases.assessmentItems',
            'locations.enrollments.beneficiary.supportCases.readinessActions.workTask',
            'locations.enrollments.beneficiary.milestoneAssessments',
            'locations.milestoneAssessments',
            'locations.attendanceRegisters.entries',
            'milestones',
        ]);

        $totalMilestones = $project->milestones->count();
        $locationSummaries = $project->locations
            ->map(fn (ProjectLocation $location) => $this->summarizeLocation($location, $totalMilestones))
            ->values();

        $overallExpectedAssessments = (int) $locationSummaries->sum('expected_assessments');
        $overallCompletedAssessments = (int) $locationSummaries->sum('completed_assessments');
        $overallActiveBeneficiaries = (int) $locationSummaries->sum('active_beneficiaries');
        $overallCompletedBeneficiaries = (int) $locationSummaries->sum('completed_beneficiaries');
        $overallAttendanceEntries = (int) $locationSummaries->sum('attendance_entries');
        $overallAttendedEntries = (int) $locationSummaries->sum('attended_entries');

        $blockers = [];

        if ($project->locations->isEmpty()) {
            $blockers[] = 'No delivery locations have been added to this project yet.';
        }

        if ($totalMilestones === 0) {
            $blockers[] = 'No project milestones are attached yet.';
        }

        if ($overallActiveBeneficiaries === 0) {
            $blockers[] = 'No active beneficiaries are enrolled across project locations.';
        }

        if ($overallAttendanceEntries === 0) {
            $blockers[] = 'No attendance has been captured across project locations yet.';
        }

        return [
            'summary' => [
                'project_manager_name' => $project->projectManager
                    ? trim($project->projectManager->first_name.' '.$project->projectManager->last_name)
                    : null,
                'total_locations' => $project->locations->count(),
                'total_milestones' => $totalMilestones,
                'total_beneficiaries' => (int) $locationSummaries->sum('total_beneficiaries'),
                'active_beneficiaries' => $overallActiveBeneficiaries,
                'completed_beneficiaries' => $overallCompletedBeneficiaries,
                'dropped_beneficiaries' => (int) $locationSummaries->sum('dropped_beneficiaries'),
                'expected_assessments' => $overallExpectedAssessments,
                'completed_assessments' => $overallCompletedAssessments,
                'registers_captured' => (int) $locationSummaries->sum('registers_captured'),
                'attendance_rate' => $this->percentage($overallAttendedEntries, $overallAttendanceEntries),
                'milestone_completion_rate' => $this->percentage($overallCompletedAssessments, $overallExpectedAssessments),
                'beneficiary_completion_rate' => $this->percentage($overallCompletedBeneficiaries, $overallActiveBeneficiaries),
                'blocked_locations' => $locationSummaries->where('is_blocked', true)->count(),
                'blockers' => $blockers,
            ],
            'locations' => $locationSummaries->all(),
            'journey' => $this->summarizeBeneficiaryJourney($project, $locationSummaries),
        ];
    }

    public function summarizePortfolio(Collection $projects): array
    {
        $projectSummaries = $projects
            ->map(function (Project $project) {
                $progress = $this->summarizeProject($project);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'project_manager_name' => $progress['summary']['project_manager_name'],
                    'total_locations' => $progress['summary']['total_locations'],
                    'active_beneficiaries' => $progress['summary']['active_beneficiaries'],
                    'milestone_completion_rate' => $progress['summary']['milestone_completion_rate'],
                    'beneficiary_completion_rate' => $progress['summary']['beneficiary_completion_rate'],
                    'attendance_rate' => $progress['summary']['attendance_rate'],
                    'blocked_locations' => $progress['summary']['blocked_locations'],
                ];
            })
            ->values();

        return [
            'projects' => $projectSummaries->all(),
            'stats' => [
                'tracked_projects' => $projectSummaries->count(),
                'average_milestone_completion_rate' => round((float) $projectSummaries->avg('milestone_completion_rate'), 2),
                'average_beneficiary_completion_rate' => round((float) $projectSummaries->avg('beneficiary_completion_rate'), 2),
                'average_attendance_rate' => round((float) $projectSummaries->avg('attendance_rate'), 2),
                'blocked_locations' => (int) $projectSummaries->sum('blocked_locations'),
            ],
        ];
    }

    protected function summarizeLocation(ProjectLocation $location, int $totalMilestones): array
    {
        $totalBeneficiaries = $location->enrollments->count();

        $activeEnrollments = $location->enrollments->filter(function (ProjectEnrollment $enrollment) {
            return in_array($enrollment->status, ['enrolled', 'completed'], true)
                && $enrollment->beneficiary?->attendance_status === 'active';
        })->values();

        $droppedBeneficiaries = $location->enrollments->filter(function (ProjectEnrollment $enrollment) {
            return $enrollment->status === 'dropped'
                || $enrollment->beneficiary?->attendance_status === 'dropout';
        })->count();

        $completedBeneficiaries = $activeEnrollments->filter(function (ProjectEnrollment $enrollment) use ($location, $totalMilestones) {
            if ($totalMilestones === 0) {
                return false;
            }

            $completedAssessments = $location->milestoneAssessments
                ->where('beneficiary_id', $enrollment->beneficiary_id)
                ->where('status', 'completed')
                ->pluck('project_milestone_id')
                ->unique()
                ->count();

            return $completedAssessments >= $totalMilestones;
        })->count();

        $expectedAssessments = $activeEnrollments->count() * $totalMilestones;
        $completedAssessments = $location->milestoneAssessments->where('status', 'completed')->count();
        $failedAssessments = $location->milestoneAssessments->where('status', 'failed')->count();
        $attendanceEntries = 0;
        $attendedEntries = 0;

        foreach ($location->attendanceRegisters as $register) {
            if ($register->is_holiday) {
                continue;
            }

            $attendanceEntries += $register->entries->count();
            $attendedEntries += $register->entries
                ->whereIn('status', ['present', 'excused'])
                ->count();
        }

        $attendanceRate = $this->percentage($attendedEntries, $attendanceEntries);
        $milestoneCompletionRate = $this->percentage($completedAssessments, $expectedAssessments);
        $beneficiaryCompletionRate = $this->percentage($completedBeneficiaries, $activeEnrollments->count());

        $blockers = [];

        if (! $location->facilitator_id) {
            $blockers[] = 'No facilitator is assigned to this location.';
        }

        if ($activeEnrollments->isEmpty()) {
            $blockers[] = 'No active beneficiaries are enrolled at this location.';
        }

        if ($attendanceEntries === 0) {
            $blockers[] = 'Attendance has not been captured for this location.';
        }

        if ($expectedAssessments > 0 && $completedAssessments < $expectedAssessments) {
            $blockers[] = 'Milestone delivery is still incomplete at this location.';
        }

        return [
            'id' => $location->id,
            'location' => $location->province?->name,
            'facilitator_name' => $location->facilitator
                ? trim($location->facilitator->name.' '.$location->facilitator->surname)
                : null,
            'training_venue_address' => $location->training_venue_address,
            'total_beneficiaries' => $totalBeneficiaries,
            'active_beneficiaries' => $activeEnrollments->count(),
            'completed_beneficiaries' => $completedBeneficiaries,
            'dropped_beneficiaries' => $droppedBeneficiaries,
            'total_milestones' => $totalMilestones,
            'expected_assessments' => $expectedAssessments,
            'completed_assessments' => $completedAssessments,
            'failed_assessments' => $failedAssessments,
            'registers_captured' => $location->attendanceRegisters->where('is_holiday', false)->count(),
            'attendance_entries' => $attendanceEntries,
            'attended_entries' => $attendedEntries,
            'attendance_rate' => $attendanceRate,
            'milestone_completion_rate' => $milestoneCompletionRate,
            'beneficiary_completion_rate' => $beneficiaryCompletionRate,
            'is_blocked' => $blockers !== [],
            'blockers' => $blockers,
        ];
    }

    protected function summarizeBeneficiaryJourney(Project $project, Collection $locationSummaries): array
    {
        $locationJourney = $project->locations
            ->map(function (ProjectLocation $location) use ($project, $locationSummaries) {
                $locationProgress = $locationSummaries->firstWhere('id', $location->id) ?? [];
                $beneficiaryRows = $location->enrollments
                    ->filter(fn (ProjectEnrollment $enrollment) => $enrollment->beneficiary !== null)
                    ->map(fn (ProjectEnrollment $enrollment) => $this->summarizeBeneficiaryJourneyRow($project, $location, $enrollment))
                    ->sortByDesc(fn (array $row) => $row['risk_score'])
                    ->values();

                return [
                    'location_id' => $location->id,
                    'location' => $locationProgress['location'] ?? $location->province?->name,
                    'active_beneficiaries' => $locationProgress['active_beneficiaries'] ?? 0,
                    'attendance_rate' => $locationProgress['attendance_rate'] ?? 0,
                    'open_support_cases' => (int) $beneficiaryRows->sum('open_support_cases'),
                    'evidence_gaps' => (int) $beneficiaryRows->sum('evidence_gap_count'),
                    'open_readiness_actions' => (int) $beneficiaryRows->sum('open_readiness_actions'),
                    'completed_milestone_assessments' => (int) $beneficiaryRows->sum('completed_milestone_assessments'),
                    'at_risk_beneficiaries' => $beneficiaryRows
                        ->filter(fn (array $row) => $row['risk_score'] > 0)
                        ->take(10)
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        return [
            'summary' => [
                'open_support_cases' => (int) $locationJourney->sum('open_support_cases'),
                'evidence_gaps' => (int) $locationJourney->sum('evidence_gaps'),
                'open_readiness_actions' => (int) $locationJourney->sum('open_readiness_actions'),
                'locations_with_risks' => $locationJourney
                    ->filter(fn (array $location) => $location['evidence_gaps'] > 0 || $location['open_readiness_actions'] > 0 || count($location['at_risk_beneficiaries']) > 0)
                    ->count(),
            ],
            'locations' => $locationJourney->all(),
        ];
    }

    protected function summarizeBeneficiaryJourneyRow(Project $project, ProjectLocation $location, ProjectEnrollment $enrollment): array
    {
        $beneficiary = $enrollment->beneficiary;
        $supportCases = $beneficiary->supportCases
            ->filter(fn (SupportCase $case) => (int) ($case->project_id ?? 0) === (int) $project->id)
            ->values();
        $assessmentItems = $supportCases->flatMap(fn (SupportCase $case) => $case->assessmentItems);
        $readinessActions = $supportCases->flatMap(fn (SupportCase $case) => $case->readinessActions);
        $missingEvidenceTypes = $assessmentItems
            ->filter(fn (AssessmentItem $item) => $item->is_blocking && ! in_array($item->status, ['verified', 'waived_with_reason', 'not_applicable'], true))
            ->map(fn (AssessmentItem $item) => $item->evidence_type ?: ($item->requirement_snapshot['name'] ?? 'Requirement'))
            ->filter()
            ->unique()
            ->values();

        $completedMilestones = $location->milestoneAssessments
            ->where('beneficiary_id', $beneficiary->id)
            ->where('status', 'completed')
            ->pluck('project_milestone_id')
            ->unique()
            ->count();
        $attendanceEntries = $location->attendanceRegisters
            ->flatMap(fn ($register) => $register->entries)
            ->where('beneficiary_id', $beneficiary->id);
        $attendedEntries = $attendanceEntries->whereIn('status', ['present', 'excused'])->count();
        $attendanceRate = $this->percentage($attendedEntries, $attendanceEntries->count());
        $evidenceCount = $beneficiary->evidenceItems->count();
        $openSupportCases = $supportCases->whereNull('closed_at')->count();
        $openReadinessActions = $readinessActions->where('status', 'open')->count();
        $evidenceGapCount = $missingEvidenceTypes->count();
        $riskScore = ($evidenceGapCount * 3)
            + ($openReadinessActions * 2)
            + $openSupportCases
            + ($attendanceEntries->isNotEmpty() && $attendanceRate < 80 ? 1 : 0);

        return [
            'beneficiary_id' => $beneficiary->id,
            'beneficiary_name' => trim($beneficiary->name.' '.$beneficiary->surname),
            'enrollment_status' => $enrollment->status,
            'attendance_status' => $beneficiary->attendance_status,
            'support_case_count' => $supportCases->count(),
            'open_support_cases' => $openSupportCases,
            'evidence_count' => $evidenceCount,
            'evidence_gap_count' => $evidenceGapCount,
            'missing_evidence' => $missingEvidenceTypes->all(),
            'open_readiness_actions' => $openReadinessActions,
            'completed_milestone_assessments' => $completedMilestones,
            'attendance_rate' => $attendanceRate,
            'risk_score' => $riskScore,
        ];
    }

    protected function percentage(int $completed, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($completed / $total) * 100, 2);
    }
}
