<?php

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\AssessmentItem;
use App\Domains\CitizenAccess\Models\EvidenceItem;
use App\Domains\CitizenAccess\Models\ReadinessAction;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\AttendanceEntry;
use App\Domains\Projects\Models\AttendanceRegister;
use App\Domains\Projects\Models\ProgramMilestoneTemplate;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectEnrollment;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Projects\Models\ProjectMilestone;
use App\Domains\Projects\Models\ProjectMilestoneAssessment;
use App\Domains\Projects\Services\ProjectProgressService;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Staff\Models\StaffMember;
use App\Models\NextOfKin;
use App\Models\Provinces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeProjectProgressGraph(string $suffix, bool $fullyDelivered = false): array
{
    $department = StaffDepartment::query()->create([
        'name' => 'Projects Department '.$suffix,
        'description' => 'Projects Department',
    ]);

    $manager = StaffMember::query()->create([
        'department_id' => $department->id,
        'first_name' => 'Paula',
        'last_name' => 'Manager '.$suffix,
        'email' => 'pm-'.$suffix.'@example.com',
        'employee_number' => 'EMP-PM-'.$suffix,
        'status' => 'active',
    ]);

    $program = Program::query()->create([
        'title' => 'Project Progress Program '.$suffix,
        'description' => 'Project Progress Program',
        'slug' => 'project-progress-program-'.Str::lower($suffix),
    ]);

    $project = Project::query()->create([
        'program_id' => $program->id,
        'project_manager_id' => $manager->id,
        'name' => 'Progress Project '.$suffix,
        'start_date' => '2026-05-01',
        'end_date' => '2026-06-30',
        'status' => 'active',
        'description' => 'Project progress test project',
    ]);

    $provinceA = Provinces::query()->create(['name' => 'Province A '.$suffix]);
    $provinceB = Provinces::query()->create(['name' => 'Province B '.$suffix]);

    $facilitator = Facilitator::query()->create([
        'name' => 'Faye',
        'surname' => 'Trainer '.$suffix,
        'dob' => now()->subYears(30)->toDateString(),
        'id_number' => fake()->unique()->numerify('####################'),
        'address' => '1 Training Street',
        'email' => 'facilitator-'.$suffix.'@example.com',
        'cell' => '0712345678',
        'specialization' => 'Training',
        'province_id' => $provinceA->id,
    ]);

    $locationA = ProjectLocation::query()->create([
        'project_id' => $project->id,
        'facilitator_id' => $facilitator->id,
        'province_id' => $provinceA->id,
        'training_venue_address' => 'Venue A '.$suffix,
    ]);

    $locationB = ProjectLocation::query()->create([
        'project_id' => $project->id,
        'facilitator_id' => $facilitator->id,
        'province_id' => $provinceB->id,
        'training_venue_address' => 'Venue B '.$suffix,
    ]);

    $nextOfKin = NextOfKin::query()->create([
        'name' => 'Nora',
        'surname' => 'Kin '.$suffix,
        'relationship' => 'Sibling',
        'phone' => '0710000000',
        'email' => 'nok-'.$suffix.'@example.com',
    ]);

    $beneficiaryA1 = Beneficiary::query()->create([
        'name' => 'Bene',
        'surname' => 'One '.$suffix,
        'dob' => now()->subYears(21),
        'age' => 21,
        'id_number' => fake()->unique()->numerify('#############'),
        'email' => 'beneficiary-a1-'.$suffix.'@example.com',
        'phone' => '0721111111',
        'gender' => 'female',
        'project_id' => $project->id,
        'attendance_status' => 'active',
        'next_of_kin_id' => $nextOfKin->id,
    ]);

    $beneficiaryA2 = Beneficiary::query()->create([
        'name' => 'Bene',
        'surname' => 'Two '.$suffix,
        'dob' => now()->subYears(22),
        'age' => 22,
        'id_number' => fake()->unique()->numerify('#############'),
        'email' => 'beneficiary-a2-'.$suffix.'@example.com',
        'phone' => '0722222222',
        'gender' => 'male',
        'project_id' => $project->id,
        'attendance_status' => 'active',
        'next_of_kin_id' => $nextOfKin->id,
    ]);

    $beneficiaryB1 = Beneficiary::query()->create([
        'name' => 'Bene',
        'surname' => 'Three '.$suffix,
        'dob' => now()->subYears(23),
        'age' => 23,
        'id_number' => fake()->unique()->numerify('#############'),
        'email' => 'beneficiary-b1-'.$suffix.'@example.com',
        'phone' => '0723333333',
        'gender' => 'female',
        'project_id' => $project->id,
        'attendance_status' => 'active',
        'next_of_kin_id' => $nextOfKin->id,
    ]);

    ProjectEnrollment::query()->create([
        'project_id' => $project->id,
        'project_location_id' => $locationA->id,
        'beneficiary_id' => $beneficiaryA1->id,
        'status' => 'enrolled',
        'enrolled_at' => now(),
    ]);

    ProjectEnrollment::query()->create([
        'project_id' => $project->id,
        'project_location_id' => $locationA->id,
        'beneficiary_id' => $beneficiaryA2->id,
        'status' => 'enrolled',
        'enrolled_at' => now(),
    ]);

    ProjectEnrollment::query()->create([
        'project_id' => $project->id,
        'project_location_id' => $locationB->id,
        'beneficiary_id' => $beneficiaryB1->id,
        'status' => 'enrolled',
        'enrolled_at' => now(),
    ]);

    if (! $fullyDelivered) {
        $droppedBeneficiary = Beneficiary::query()->create([
            'name' => 'Bene',
            'surname' => 'Dropped '.$suffix,
            'dob' => now()->subYears(24),
            'age' => 24,
            'id_number' => fake()->unique()->numerify('#############'),
            'email' => 'beneficiary-drop-'.$suffix.'@example.com',
            'phone' => '0724444444',
            'gender' => 'male',
            'project_id' => $project->id,
            'attendance_status' => 'dropout',
            'next_of_kin_id' => $nextOfKin->id,
        ]);

        ProjectEnrollment::query()->create([
            'project_id' => $project->id,
            'project_location_id' => $locationB->id,
            'beneficiary_id' => $droppedBeneficiary->id,
            'status' => 'dropped',
            'enrolled_at' => now(),
        ]);
    }

    $template1 = ProgramMilestoneTemplate::query()->create([
        'program_id' => $program->id,
        'title' => 'Module 1 Template '.$suffix,
        'description' => 'Module 1 Template',
        'sort_order' => 1,
        'max_score' => 10,
    ]);

    $template2 = ProgramMilestoneTemplate::query()->create([
        'program_id' => $program->id,
        'title' => 'Module 2 Template '.$suffix,
        'description' => 'Module 2 Template',
        'sort_order' => 2,
        'max_score' => 10,
    ]);

    $milestone1 = ProjectMilestone::query()->create([
        'project_id' => $project->id,
        'program_milestone_template_id' => $template1->id,
        'title' => 'Module 1 '.$suffix,
        'description' => 'Module 1',
        'sort_order' => 1,
        'max_score' => 10,
    ]);

    $milestone2 = ProjectMilestone::query()->create([
        'project_id' => $project->id,
        'program_milestone_template_id' => $template2->id,
        'title' => 'Module 2 '.$suffix,
        'description' => 'Module 2',
        'sort_order' => 2,
        'max_score' => 10,
    ]);

    $registerA = AttendanceRegister::query()->create([
        'project_id' => $project->id,
        'project_location_id' => $locationA->id,
        'facilitator_id' => $facilitator->id,
        'attendance_date' => '2026-05-12',
        'is_holiday' => false,
    ]);

    if ($fullyDelivered) {
        AttendanceEntry::query()->create([
            'attendance_register_id' => $registerA->id,
            'beneficiary_id' => $beneficiaryA1->id,
            'status' => 'present',
        ]);

        AttendanceEntry::query()->create([
            'attendance_register_id' => $registerA->id,
            'beneficiary_id' => $beneficiaryA2->id,
            'status' => 'present',
        ]);

        $registerB = AttendanceRegister::query()->create([
            'project_id' => $project->id,
            'project_location_id' => $locationB->id,
            'facilitator_id' => $facilitator->id,
            'attendance_date' => '2026-05-13',
            'is_holiday' => false,
        ]);

        AttendanceEntry::query()->create([
            'attendance_register_id' => $registerB->id,
            'beneficiary_id' => $beneficiaryB1->id,
            'status' => 'present',
        ]);
    } else {
        AttendanceEntry::query()->create([
            'attendance_register_id' => $registerA->id,
            'beneficiary_id' => $beneficiaryA1->id,
            'status' => 'present',
        ]);

        AttendanceEntry::query()->create([
            'attendance_register_id' => $registerA->id,
            'beneficiary_id' => $beneficiaryA2->id,
            'status' => 'absent',
        ]);
    }

    $assessments = [
        [$milestone1, $beneficiaryA1, $locationA],
        [$milestone2, $beneficiaryA1, $locationA],
        [$milestone1, $beneficiaryA2, $locationA],
    ];

    if ($fullyDelivered) {
        $assessments[] = [$milestone2, $beneficiaryA2, $locationA];
        $assessments[] = [$milestone1, $beneficiaryB1, $locationB];
        $assessments[] = [$milestone2, $beneficiaryB1, $locationB];
    }

    foreach ($assessments as [$milestone, $beneficiary, $location]) {
        ProjectMilestoneAssessment::query()->create([
            'project_milestone_id' => $milestone->id,
            'beneficiary_id' => $beneficiary->id,
            'project_location_id' => $location->id,
            'facilitator_id' => $facilitator->id,
            'status' => 'completed',
            'score' => 8,
            'comments' => 'Completed',
            'assessed_at' => now(),
        ]);
    }

    return compact('project', 'locationA', 'locationB');
}

test('project progress service summarizes delivery across project locations', function () {
    $graph = makeProjectProgressGraph(Str::upper(Str::random(5)));

    $summary = app(ProjectProgressService::class)->summarizeProject($graph['project']);

    expect($summary['summary']['project_manager_name'])->toContain('Paula');
    expect($summary['summary']['total_locations'])->toBe(2);
    expect($summary['summary']['total_milestones'])->toBe(2);
    expect($summary['summary']['total_beneficiaries'])->toBe(4);
    expect($summary['summary']['active_beneficiaries'])->toBe(3);
    expect($summary['summary']['completed_beneficiaries'])->toBe(1);
    expect($summary['summary']['dropped_beneficiaries'])->toBe(1);
    expect($summary['summary']['expected_assessments'])->toBe(6);
    expect($summary['summary']['completed_assessments'])->toBe(3);
    expect($summary['summary']['attendance_rate'])->toBe(50.0);
    expect($summary['summary']['milestone_completion_rate'])->toBe(50.0);
    expect($summary['summary']['beneficiary_completion_rate'])->toBe(33.33);
    expect($summary['summary']['blocked_locations'])->toBe(2);
    expect($summary['summary']['blockers'])->toBe([]);

    $locationA = collect($summary['locations'])->firstWhere('id', $graph['locationA']->id);
    $locationB = collect($summary['locations'])->firstWhere('id', $graph['locationB']->id);

    expect($locationA['attendance_rate'])->toBe(50.0);
    expect($locationA['milestone_completion_rate'])->toBe(75.0);
    expect($locationA['beneficiary_completion_rate'])->toBe(50.0);
    expect($locationA['is_blocked'])->toBeTrue();

    expect($locationB['attendance_rate'])->toBe(0.0);
    expect($locationB['milestone_completion_rate'])->toBe(0.0);
    expect($locationB['beneficiary_completion_rate'])->toBe(0.0);
    expect($locationB['is_blocked'])->toBeTrue();
    expect($locationB['blockers'])->toContain('Attendance has not been captured for this location.');
});

test('project progress service summarizes beneficiary journey risks by location', function () {
    $graph = makeProjectProgressGraph(Str::upper(Str::random(5)));
    $project = $graph['project'];
    $locationA = $graph['locationA'];
    $locationB = $graph['locationB'];
    $stream = ServiceStream::query()->create([
        'name' => 'University applications',
        'slug' => 'project-journey-'.Str::lower(Str::random(5)),
    ]);
    $beneficiaryA = ProjectEnrollment::query()
        ->where('project_location_id', $locationA->id)
        ->firstOrFail()
        ->beneficiary;
    $beneficiaryB = ProjectEnrollment::query()
        ->where('project_location_id', $locationB->id)
        ->firstOrFail()
        ->beneficiary;

    EvidenceItem::query()->create([
        'beneficiary_id' => $beneficiaryA->id,
        'evidence_type' => 'identity_document',
        'verification_status' => 'verified',
    ]);
    $caseA = SupportCase::query()->create([
        'case_reference' => 'CAS-260803-PJR01',
        'beneficiary_id' => $beneficiaryA->id,
        'program_id' => $project->program_id,
        'project_id' => $project->id,
        'project_location_id' => $locationA->id,
        'service_stream_id' => $stream->id,
        'priority' => 'high',
        'stage' => 'assessment_in_progress',
        'readiness_state' => 'not_document_ready',
    ]);
    $assessmentA = AssessmentItem::query()->create([
        'support_case_id' => $caseA->id,
        'requirement_snapshot' => ['name' => 'Proof of residence'],
        'status' => 'evidence_missing',
        'is_blocking' => true,
        'evidence_type' => 'proof_of_residence',
    ]);
    ReadinessAction::query()->create([
        'support_case_id' => $caseA->id,
        'assessment_item_id' => $assessmentA->id,
        'description' => 'Resolve readiness gap: Proof of residence',
        'priority' => 'high',
        'status' => 'open',
    ]);
    $caseB = SupportCase::query()->create([
        'case_reference' => 'CAS-260803-PJR02',
        'beneficiary_id' => $beneficiaryB->id,
        'program_id' => $project->program_id,
        'project_id' => $project->id,
        'project_location_id' => $locationB->id,
        'service_stream_id' => $stream->id,
        'priority' => 'medium',
        'stage' => 'ready_to_apply',
        'readiness_state' => 'ready_for_application_support',
    ]);
    AssessmentItem::query()->create([
        'support_case_id' => $caseB->id,
        'requirement_snapshot' => ['name' => 'Identity document'],
        'status' => 'verified',
        'is_blocking' => true,
        'evidence_type' => 'identity_document',
    ]);

    $summary = app(ProjectProgressService::class)->summarizeProject($project);
    $journey = $summary['journey'];
    $locationAJourney = collect($journey['locations'])->firstWhere('location_id', $locationA->id);
    $locationBJourney = collect($journey['locations'])->firstWhere('location_id', $locationB->id);

    expect($journey['summary']['open_support_cases'])->toBe(2)
        ->and($journey['summary']['evidence_gaps'])->toBe(1)
        ->and($journey['summary']['open_readiness_actions'])->toBe(1)
        ->and($journey['summary']['locations_with_risks'])->toBeGreaterThanOrEqual(1)
        ->and($locationAJourney['evidence_gaps'])->toBe(1)
        ->and($locationAJourney['open_readiness_actions'])->toBe(1)
        ->and($locationAJourney['at_risk_beneficiaries'][0]['beneficiary_id'])->toBe($beneficiaryA->id)
        ->and($locationAJourney['at_risk_beneficiaries'][0]['missing_evidence'])->toContain('proof_of_residence')
        ->and($locationBJourney['evidence_gaps'])->toBe(0);
});

test('project progress service summarizes the portfolio for the project manager dashboard', function () {
    $first = makeProjectProgressGraph('ALPHA');
    $second = makeProjectProgressGraph('BETA', fullyDelivered: true);

    $projects = Project::query()
        ->with([
            'projectManager',
            'locations.facilitator',
            'locations.province',
            'locations.enrollments.beneficiary',
            'locations.milestoneAssessments',
            'locations.attendanceRegisters.entries',
            'milestones',
        ])
        ->whereIn('id', [$first['project']->id, $second['project']->id])
        ->orderBy('id')
        ->get();

    $portfolio = app(ProjectProgressService::class)->summarizePortfolio($projects);

    expect($portfolio['stats']['tracked_projects'])->toBe(2);
    expect($portfolio['stats']['average_milestone_completion_rate'])->toBe(75.0);
    expect($portfolio['stats']['average_beneficiary_completion_rate'])->toBe(66.67);
    expect($portfolio['stats']['average_attendance_rate'])->toBe(75.0);
    expect($portfolio['stats']['blocked_locations'])->toBe(2);

    $secondProject = collect($portfolio['projects'])->firstWhere('id', $second['project']->id);

    expect($secondProject['blocked_locations'])->toBe(0);
    expect($secondProject['attendance_rate'])->toBe(100.0);
    expect($secondProject['milestone_completion_rate'])->toBe(100.0);
    expect($secondProject['beneficiary_completion_rate'])->toBe(100.0);
});
