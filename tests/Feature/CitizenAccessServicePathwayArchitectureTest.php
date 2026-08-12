<?php

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServicePathway;
use App\Domains\CitizenAccess\Models\ServicePathwayVersion;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\CitizenAccess\Services\CitizenAccessCaseService;
use App\Domains\Enterprises\Models\Enterprise;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Programs\Models\Program;
use App\Domains\Programs\Models\ProgramCategory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Staff\Models\StaffMember;
use App\Models\Provinces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function pathwayArchitectureGraph(): array
{
    $user = User::factory()->create();
    $department = StaffDepartment::query()->create([
        'name' => 'Citizen Access Architecture',
        'description' => 'Architecture test department.',
    ]);
    $manager = StaffMember::query()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'first_name' => 'Pathway',
        'last_name' => 'Manager',
        'email' => 'pathway-manager-'.Str::lower(Str::random(8)).'@example.test',
        'employee_number' => 'EMP-'.Str::upper(Str::random(8)),
        'status' => 'active',
    ]);
    $category = ProgramCategory::query()->create([
        'name' => 'Citizen Access',
        'slug' => 'citizen-access-'.Str::lower(Str::random(6)),
    ]);
    $program = Program::query()->create([
        'program_category_id' => $category->id,
        'title' => 'Education Access Programme',
        'code' => 'EAP-'.Str::upper(Str::random(5)),
        'description' => 'Strategic education access support.',
        'slug' => 'education-access-'.Str::lower(Str::random(6)),
        'status' => 'active',
    ]);
    $project = Project::query()->create([
        'program_id' => $program->id,
        'project_manager_id' => $manager->id,
        'name' => 'NSFAS 2027 Gauteng Campaign',
        'project_code' => 'NSFAS-'.Str::upper(Str::random(5)),
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);
    $province = Provinces::query()->create(['name' => 'Gauteng '.Str::upper(Str::random(4))]);
    $facilitator = Facilitator::query()->create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Nandi',
        'surname' => 'Facilitator',
        'dob' => now()->subYears(30)->toDateString(),
        'id_number' => fake()->unique()->numerify('#############'),
        'address' => '1 Pathway Street',
        'email' => 'facilitator-'.Str::lower(Str::random(8)).'@example.test',
        'cell' => '0712345678',
        'specialization' => 'Access support',
        'province_id' => $province->id,
    ]);
    $location = ProjectLocation::query()->create([
        'project_id' => $project->id,
        'facilitator_id' => $facilitator->id,
        'province_id' => $province->id,
        'training_venue_address' => 'Soshanguve access desk',
    ]);
    $stream = ServiceStream::query()->create([
        'name' => 'NSFAS support',
        'slug' => 'nsfas-support-'.Str::lower(Str::random(6)),
        'is_active' => true,
    ]);
    $template = RequirementTemplate::query()->create([
        'service_stream_id' => $stream->id,
        'name' => 'NSFAS 2027 requirements',
        'status' => 'published',
    ]);
    $requirementVersion = $template->versions()->create([
        'version_number' => 1,
        'status' => 'published',
        'published_at' => now(),
        'published_by' => $user->id,
    ]);
    RequirementDefinition::query()->create([
        'template_version_id' => $requirementVersion->id,
        'name' => 'South African identity verification',
        'category' => 'eligibility',
        'requirement_status' => 'mandatory',
        'evidence_type' => 'identity_document',
        'display_order' => 1,
        'is_blocking' => true,
    ]);
    $pathway = ServicePathway::query()->create([
        'program_category_id' => $category->id,
        'service_stream_id' => $stream->id,
        'name' => 'NSFAS Application Support',
        'slug' => 'nsfas-application-support-'.Str::lower(Str::random(6)),
        'recipient_type' => 'person',
        'status' => 'active',
    ]);
    $pathwayVersion = $pathway->versions()->create([
        'requirement_template_version_id' => $requirementVersion->id,
        'version_number' => 1,
        'label' => 'NSFAS 2027',
        'status' => 'active',
        'activated_at' => now(),
        'activated_by_user_id' => $user->id,
    ]);
    $pathwayVersion->stages()->createMany([
        ['name' => 'Intake', 'slug' => 'intake', 'display_order' => 1],
        ['name' => 'Eligibility screening', 'slug' => 'eligibility-screening', 'display_order' => 2],
        ['name' => 'Outcome capture', 'slug' => 'outcome-capture', 'display_order' => 3],
    ]);
    $pathwayVersion->outcomeDefinitions()->createMany([
        ['name' => 'Application submitted', 'outcome_type' => 'service_output', 'display_order' => 1],
        ['name' => 'Funding approved', 'outcome_type' => 'immediate_outcome', 'display_order' => 2],
        ['name' => 'Funded', 'outcome_type' => 'longer_term_impact', 'display_order' => 3],
    ]);
    $offering = Opportunity::query()->create([
        'service_stream_id' => $stream->id,
        'program_id' => $program->id,
        'project_id' => $project->id,
        'project_location_id' => $location->id,
        'requirement_template_id' => $template->id,
        'service_pathway_id' => $pathway->id,
        'service_pathway_version_id' => $pathwayVersion->id,
        'name' => 'NSFAS Walk-in Support - Soshanguve',
        'opportunity_type' => 'service_offering',
        'public_slug' => 'nsfas-soshanguve-'.Str::lower(Str::random(6)),
        'public_title' => 'NSFAS Walk-in Support - Soshanguve',
        'public_summary' => 'Assisted NSFAS application support.',
        'is_active' => true,
        'is_published' => true,
        'published_at' => now(),
    ]);
    $beneficiary = Beneficiary::query()->create([
        'name' => 'Lebo',
        'surname' => 'Mokoena',
        'dob' => now()->subYears(20)->toDateString(),
        'age' => 20,
        'id_number' => fake()->unique()->numerify('#############'),
        'email' => 'lebo-'.Str::lower(Str::random(8)).'@example.test',
        'phone' => '0722222222',
        'gender' => 'female',
        'project_id' => $project->id,
        'program_id' => $program->id,
        'province_id' => $province->id,
        'postal_code' => '0001',
    ]);

    return compact('user', 'program', 'project', 'location', 'stream', 'template', 'requirementVersion', 'pathway', 'pathwayVersion', 'offering', 'beneficiary');
}

it('allows a service pathway to carry multiple immutable versions with ordered stages and outcomes', function () {
    $graph = pathwayArchitectureGraph();

    $second = $graph['pathway']->versions()->create([
        'requirement_template_version_id' => $graph['requirementVersion']->id,
        'version_number' => 2,
        'label' => 'NSFAS 2028',
        'status' => 'draft',
    ]);

    expect($graph['pathway']->versions()->count())->toBe(2)
        ->and($graph['pathwayVersion']->stages()->pluck('name')->all())->toBe(['Intake', 'Eligibility screening', 'Outcome capture'])
        ->and($graph['pathwayVersion']->outcomeDefinitions()->pluck('outcome_type')->all())->toBe(['service_output', 'immediate_outcome', 'longer_term_impact'])
        ->and($second->version_number)->toBe(2);
});

it('keeps a support case on its original pathway version after a newer version exists', function () {
    $graph = pathwayArchitectureGraph();
    $case = app(CitizenAccessCaseService::class)->createCase($graph['beneficiary'], [
        'program_id' => $graph['program']->id,
        'project_id' => $graph['project']->id,
        'project_location_id' => $graph['location']->id,
        'service_stream_id' => $graph['stream']->id,
        'opportunity_id' => $graph['offering']->id,
        'template_version_id' => $graph['requirementVersion']->id,
    ], $graph['user']);

    $graph['pathway']->versions()->create([
        'requirement_template_version_id' => $graph['requirementVersion']->id,
        'version_number' => 2,
        'label' => 'NSFAS 2028',
        'status' => 'active',
        'activated_at' => now(),
    ]);

    expect($case->fresh()->service_pathway_version_id)->toBe($graph['pathwayVersion']->id);
});

it('prevents destructive edits to an in-use pathway version', function () {
    $graph = pathwayArchitectureGraph();
    app(CitizenAccessCaseService::class)->createCase($graph['beneficiary'], [
        'program_id' => $graph['program']->id,
        'project_id' => $graph['project']->id,
        'project_location_id' => $graph['location']->id,
        'service_stream_id' => $graph['stream']->id,
        'opportunity_id' => $graph['offering']->id,
    ], $graph['user']);

    expect(fn () => $graph['pathwayVersion']->update(['label' => 'Changed in place']))
        ->toThrow(LogicException::class);
});

it('supports enterprise recipients without storing businesses as beneficiaries', function () {
    $graph = pathwayArchitectureGraph();
    $enterprise = Enterprise::query()->create([
        'legal_name' => 'Soshanguve Trading Cooperative',
        'registration_number' => '2027/123456/07',
        'enterprise_type' => 'cooperative',
        'sector' => 'Retail',
        'is_active' => true,
    ]);
    $enterprise->people()->create([
        'beneficiary_id' => $graph['beneficiary']->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'is_authorised_representative' => true,
    ]);

    $case = app(CitizenAccessCaseService::class)->createEnterpriseCase($enterprise, [
        'program_id' => $graph['program']->id,
        'project_id' => $graph['project']->id,
        'project_location_id' => $graph['location']->id,
        'service_stream_id' => $graph['stream']->id,
        'opportunity_id' => $graph['offering']->id,
    ], $graph['user']);

    expect($case->recipient_type)->toBe('enterprise')
        ->and($case->enterprise_id)->toBe($enterprise->id)
        ->and($case->beneficiary_id)->toBeNull()
        ->and($enterprise->people()->count())->toBe(1);
});

it('rejects support cases with two recipients', function () {
    $graph = pathwayArchitectureGraph();
    $enterprise = Enterprise::query()->create([
        'legal_name' => 'Dual Recipient Test',
        'is_active' => true,
    ]);
    grantDomainAccess($graph['user'], 'citizen-access');

    $this->actingAs($graph['user'])->post('/citizen-access/cases', [
        'recipient_type' => 'person',
        'beneficiary_id' => $graph['beneficiary']->id,
        'enterprise_id' => $enterprise->id,
        'service_stream_id' => $graph['stream']->id,
    ])->assertSessionHasErrors('enterprise_id');
});
