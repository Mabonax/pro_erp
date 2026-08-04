<?php

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\AssessmentItem;
use App\Domains\CitizenAccess\Models\Intake;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\ReadinessAction;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectEnrollment;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Staff\Models\StaffMember;
use App\Domains\TaskManagement\Models\WorkTask;
use App\Models\Provinces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function citizenAccessPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Naledi',
        'surname' => 'Mokoena',
        'mobile_number' => '0821234567',
        'email' => 'naledi@example.test',
        'province' => 'Gauteng',
        'municipality' => 'Johannesburg',
        'preferred_contact_method' => 'whatsapp',
        'selected_needs' => ['nsfas-funding', 'university-applications'],
        'assistance_description' => 'I need help preparing applications.',
        'consent_to_contact' => true,
        'privacy_notice_accepted' => true,
        'information_accuracy_confirmed' => true,
        'idempotency_key' => 'idem-'.Str::random(8),
    ], $overrides);
}

function citizenAccessProject(): Project
{
    $department = StaffDepartment::query()->create([
        'name' => 'Citizen Access Department '.Str::upper(Str::random(5)),
        'description' => 'Citizen access test department.',
    ]);
    $manager = StaffMember::query()->create([
        'user_id' => User::factory()->create()->id,
        'department_id' => $department->id,
        'first_name' => 'Programme',
        'last_name' => 'Manager',
        'email' => 'manager-'.Str::lower(Str::random(8)).'@example.test',
        'employee_number' => 'EMP-'.Str::upper(Str::random(8)),
        'status' => 'active',
    ]);
    $program = Program::query()->create([
        'title' => 'Citizen Access Programme',
        'code' => 'CAP-'.Str::upper(Str::random(5)),
        'description' => 'Citizen access workflow test programme.',
        'slug' => 'citizen-access-programme-'.Str::lower(Str::random(5)),
        'status' => 'active',
    ]);

    return Project::query()->create([
        'program_id' => $program->id,
        'project_manager_id' => $manager->id,
        'name' => 'Citizen Access Project',
        'project_code' => 'CAP-P-'.Str::upper(Str::random(5)),
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);
}

function citizenAccessOffering(string $slug = 'nsfas-funding'): Opportunity
{
    $project = citizenAccessProject();
    $province = Provinces::query()->create(['name' => 'Gauteng '.Str::upper(Str::random(4))]);
    $facilitator = Facilitator::query()->create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Fiona',
        'surname' => 'Access',
        'dob' => now()->subYears(30)->toDateString(),
        'id_number' => fake()->unique()->numerify('#############'),
        'address' => '1 Access Street',
        'email' => 'facilitator-'.Str::lower(Str::random(8)).'@example.test',
        'cell' => '0712345678',
        'specialization' => 'Access support',
        'province_id' => $province->id,
    ]);
    $location = ProjectLocation::query()->create([
        'project_id' => $project->id,
        'facilitator_id' => $facilitator->id,
        'province_id' => $province->id,
        'training_venue_address' => 'Community access desk',
    ]);
    $stream = ServiceStream::query()->create([
        'name' => Str::headline($slug),
        'slug' => $slug,
        'is_active' => true,
    ]);
    $template = RequirementTemplate::query()->create([
        'service_stream_id' => $stream->id,
        'name' => Str::headline($slug).' readiness',
        'status' => 'published',
    ]);
    $version = $template->versions()->create([
        'version_number' => 1,
        'status' => 'published',
        'published_at' => now(),
        'published_by' => User::factory()->create()->id,
    ]);
    RequirementDefinition::query()->create([
        'template_version_id' => $version->id,
        'name' => 'Identity and contact information confirmed',
        'requirement_status' => 'mandatory',
        'evidence_type' => 'identity_or_contact_record',
        'is_blocking' => true,
    ]);

    return Opportunity::query()->create([
        'service_stream_id' => $stream->id,
        'program_id' => $project->program_id,
        'project_id' => $project->id,
        'project_location_id' => $location->id,
        'requirement_template_id' => $template->id,
        'name' => Str::headline($slug),
        'opportunity_type' => 'access_offering',
        'description' => 'Configured public offering.',
        'public_slug' => $slug,
        'public_title' => Str::headline($slug),
        'public_summary' => 'Configured assistance offering.',
        'is_active' => true,
        'is_published' => true,
        'published_at' => now(),
    ]);
}

it('requires the scoped public intake token', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);

    $this->postJson('/api/public/v1/intakes', citizenAccessPayload())
        ->assertForbidden();
});

it('creates an intake from the public api and keeps retries idempotent', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);

    citizenAccessOffering('nsfas-funding');
    citizenAccessOffering('university-applications');

    $payload = citizenAccessPayload(['idempotency_key' => 'citizen-access-idem-1']);

    $first = $this->withToken('secret-token')
        ->postJson('/api/public/v1/intakes', $payload)
        ->assertCreated()
        ->assertJsonPath('status', 'received_for_screening')
        ->json('public_reference');

    $this->withToken('secret-token')
        ->postJson('/api/public/v1/intakes', $payload)
        ->assertOk()
        ->assertJsonPath('public_reference', $first);

    expect(Intake::query()->count())->toBe(1)
        ->and(Intake::query()->first()->needs()->count())->toBe(2)
        ->and(Intake::query()->first()->needs()->whereNotNull('opportunity_id')->count())->toBe(2);
});

it('publishes only complete active offerings through the public api', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);
    citizenAccessOffering('nsfas-funding');
    Opportunity::query()->create([
        'service_stream_id' => ServiceStream::query()->first()->id,
        'name' => 'Draft offering',
        'opportunity_type' => 'access_offering',
        'public_slug' => 'draft-offering',
        'public_title' => 'Draft offering',
        'is_active' => true,
        'is_published' => false,
    ]);

    $this->withToken('secret-token')
        ->getJson('/api/public/v1/offerings')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'nsfas-funding')
        ->assertJsonMissing(['slug' => 'draft-offering'])
        ->assertJsonMissing(['project_id' => 1]);
});

it('rejects unknown or unpublished offering slugs', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);
    citizenAccessOffering('nsfas-funding');

    $this->withToken('secret-token')
        ->postJson('/api/public/v1/intakes', citizenAccessPayload(['selected_needs' => ['missing-offering']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['selected_needs']);
});

it('prevents publishing incomplete or inconsistent offerings', function () {
    $officer = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $offering = citizenAccessOffering('nsfas-funding');
    $otherProject = citizenAccessProject();

    $this->actingAs($officer)
        ->put("/citizen-access/admin/opportunities/{$offering->id}", [
            'service_stream_id' => $offering->service_stream_id,
            'name' => $offering->name,
            'opportunity_type' => 'access_offering',
            'is_active' => true,
            'is_published' => true,
            'public_slug' => 'nsfas-funding',
            'public_title' => '',
        ])
        ->assertSessionHasErrors(['public_title']);

    $this->actingAs($officer)
        ->put("/citizen-access/admin/opportunities/{$offering->id}", [
            'service_stream_id' => $offering->service_stream_id,
            'program_id' => $offering->program_id,
            'project_id' => $otherProject->id,
            'project_location_id' => $offering->project_location_id,
            'requirement_template_id' => $offering->requirement_template_id,
            'name' => $offering->name,
            'opportunity_type' => 'access_offering',
            'is_active' => true,
            'is_published' => true,
            'public_slug' => 'nsfas-funding',
            'public_title' => 'NSFAS funding',
        ])
        ->assertSessionHasErrors(['project_id']);
});

it('lets an officer convert an offering intake into beneficiary workflow records idempotently', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);
    $officer = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $offering = citizenAccessOffering('nsfas-funding');

    $reference = $this->withToken('secret-token')
        ->postJson('/api/public/v1/intakes', citizenAccessPayload(['selected_needs' => ['nsfas-funding']]))
        ->json('public_reference');

    $intake = Intake::query()->where('public_reference', $reference)->firstOrFail();

    $this->actingAs($officer)
        ->post("/citizen-access/intakes/{$intake->id}/convert", [
            'project_id' => $offering->project_id,
            'program_id' => $offering->program_id,
        ])
        ->assertRedirect();

    $intake->refresh();
    expect($intake->status)->toBe('converted')
        ->and($intake->converted_beneficiary_id)->not->toBeNull()
        ->and(Beneficiary::query()->count())->toBe(1)
        ->and(ProjectEnrollment::query()->where('project_id', $offering->project_id)->where('project_location_id', $offering->project_location_id)->count())->toBe(1)
        ->and(SupportCase::query()->where('intake_id', $intake->id)->where('opportunity_id', $offering->id)->count())->toBe(1)
        ->and(AssessmentItem::query()->count())->toBe(1)
        ->and(ReadinessAction::query()->count())->toBe(1);

    $this->actingAs($officer)
        ->post("/citizen-access/intakes/{$intake->id}/convert", [
            'project_id' => $offering->project_id,
            'program_id' => $offering->program_id,
        ])
        ->assertRedirect();

    expect(ProjectEnrollment::query()->count())->toBe(1)
        ->and(SupportCase::query()->count())->toBe(1)
        ->and(AssessmentItem::query()->count())->toBe(1)
        ->and(ReadinessAction::query()->count())->toBe(1);
});

it('creates immutable requirement snapshots and blocks readiness until requirements are satisfied', function () {
    $officer = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $project = citizenAccessProject();
    $stream = ServiceStream::query()->create(['name' => 'University applications', 'slug' => 'university-applications']);
    $beneficiary = Beneficiary::query()->create([
        'name' => 'Naledi',
        'surname' => 'Mokoena',
        'email' => 'naledi-case@example.test',
        'phone' => '0821234567',
        'project_id' => $project->id,
        'program_id' => $project->program_id,
        'attendance_status' => 'active',
    ]);

    $template = RequirementTemplate::query()->create([
        'service_stream_id' => $stream->id,
        'name' => 'University application readiness',
        'status' => 'published',
    ]);
    $version = $template->versions()->create([
        'version_number' => 1,
        'status' => 'published',
        'published_at' => now(),
        'published_by' => $officer->id,
    ]);
    $definition = RequirementDefinition::query()->create([
        'template_version_id' => $version->id,
        'name' => 'Certified identity document',
        'requirement_status' => 'mandatory',
        'evidence_type' => 'identity_document',
        'is_blocking' => true,
    ]);

    $this->actingAs($officer)
        ->post('/citizen-access/cases', [
            'beneficiary_id' => $beneficiary->id,
            'service_stream_id' => $stream->id,
            'template_version_id' => $version->id,
        ])
        ->assertRedirect();

    $case = SupportCase::query()->with('assessmentItems')->firstOrFail();
    expect($case->assessmentItems)->toHaveCount(1)
        ->and($case->assessmentItems->first()->requirement_snapshot['name'])->toBe('Certified identity document')
        ->and($case->readiness_state)->toBe('not_document_ready');

    $definition->update(['name' => 'Changed future requirement']);
    expect($case->assessmentItems()->first()->requirement_snapshot['name'])->toBe('Certified identity document');

    $this->actingAs($officer)
        ->post('/citizen-access/assessment-items/'.$case->assessmentItems->first()->id.'/status', [
            'status' => 'verified',
        ])
        ->assertRedirect();

    expect($case->refresh()->readiness_state)->toBe('ready_for_application_support');
});

it('creates a governed work task from a support case readiness action', function () {
    Notification::fake();

    $department = StaffDepartment::query()->create([
        'name' => 'Citizen Access Operations',
        'description' => 'Case operations team',
    ]);
    $manager = User::factory()->create([
        'name' => 'Case Manager',
        'email' => 'case.manager@example.test',
    ]);
    $staff = StaffMember::query()->create([
        'user_id' => $manager->id,
        'department_id' => $department->id,
        'first_name' => 'Case',
        'last_name' => 'Manager',
        'email' => 'case.manager@example.test',
        'employee_number' => 'CA-'.Str::upper(Str::random(6)),
        'status' => 'active',
        'is_manager' => true,
    ]);
    $manager->staffMember()->save($staff);
    grantDomainAccess($manager, 'citizen-access');
    grantDomainAccess($manager, 'task-management');

    $project = citizenAccessProject();
    $stream = ServiceStream::query()->create(['name' => 'University applications', 'slug' => 'university-task-bridge']);
    $beneficiary = Beneficiary::query()->create([
        'name' => 'Naledi',
        'surname' => 'Mokoena',
        'email' => 'naledi-task@example.test',
        'phone' => '0821234567',
        'project_id' => $project->id,
        'program_id' => $project->program_id,
        'attendance_status' => 'active',
    ]);
    $case = SupportCase::query()->create([
        'case_reference' => 'CAS-260803-TASK1',
        'beneficiary_id' => $beneficiary->id,
        'program_id' => $project->program_id,
        'project_id' => $project->id,
        'service_stream_id' => $stream->id,
        'assigned_to_user_id' => $manager->id,
        'priority' => 'high',
        'stage' => 'assessment_in_progress',
    ]);
    $assessment = AssessmentItem::query()->create([
        'support_case_id' => $case->id,
        'requirement_snapshot' => ['name' => 'Certified identity document'],
        'status' => 'evidence_missing',
        'is_blocking' => true,
        'evidence_type' => 'identity_document',
    ]);
    $action = ReadinessAction::query()->create([
        'support_case_id' => $case->id,
        'assessment_item_id' => $assessment->id,
        'description' => 'Resolve readiness gap: Certified identity document',
        'assigned_to_user_id' => $manager->id,
        'priority' => 'high',
        'status' => 'open',
    ]);

    $this->actingAs($manager)
        ->get("/citizen-access/cases/{$case->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CitizenAccess/Cases/Show')
            ->where('canCreateReadinessTask', true)
            ->where('caseRecord.readiness_actions.0.work_task', null)
        );

    $this->actingAs($manager)
        ->post("/citizen-access/cases/{$case->id}/readiness-actions/{$action->id}/task", [
            'assigned_to_user_id' => $manager->id,
            'assigned_department_id' => '',
            'priority' => 'high',
            'due_date' => now()->addDays(2)->toDateString(),
        ])
        ->assertRedirect();

    $task = WorkTask::query()->firstOrFail();
    $action->refresh();

    expect($action->work_task_id)->toBe($task->id)
        ->and($task->creator_user_id)->toBe($manager->id)
        ->and($task->assigned_to_user_id)->toBe($manager->id)
        ->and($task->project_id)->toBe($project->id)
        ->and($task->program_id)->toBe($project->program_id);

    $this->actingAs($manager)
        ->get("/citizen-access/cases/{$case->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('caseRecord.readiness_actions.0.work_task.id', $task->id)
            ->where('caseRecord.readiness_actions.0.work_task.status', 'open')
        );

    $this->actingAs($manager)
        ->post("/citizen-access/cases/{$case->id}/readiness-actions/{$action->id}/task", [
            'assigned_to_user_id' => $manager->id,
            'priority' => 'high',
        ])
        ->assertRedirect(route('task-management.tasks.show', $task));

    expect(WorkTask::query()->count())->toBe(1);
});

it('records application referral follow up and outcome activities on a support case', function () {
    $officer = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $project = citizenAccessProject();
    $stream = ServiceStream::query()->create(['name' => 'University applications', 'slug' => 'university-applications']);
    $beneficiary = Beneficiary::query()->create([
        'name' => 'Naledi',
        'surname' => 'Mokoena',
        'email' => 'naledi-activity@example.test',
        'phone' => '0821234567',
        'project_id' => $project->id,
        'program_id' => $project->program_id,
        'attendance_status' => 'active',
    ]);
    $case = SupportCase::query()->create([
        'case_reference' => 'CAS-260730-ACT01',
        'beneficiary_id' => $beneficiary->id,
        'service_stream_id' => $stream->id,
        'stage' => 'ready_to_apply',
    ]);

    $this->actingAs($officer)
        ->post("/citizen-access/cases/{$case->id}/activities", [
            'activity_type' => 'application',
            'official_channel' => 'Official portal',
            'external_reference' => 'EXT-123',
            'submission_date' => now()->toDateString(),
            'external_status' => 'submitted',
            'follow_up_date' => now()->addWeek()->toDateString(),
        ])
        ->assertRedirect();

    $this->actingAs($officer)
        ->post("/citizen-access/cases/{$case->id}/activities", [
            'activity_type' => 'outcome',
            'outcome_category' => 'accepted',
            'outcome_date' => now()->toDateString(),
            'outcome_verification_status' => 'verified',
            'closure_reason' => 'Official outcome evidence reviewed by officer.',
        ])
        ->assertRedirect();

    expect($case->applications()->count())->toBe(2)
        ->and($case->refresh()->stage)->toBe('outcome_recorded');
});
