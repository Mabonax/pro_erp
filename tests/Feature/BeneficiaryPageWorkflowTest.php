<?php

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\EvidenceItem;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\Documents\Models\DocumentFile;
use App\Domains\Documents\Models\DocumentFolder;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectEnrollment;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Projects\Models\ProjectMilestone;
use App\Domains\Projects\Models\ProjectMilestoneAssessment;
use App\Domains\Projects\Models\ProgramMilestoneTemplate;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Staff\Models\StaffMember;
use App\Models\NextOfKin;
use App\Models\Provinces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeBeneficiaryPageWorkflowFixture(): array
{
    $department = StaffDepartment::query()->create([
        'name' => 'Delivery',
        'description' => 'Delivery department',
    ]);

    $manager = StaffMember::query()->create([
        'department_id' => $department->id,
        'first_name' => 'Project',
        'last_name' => 'Manager',
        'email' => 'manager-'.Str::lower(Str::random(8)).'@example.com',
        'employee_number' => 'EMP-'.Str::upper(Str::random(8)),
        'status' => 'active',
    ]);

    $program = Program::query()->create([
        'title' => 'Delivery Programme',
        'description' => 'Programme for beneficiary workflow tests',
        'slug' => 'delivery-programme-'.Str::lower(Str::random(5)),
    ]);

    $project = Project::query()->create([
        'program_id' => $program->id,
        'project_manager_id' => $manager->id,
        'name' => 'Cohort 2026',
        'start_date' => now()->toDateString(),
        'status' => 'active',
        'description' => 'Test cohort',
    ]);

    $province = Provinces::query()->create([
        'name' => 'Gauteng '.Str::upper(Str::random(4)),
    ]);

    $facilitator = Facilitator::query()->create([
        'name' => 'Fac',
        'surname' => 'ilitator',
        'dob' => now()->subYears(30)->toDateString(),
        'id_number' => fake()->unique()->numerify('####################'),
        'address' => '123 Street',
        'email' => 'facilitator-'.Str::lower(Str::random(8)).'@example.com',
        'cell' => '0712345678',
        'specialization' => 'Incubation',
        'province_id' => $province->id,
    ]);

    $location = ProjectLocation::query()->create([
        'project_id' => $project->id,
        'facilitator_id' => $facilitator->id,
        'province_id' => $province->id,
        'training_venue_address' => 'Training Hall',
    ]);

    $nextOfKin = NextOfKin::query()->create([
        'name' => 'Nora',
        'surname' => 'Kin',
        'relationship' => 'Sibling',
        'phone' => '0733333333',
        'email' => 'nora.kin@example.test',
    ]);

    $beneficiary = Beneficiary::query()->create([
        'name' => 'Lebo',
        'surname' => 'Mokoena',
        'dob' => now()->subYears(24)->toDateString(),
        'age' => 24,
        'id_number' => fake()->unique()->numerify('#############'),
        'email' => 'lebo-'.Str::lower(Str::random(8)).'@example.com',
        'phone' => '0722222222',
        'gender' => 'female',
        'project_id' => $project->id,
        'province_id' => $province->id,
        'postal_code' => '2000',
        'attendance_status' => 'active',
        'next_of_kin_id' => $nextOfKin->id,
    ]);

    ProjectEnrollment::query()->create([
        'project_id' => $project->id,
        'project_location_id' => $location->id,
        'beneficiary_id' => $beneficiary->id,
        'status' => 'enrolled',
        'enrolled_at' => now()->subDay(),
    ]);

    return compact('program', 'project', 'province', 'location', 'beneficiary');
}

test('authorized user can open the beneficiary create page', function () {
    $user = User::factory()->create();
    grantDomainAccess($user, 'beneficiaries');
    $fixture = makeBeneficiaryPageWorkflowFixture();

    $this->actingAs($user)
        ->get('/beneficiaries/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Beneficiaries/Create')
            ->has('programs', 1)
            ->has('projects', 1)
            ->where('projects.0.id', $fixture['project']->id)
            ->has('projectLocations', 1)
        );
});

test('beneficiary create page only exposes assignable projects while keeping planned projects available', function () {
    $user = User::factory()->create();
    grantDomainAccess($user, 'beneficiaries');
    $fixture = makeBeneficiaryPageWorkflowFixture();

    $completedProject = Project::query()->create([
        'program_id' => $fixture['program']->id,
        'project_manager_id' => $fixture['project']->project_manager_id,
        'name' => 'Completed Cohort',
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'status' => 'completed',
        'description' => 'Closed project',
    ]);

    $plannedProject = Project::query()->create([
        'program_id' => $fixture['program']->id,
        'project_manager_id' => null,
        'name' => 'Planned Cohort',
        'start_date' => now()->addWeek()->toDateString(),
        'status' => 'planned',
        'description' => 'Open for setup',
    ]);

    $this->actingAs($user)
        ->get('/beneficiaries/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Beneficiaries/Create')
            ->where('projects', fn ($projects) => collect($projects)->pluck('name')->contains('Planned Cohort'))
            ->where('projects', fn ($projects) => ! collect($projects)->pluck('id')->contains($completedProject->id))
            ->where('projects', fn ($projects) => collect($projects)->pluck('id')->contains($plannedProject->id))
        );
});

test('authorized user can open the beneficiary edit page', function () {
    $user = User::factory()->create();
    grantDomainAccess($user, 'beneficiaries');
    $fixture = makeBeneficiaryPageWorkflowFixture();

    $this->actingAs($user)
        ->get("/beneficiaries/{$fixture['beneficiary']->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Beneficiaries/Edit')
            ->where('beneficiary.id', $fixture['beneficiary']->id)
            ->where('beneficiary.full_name', 'Lebo Mokoena')
        );
});

test('beneficiary file exposes the cross-domain service journey', function () {
    $user = User::factory()->create();
    grantDomainAccess($user, 'beneficiaries');
    $fixture = makeBeneficiaryPageWorkflowFixture();

    $stream = ServiceStream::query()->create([
        'name' => 'NSFAS Applications',
        'slug' => 'nsfas-applications-'.Str::lower(Str::random(5)),
    ]);

    SupportCase::query()->create([
        'case_reference' => 'CAS-260803-001',
        'beneficiary_id' => $fixture['beneficiary']->id,
        'program_id' => $fixture['project']->program_id,
        'project_id' => $fixture['project']->id,
        'project_location_id' => $fixture['location']->id,
        'service_stream_id' => $stream->id,
        'stage' => 'ready_to_apply',
        'readiness_state' => 'ready_for_application_support',
        'readiness_percentage' => 90,
    ]);

    EvidenceItem::query()->create([
        'beneficiary_id' => $fixture['beneficiary']->id,
        'evidence_type' => 'identity_document',
        'verification_status' => 'verified',
    ]);

    $template = ProgramMilestoneTemplate::query()->create([
        'program_id' => $fixture['program']->id,
        'title' => 'Application submitted',
        'sort_order' => 1,
    ]);

    $milestone = ProjectMilestone::query()->create([
        'project_id' => $fixture['project']->id,
        'program_milestone_template_id' => $template->id,
        'title' => 'Application submitted',
        'sort_order' => 1,
    ]);

    ProjectMilestoneAssessment::query()->create([
        'project_milestone_id' => $milestone->id,
        'beneficiary_id' => $fixture['beneficiary']->id,
        'project_location_id' => $fixture['location']->id,
        'status' => 'completed',
        'score' => 80,
        'assessed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get("/beneficiaries/{$fixture['beneficiary']->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Beneficiaries/Show')
            ->where('beneficiary.support_cases.0.case_reference', 'CAS-260803-001')
            ->where('beneficiary.support_cases.0.service_stream', 'NSFAS Applications')
            ->where('beneficiary.evidence_items.0.evidence_type', 'identity_document')
            ->where('beneficiary.milestone_assessments.0.milestone', 'Application submitted')
            ->where('beneficiary.service_journey_summary.open_support_case_count', 1)
            ->where('beneficiary.service_journey_summary.completed_milestone_assessment_count', 1)
        );
});

test('creating a citizen access case from a beneficiary preserves placement context', function () {
    $user = User::factory()->create();
    grantDomainAccess($user, 'citizen-access');
    $fixture = makeBeneficiaryPageWorkflowFixture();
    $stream = ServiceStream::query()->create([
        'name' => 'University Applications',
        'slug' => 'university-applications-'.Str::lower(Str::random(5)),
    ]);

    $this->actingAs($user)
        ->get("/citizen-access/cases/create?beneficiary_id={$fixture['beneficiary']->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CitizenAccess/Cases/Create')
            ->where('selectedBeneficiaryId', $fixture['beneficiary']->id)
            ->where('beneficiaries.0.project_id', $fixture['project']->id)
            ->where('beneficiaries.0.project_location_id', $fixture['location']->id)
        );

    $this->actingAs($user)
        ->post('/citizen-access/cases', [
            'beneficiary_id' => $fixture['beneficiary']->id,
            'service_stream_id' => $stream->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('citizen_access_support_cases', [
        'beneficiary_id' => $fixture['beneficiary']->id,
        'program_id' => $fixture['project']->program_id,
        'project_id' => $fixture['project']->id,
        'project_location_id' => $fixture['location']->id,
    ]);
});

test('beneficiary evidence upload stores a document library file and evidence item', function () {
    Storage::fake('document_library');

    $user = User::factory()->create();
    grantDomainAccess($user, 'beneficiaries');
    $fixture = makeBeneficiaryPageWorkflowFixture();

    $this->actingAs($user)
        ->post("/beneficiaries/{$fixture['beneficiary']->id}/evidence", [
            'evidence_type' => 'identity_document',
            'title' => 'Certified ID',
            'issuer' => 'Home Affairs',
            'issue_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'verification_status' => 'verified',
            'file' => UploadedFile::fake()->create('id.pdf', 16, 'application/pdf'),
        ])
        ->assertRedirect("/beneficiaries/{$fixture['beneficiary']->id}");

    $folder = DocumentFolder::query()
        ->where('owner_type', Beneficiary::class)
        ->where('owner_id', $fixture['beneficiary']->id)
        ->where('folder_type', DocumentFolder::TYPE_STANDARD)
        ->firstOrFail();
    $file = DocumentFile::query()->where('folder_id', $folder->id)->firstOrFail();

    Storage::disk('document_library')->assertExists($file->file_path);

    $this->assertDatabaseHas('citizen_access_evidence_items', [
        'beneficiary_id' => $fixture['beneficiary']->id,
        'document_file_id' => $file->id,
        'evidence_type' => 'identity_document',
        'verification_status' => 'verified',
        'issuer' => 'Home Affairs',
    ]);
});

test('deleting a beneficiary from the file page returns to the beneficiary index', function () {
    $user = User::factory()->create();
    grantDomainAccess($user, 'beneficiaries');
    $fixture = makeBeneficiaryPageWorkflowFixture();

    $enrollmentId = ProjectEnrollment::query()
        ->where('beneficiary_id', $fixture['beneficiary']->id)
        ->value('id');

    $nextOfKinId = $fixture['beneficiary']->next_of_kin_id;

    $this->actingAs($user)
        ->delete("/beneficiaries/{$fixture['beneficiary']->id}")
        ->assertRedirect('/beneficiaries');

    $this->assertSoftDeleted('beneficiaries', [
        'id' => $fixture['beneficiary']->id,
    ]);

    $this->assertDatabaseHas('project_enrollments', [
        'id' => $enrollmentId,
        'beneficiary_id' => $fixture['beneficiary']->id,
    ]);

    $this->assertDatabaseHas('next_of_kin', [
        'id' => $nextOfKinId,
    ]);
});

test('archived beneficiaries are excluded from the active beneficiary directory', function () {
    $user = User::factory()->create();
    grantDomainAccess($user, 'beneficiaries');
    $fixture = makeBeneficiaryPageWorkflowFixture();

    $fixture['beneficiary']->delete();

    $this->actingAs($user)
        ->get("/beneficiaries?program_id={$fixture['program']->id}&project_id={$fixture['project']->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Beneficiaries/Index')
            ->has('beneficiary.data', 0)
        );
});
