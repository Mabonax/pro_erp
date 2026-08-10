<?php

use App\Domains\CitizenAccess\Models\EvidenceItem;
use App\Domains\Documents\Models\DocumentFile;
use App\Domains\Documents\Models\DocumentFolder;
use App\Domains\Enterprises\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function enterpriseWorkflowUser(): User
{
    $user = User::factory()->create();
    grantDomainAccess($user, 'citizen-access');

    return $user;
}

function enterpriseWorkflowEnterprise(array $overrides = []): Enterprise
{
    return Enterprise::query()->create(array_merge([
        'legal_name' => 'Soshanguve Trading Cooperative',
        'trading_name' => 'Soshanguve Traders',
        'registration_number' => '2027/123456/07',
        'enterprise_type' => 'cooperative',
        'sector' => 'Retail',
        'registration_status' => 'registered',
        'trading_status' => 'trading',
        'province' => 'Gauteng',
        'municipality' => 'Tshwane',
        'primary_email' => 'info-'.Str::lower(Str::random(6)).'@example.test',
        'primary_telephone' => '0123456789',
        'is_active' => true,
    ], $overrides));
}

it('shows enterprise index and profile pages', function () {
    $user = enterpriseWorkflowUser();
    $enterprise = enterpriseWorkflowEnterprise();

    $this->actingAs($user)
        ->get('/enterprises')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Enterprises/Index')
            ->has('enterprises.data', 1)
            ->where('enterprises.data.0.id', $enterprise->id)
        );

    $this->actingAs($user)
        ->get("/enterprises/{$enterprise->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Enterprises/Show')
            ->where('enterprise.legal_name', 'Soshanguve Trading Cooperative')
            ->where('canManageEnterprise', true)
        );
});

it('creates and updates enterprise profile data', function () {
    $user = enterpriseWorkflowUser();

    $this->actingAs($user)
        ->post('/enterprises', [
            'legal_name' => 'Township Compliance Hub',
            'trading_name' => 'Compliance Hub',
            'registration_number' => '2028/000111/07',
            'enterprise_type' => 'private_company',
            'sector' => 'Professional services',
            'province' => 'Gauteng',
            'municipality' => 'Tshwane',
            'is_active' => true,
        ])
        ->assertRedirect();

    $enterprise = Enterprise::query()->where('legal_name', 'Township Compliance Hub')->firstOrFail();

    $this->actingAs($user)
        ->put("/enterprises/{$enterprise->id}", [
            'legal_name' => 'Township Compliance Hub',
            'trading_name' => 'Compliance Hub Updated',
            'registration_status' => 'registered',
            'trading_status' => 'active',
            'is_active' => true,
        ])
        ->assertRedirect(route('enterprises.show', $enterprise));

    expect($enterprise->fresh()->trading_name)->toBe('Compliance Hub Updated');
});

it('links people to enterprises through governed roles', function () {
    $user = enterpriseWorkflowUser();
    $enterprise = enterpriseWorkflowEnterprise();

    $this->actingAs($user)
        ->post("/enterprises/{$enterprise->id}/people", [
            'person_name' => 'Karabo Molefe',
            'person_email' => 'karabo-'.Str::lower(Str::random(6)).'@example.test',
            'role' => 'owner',
            'is_primary_contact' => true,
            'is_authorised_representative' => true,
        ])
        ->assertRedirect();

    expect($enterprise->people()->where('person_name', 'Karabo Molefe')->where('role', 'owner')->exists())->toBeTrue();
});

it('uploads enterprise evidence through the document library', function () {
    Storage::fake('document_library');
    $user = enterpriseWorkflowUser();
    $enterprise = enterpriseWorkflowEnterprise();

    $this->actingAs($user)
        ->post("/enterprises/{$enterprise->id}/evidence", [
            'evidence_type' => 'tax_compliance_status',
            'title' => 'Tax Compliance PIN',
            'issuer' => 'SARS',
            'verification_status' => 'awaiting_verification',
            'file' => UploadedFile::fake()->create('tax-pin.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('enterprises.show', $enterprise));

    expect(EvidenceItem::query()->where('enterprise_id', $enterprise->id)->where('evidence_type', 'tax_compliance_status')->exists())->toBeTrue()
        ->and(DocumentFolder::query()->where('owner_type', Enterprise::class)->where('owner_id', $enterprise->id)->exists())->toBeTrue()
        ->and(DocumentFile::query()->where('title', 'Tax Compliance PIN')->exists())->toBeTrue();
});

it('opens support case creation with an enterprise preselected', function () {
    $user = enterpriseWorkflowUser();
    $enterprise = enterpriseWorkflowEnterprise();

    $this->actingAs($user)
        ->get("/citizen-access/cases/create?enterprise_id={$enterprise->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CitizenAccess/Cases/Create')
            ->where('selectedEnterpriseId', $enterprise->id)
            ->has('enterprises', 1)
        );
});
