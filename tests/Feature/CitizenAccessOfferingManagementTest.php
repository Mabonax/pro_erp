<?php

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\CitizenAccess\Services\CitizenAccessCatalogueService;
use App\Domains\CitizenAccess\Services\OpportunityPublicationReadinessService;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Staff\Models\StaffMember;
use App\Models\Provinces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function offeringManagementGraph(): array
{
    $department = StaffDepartment::query()->create([
        'name' => 'Citizen Access Ops '.Str::upper(Str::random(4)),
        'description' => 'Offering test department.',
    ]);
    $manager = StaffMember::query()->create([
        'department_id' => $department->id,
        'first_name' => 'Offer',
        'last_name' => 'Manager',
        'email' => 'offer-manager-'.Str::lower(Str::random(6)).'@example.test',
        'employee_number' => 'OM-'.Str::upper(Str::random(6)),
        'status' => 'active',
    ]);
    $program = Program::query()->create([
        'title' => 'Citizen Access Test Program',
        'code' => 'CATP-'.Str::upper(Str::random(4)),
        'slug' => 'citizen-access-test-'.Str::lower(Str::random(5)),
        'description' => 'Test programme.',
        'status' => 'active',
    ]);
    $project = Project::query()->create([
        'program_id' => $program->id,
        'project_manager_id' => $manager->id,
        'name' => 'Citizen Access Test Project',
        'project_code' => 'CAT-P-'.Str::upper(Str::random(5)),
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);
    $province = Provinces::query()->create(['name' => 'Gauteng '.Str::upper(Str::random(4))]);
    $facilitator = Facilitator::query()->create([
        'name' => 'Offer',
        'surname' => 'Desk',
        'dob' => '1990-01-01',
        'id_number' => fake()->unique()->numerify('#############'),
        'address' => 'Offering desk',
        'email' => 'offer-desk-'.Str::lower(Str::random(6)).'@example.test',
        'cell' => '0712345678',
        'specialization' => 'Citizen access',
        'province_id' => $province->id,
    ]);
    $location = ProjectLocation::query()->create([
        'project_id' => $project->id,
        'facilitator_id' => $facilitator->id,
        'province_id' => $province->id,
        'training_venue_address' => 'Offering desk',
    ]);
    $stream = ServiceStream::query()->create([
        'name' => 'Funding Access '.Str::upper(Str::random(4)),
        'slug' => 'funding-access-'.Str::lower(Str::random(4)),
        'public_label' => 'Student Funding',
        'public_summary' => 'NSFAS, bursaries and funding application support.',
        'public_display_order' => 2,
        'is_active' => true,
    ]);
    $template = RequirementTemplate::query()->create([
        'service_stream_id' => $stream->id,
        'name' => 'Funding readiness '.Str::upper(Str::random(4)),
        'status' => 'published',
    ]);
    $version = $template->versions()->create([
        'version_number' => 1,
        'status' => 'published',
        'published_at' => now(),
    ]);
    RequirementDefinition::query()->create([
        'template_version_id' => $version->id,
        'name' => 'Identity confirmed',
        'requirement_status' => 'mandatory',
        'evidence_type' => 'identity_document',
        'is_blocking' => true,
    ]);

    return compact('manager', 'program', 'project', 'province', 'facilitator', 'location', 'stream', 'template');
}

function offeringPayload(array $overrides = []): array
{
    $graph = offeringManagementGraph();

    return array_merge([
        'service_stream_id' => $graph['stream']->id,
        'program_id' => $graph['program']->id,
        'project_id' => $graph['project']->id,
        'project_location_id' => $graph['location']->id,
        'requirement_template_id' => $graph['template']->id,
        'owner_staff_id' => $graph['manager']->id,
        'facilitator_id' => $graph['facilitator']->id,
        'name' => 'NSFAS Test Offering',
        'opportunity_type' => 'access_offering',
        'status' => 'draft',
        'description' => 'Internal description.',
        'delivery_channel' => 'assisted_access',
        'delivery_mode' => 'hybrid',
        'target_audience' => 'Applicants',
        'province' => 'Gauteng',
        'public_slug' => 'ca-test-'.Str::lower(Str::random(6)),
        'public_title' => 'NSFAS Test Offering',
        'public_summary' => 'Public summary.',
        'public_help_text' => 'Public help text.',
        'is_active' => true,
        'display_order' => 1,
        'notes' => 'Internal notes.',
        'metadata' => ['canonical_code' => 'CA-TEST', 'source' => 'test'],
    ], $overrides);
}

it('seeds the production catalogue idempotently without duplicating canonical records', function () {
    $service = app(CitizenAccessCatalogueService::class);

    $first = $service->seed();
    $second = app(CitizenAccessCatalogueService::class)->seed();

    expect($first['offerings_created'])->toBe(26)
        ->and($second['offerings_created'])->toBe(0)
        ->and(Program::query()->whereIn('slug', ['citizen-access', 'youth-development', 'entrepreneurship', 'community-support'])->count())->toBe(4)
        ->and(Project::query()->whereIn('project_code', ['CA-GP-2026-27', 'NSFAS-GP-2027', 'POSTSCHOOL-GP-2027', 'SCM-GP-2027', 'YEA-GP-2026-27', 'ENT-GP-2026-27', 'COM-GP-2026-27'])->count())->toBe(7)
        ->and(Opportunity::query()->where('opportunity_type', 'access_offering')->count())->toBe(26)
        ->and(Opportunity::query()->publishedPublic()->count())->toBe(26);
});

it('preserves administrator-managed production offering fields when reseeding the catalogue', function () {
    app(CitizenAccessCatalogueService::class)->seed();

    $offering = Opportunity::query()->where('public_slug', 'ca-nsfas')->firstOrFail();
    $replacement = offeringManagementGraph();
    $offering->update([
        'service_stream_id' => $replacement['stream']->id,
        'program_id' => $replacement['program']->id,
        'project_id' => $replacement['project']->id,
        'project_location_id' => $replacement['location']->id,
        'requirement_template_id' => $replacement['template']->id,
        'owner_staff_id' => $replacement['manager']->id,
        'facilitator_id' => $replacement['facilitator']->id,
        'status' => 'unpublished',
        'is_active' => false,
        'is_published' => false,
        'published_at' => null,
        'archived_at' => now(),
        'public_title' => 'Administrator NSFAS Label',
        'public_summary' => 'Administrator-edited description.',
        'contact_reference' => 'Admin contact desk',
        'province' => 'Western Cape',
    ]);

    app(CitizenAccessCatalogueService::class)->seed();

    $offering->refresh();
    expect($offering->service_stream_id)->toBe($replacement['stream']->id)
        ->and($offering->program_id)->toBe($replacement['program']->id)
        ->and($offering->project_id)->toBe($replacement['project']->id)
        ->and($offering->project_location_id)->toBe($replacement['location']->id)
        ->and($offering->requirement_template_id)->toBe($replacement['template']->id)
        ->and($offering->owner_staff_id)->toBe($replacement['manager']->id)
        ->and($offering->facilitator_id)->toBe($replacement['facilitator']->id)
        ->and($offering->status)->toBe('unpublished')
        ->and($offering->is_active)->toBeFalse()
        ->and($offering->is_published)->toBeFalse()
        ->and($offering->archived_at)->not->toBeNull()
        ->and($offering->public_title)->toBe('Administrator NSFAS Label')
        ->and($offering->public_summary)->toBe('Administrator-edited description.')
        ->and($offering->contact_reference)->toBe('Admin contact desk')
        ->and($offering->province)->toBe('Western Cape');
});

it('does not delete operational offering records when seeding the catalogue', function () {
    $payload = offeringPayload(['public_slug' => 'operator-created-offering', 'name' => 'Operator Created Offering']);
    $operational = Opportunity::query()->create($payload + ['is_published' => false, 'published_at' => null]);

    app(CitizenAccessCatalogueService::class)->seed();

    expect(Opportunity::query()->whereKey($operational->id)->exists())->toBeTrue();
});

it('lets an administrator create and edit an offering', function () {
    $manager = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $payload = offeringPayload();

    $this->actingAs($manager)
        ->post('/citizen-access/admin/offerings', $payload)
        ->assertRedirect();

    $offering = Opportunity::query()->where('public_slug', $payload['public_slug'])->firstOrFail();

    $this->actingAs($manager)
        ->put("/citizen-access/admin/offerings/{$offering->id}", array_merge($payload, [
            'name' => 'Updated Offering',
            'status' => 'ready',
        ]))
        ->assertRedirect();

    expect($offering->refresh()->name)->toBe('Updated Offering')
        ->and($offering->status)->toBe('ready');
});

it('blocks users without Citizen Access authority from creating offerings', function () {
    $payload = offeringPayload();

    $this->actingAs(User::factory()->create())
        ->post('/citizen-access/admin/offerings', $payload)
        ->assertForbidden();
});

it('explains missing readiness relationships and rejects incomplete publishing', function () {
    $manager = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $payload = offeringPayload(['project_location_id' => null, 'status' => 'draft']);
    $offering = Opportunity::query()->create($payload + ['is_published' => false, 'published_at' => null]);

    $readiness = app(OpportunityPublicationReadinessService::class)->evaluate($offering);

    expect($readiness->ready)->toBeFalse()
        ->and(collect($readiness->errors)->pluck('field'))->toContain('project_location_id');

    $this->actingAs($manager)
        ->post("/citizen-access/admin/offerings/{$offering->id}/publish")
        ->assertSessionHasErrors(['project_location_id']);

    $this->actingAs($manager)
        ->get("/citizen-access/admin/offerings/{$offering->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CitizenAccess/Admin/Offerings/Show')
            ->where('offering.publish_readiness.ready', false)
            ->where('offering.publish_readiness.errors.0.field', 'project_location_id')
            ->where('offering.publish_readiness.checks.9.action', 'Relationships section'));
});

it('publishes a ready offering and exposes only safe public API fields', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);
    $manager = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $payload = offeringPayload(['status' => 'draft', 'public_slug' => 'ca-ready-offering']);
    $offering = Opportunity::query()->create($payload + ['is_published' => false, 'published_at' => null]);

    $this->actingAs($manager)
        ->post("/citizen-access/admin/offerings/{$offering->id}/publish")
        ->assertRedirect();

    expect($offering->refresh()->is_published)->toBeTrue()
        ->and($offering->status)->toBe('published');

    $this->withToken('secret-token')
        ->getJson('/api/public/v1/offerings')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'ca-ready-offering'])
        ->assertJsonPath('data.0.support_area.label', 'Student Funding')
        ->assertJsonMissing(['notes' => 'Internal notes.'])
        ->assertJsonMissing(['owner_staff_id' => $payload['owner_staff_id']]);
});

it('removes unpublished deactivated and archived offerings from the public API', function () {
    config(['services.citizen_access.public_intake_token' => 'secret-token']);
    $manager = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $payload = offeringPayload(['status' => 'published', 'public_slug' => 'ca-public-toggle']);
    $offering = Opportunity::query()->create($payload + ['is_published' => true, 'published_at' => now()]);

    $this->actingAs($manager)->post("/citizen-access/admin/offerings/{$offering->id}/unpublish")->assertRedirect();

    $this->withToken('secret-token')
        ->getJson('/api/public/v1/offerings')
        ->assertJsonMissing(['slug' => 'ca-public-toggle']);

    $offering->update(['is_published' => true, 'published_at' => now(), 'status' => 'published']);
    $this->actingAs($manager)->post("/citizen-access/admin/offerings/{$offering->id}/deactivate")->assertRedirect();

    $this->withToken('secret-token')
        ->getJson('/api/public/v1/offerings')
        ->assertJsonMissing(['slug' => 'ca-public-toggle']);

    $offering->update(['is_active' => true, 'is_published' => true, 'published_at' => now(), 'status' => 'published']);
    $this->actingAs($manager)->post("/citizen-access/admin/offerings/{$offering->id}/archive")->assertRedirect();

    $this->withToken('secret-token')
        ->getJson('/api/public/v1/offerings')
        ->assertJsonMissing(['slug' => 'ca-public-toggle']);

    $this->actingAs($manager)->post("/citizen-access/admin/offerings/{$offering->id}/restore")->assertRedirect();
    expect($offering->refresh()->status)->toBe('draft')
        ->and($offering->is_published)->toBeFalse();
});

it('clones an offering as a draft and blocks destructive deletion with historical cases', function () {
    $manager = grantDomainAccess(User::factory()->create(), 'citizen-access');
    $payload = offeringPayload(['status' => 'published', 'public_slug' => 'ca-clone-source']);
    $offering = Opportunity::query()->create($payload + ['is_published' => true, 'published_at' => now()]);

    $this->actingAs($manager)
        ->post("/citizen-access/admin/offerings/{$offering->id}/duplicate")
        ->assertRedirect();

    $copy = Opportunity::query()->where('name', 'NSFAS Test Offering copy')->firstOrFail();
    expect($copy->status)->toBe('draft')
        ->and($copy->is_published)->toBeFalse()
        ->and($copy->public_slug)->toBeNull();

    $beneficiary = Beneficiary::query()->create([
        'name' => 'History',
        'surname' => 'Holder',
        'email' => 'history@example.test',
        'phone' => '0821234567',
        'project_id' => $payload['project_id'],
        'program_id' => $payload['program_id'],
        'attendance_status' => 'active',
    ]);
    SupportCase::query()->create([
        'case_reference' => 'CAS-DEL-001',
        'beneficiary_id' => $beneficiary->id,
        'program_id' => $payload['program_id'],
        'project_id' => $payload['project_id'],
        'project_location_id' => $payload['project_location_id'],
        'service_stream_id' => $payload['service_stream_id'],
        'opportunity_id' => $offering->id,
        'stage' => 'needs_identified',
    ]);

    $this->actingAs($manager)
        ->delete("/citizen-access/admin/offerings/{$offering->id}", ['hard_delete' => true])
        ->assertSessionHasErrors(['hard_delete']);
});
