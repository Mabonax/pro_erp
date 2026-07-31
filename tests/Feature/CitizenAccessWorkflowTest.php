<?php

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\Intake;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function citizenAccessPayload(array $overrides = []): array
{
    return [
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
    ] + $overrides;
}

function citizenAccessProject(): Project
{
    $program = Program::query()->create([
        'title' => 'Citizen Access Programme',
        'code' => 'CAP-'.Str::upper(Str::random(5)),
        'description' => 'Citizen access workflow test programme.',
        'slug' => 'citizen-access-programme-'.Str::lower(Str::random(5)),
        'status' => 'active',
    ]);

    return Project::query()->create([
        'program_id' => $program->id,
        'name' => 'Citizen Access Project',
        'project_code' => 'CAP-P-'.Str::upper(Str::random(5)),
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);
}

it('requires the scoped public intake token', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);

    $this->postJson('/api/public/v1/intakes', citizenAccessPayload())
        ->assertForbidden();
});

it('creates an intake from the public api and keeps retries idempotent', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);

    ServiceStream::query()->create([
        'name' => 'NSFAS and post-school funding',
        'slug' => 'nsfas-funding',
    ]);

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
        ->and(Intake::query()->first()->needs()->count())->toBe(2);
});

it('lets an officer convert an intake to a beneficiary without deleting the intake', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);
    $officer = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $project = citizenAccessProject();

    $reference = $this->withToken('secret-token')
        ->postJson('/api/public/v1/intakes', citizenAccessPayload())
        ->json('public_reference');

    $intake = Intake::query()->where('public_reference', $reference)->firstOrFail();

    $this->actingAs($officer)
        ->post("/citizen-access/intakes/{$intake->id}/convert", [
            'project_id' => $project->id,
            'program_id' => $project->program_id,
        ])
        ->assertRedirect();

    $intake->refresh();
    expect($intake->status)->toBe('converted')
        ->and($intake->converted_beneficiary_id)->not->toBeNull()
        ->and(Beneficiary::query()->count())->toBe(1);
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
