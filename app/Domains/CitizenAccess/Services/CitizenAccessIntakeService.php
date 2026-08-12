<?php

namespace App\Domains\CitizenAccess\Services;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\Beneficiaries\Support\BeneficiaryIdentityMatcher;
use App\Domains\CitizenAccess\Models\Intake;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\Enterprises\Models\Enterprise;
use App\Domains\Projects\Models\ProjectEnrollment;
use App\Domains\Projects\Services\ProjectService;
use App\Models\NextOfKin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CitizenAccessIntakeService
{
    public function __construct(
        private CitizenAccessAuditService $audit,
        private BeneficiaryIdentityMatcher $identityMatcher,
        private CitizenAccessCaseService $caseService,
        private ProjectService $projectService,
    ) {}

    public function createPublicIntake(array $data, ?string $ipAddress = null, ?string $userAgent = null): Intake
    {
        $data['recipient_context'] = $this->selectedRecipientContext($data);
        $data['submission_context'] = $data['submission_context'] ?? ($data['recipient_context'] === 'child' ? 'parent_guardian' : ($data['recipient_context'] === 'enterprise' ? 'enterprise_representative' : 'self'));
        $existing = Intake::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            return $existing->load(['needs.stream', 'needs.opportunity']);
        }

        return DB::transaction(function () use ($data, $ipAddress, $userAgent) {
            $intake = Intake::query()->create([
                'public_reference' => $this->nextReference(),
                'status' => 'new',
                'source_channel' => $data['source_channel'] ?? 'public_website',
                'campaign_source' => $data['campaign_source'] ?? null,
                'first_name' => $data['first_name'],
                'surname' => $data['surname'],
                'mobile_number' => $data['mobile_number'],
                'email' => $data['email'] ?? null,
                'preferred_contact_method' => $data['preferred_contact_method'] ?? null,
                'province' => $data['province'] ?? null,
                'municipality' => $data['municipality'] ?? null,
                'ward_area' => $data['ward_area'] ?? null,
                'assistance_description' => $data['assistance_description'] ?? null,
                'preferred_delivery_channel' => $data['preferred_delivery_channel'] ?? null,
                'consent_to_contact' => (bool) ($data['consent_to_contact'] ?? false),
                'privacy_notice_accepted' => (bool) ($data['privacy_notice_accepted'] ?? false),
                'consent_recorded_at' => now(),
                'privacy_notice_version' => $data['privacy_notice_version'] ?? '2026-07',
                'submission_ip_hash' => $ipAddress ? hash('sha256', $ipAddress.config('app.key')) : null,
                'user_agent' => $userAgent ? Str::limit($userAgent, 1000, '') : null,
                'idempotency_key' => $data['idempotency_key'],
                'correlation_id' => $data['correlation_id'] ?? null,
                'duplicate_candidates' => $this->duplicateCandidates($data),
                'meta' => [
                    'submission_context' => $data['submission_context'] ?? 'self',
                    'recipient_context' => $data['recipient_context'] ?? 'person',
                    'requester' => $this->requesterSnapshot($data),
                    'beneficiary' => $this->beneficiarySnapshot($data),
                    'enterprise' => $this->enterpriseSnapshot($data),
                    'current_position' => $data['current_position'] ?? null,
                    'preferred_contact_time' => $data['preferred_contact_time'] ?? null,
                    'heard_about_poa' => $data['heard_about_poa'] ?? null,
                    'consent_to_process_data' => (bool) ($data['consent_to_process_data'] ?? false),
                    'information_accuracy_confirmed' => (bool) ($data['information_accuracy_confirmed'] ?? false),
                ],
            ]);

            $this->syncNeeds($intake, $data['selected_needs'] ?? []);
            $this->audit->record('intake.created', $intake, properties: ['source_channel' => $intake->source_channel]);

            return $intake->load(['needs.stream', 'needs.opportunity']);
        });
    }

    public function assign(Intake $intake, ?int $userId, User $actor): Intake
    {
        $intake->update([
            'assigned_to_user_id' => $userId,
            'status' => $intake->status === 'new' ? 'acknowledged' : $intake->status,
        ]);
        $this->audit->record('intake.assigned', $intake, $actor, ['assigned_to_user_id' => $userId]);

        return $intake->refresh();
    }

    public function updateStatus(Intake $intake, string $status, User $actor): Intake
    {
        $intake->update(['status' => $status]);
        $this->audit->record('intake.status_changed', $intake, $actor, ['status' => $status]);

        return $intake->refresh();
    }

    public function convertToBeneficiary(Intake $intake, array $data, User $actor): Beneficiary
    {
        if (in_array($intake->status, ['converted', 'linked_to_existing_beneficiary'], true)) {
            if ($this->recipientContext($intake) === 'enterprise') {
                $enterprise = $this->resolveEnterprise($intake);

                return DB::transaction(function () use ($intake, $enterprise, $actor) {
                    $freshIntake = $intake->fresh(['needs.opportunity']);
                    $this->createEnterpriseOfferingWorkflow($freshIntake, $enterprise, $actor);

                    return $this->enterpriseContactBeneficiary($freshIntake, [], $actor, $freshIntake->needs->first()?->opportunity);
                });
            }

            $beneficiary = Beneficiary::query()->findOrFail((int) ($intake->converted_beneficiary_id ?: $intake->linked_beneficiary_id));

            return DB::transaction(function () use ($intake, $beneficiary, $actor) {
                $this->createOfferingWorkflow($intake->fresh(['needs.opportunity']), $beneficiary, $actor);

                return $beneficiary->refresh();
            });
        }

        return DB::transaction(function () use ($intake, $data, $actor) {
            $intake->loadMissing(['needs.opportunity']);
            $configuredNeeds = $intake->needs->filter(fn ($need) => $need->opportunity !== null);
            $primaryOpportunity = $configuredNeeds->first()?->opportunity;
            $recipientContext = $this->recipientContext($intake);

            if ($configuredNeeds->isEmpty() && empty($data['project_id'])) {
                throw ValidationException::withMessages(['project_id' => ['Select a project when converting an intake without configured offerings.']]);
            }

            if ($recipientContext === 'enterprise') {
                $enterprise = $this->resolveEnterprise($intake);
                $this->createEnterpriseOfferingWorkflow($intake, $enterprise, $actor);
                $contactBeneficiary = $this->enterpriseContactBeneficiary($intake, $data, $actor, $primaryOpportunity);
                $intake->update([
                    'status' => 'converted',
                    'linked_beneficiary_id' => $contactBeneficiary->id,
                    'converted_at' => now(),
                    'converted_by_user_id' => $actor->id,
                ]);
                $this->audit->record('intake.converted_to_enterprise', $intake, $actor, ['enterprise_id' => $enterprise->id]);

                return $contactBeneficiary;
            }

            $beneficiarySnapshot = $this->recipientBeneficiarySnapshot($intake);
            $matched = $this->identityMatcher->findMatch([
                'name' => $beneficiarySnapshot['first_name'],
                'surname' => $beneficiarySnapshot['surname'],
                'dob' => $beneficiarySnapshot['date_of_birth'] ?? null,
                'email' => $beneficiarySnapshot['email'] ?? null,
                'phone' => $beneficiarySnapshot['mobile_number'] ?? null,
            ]);

            $beneficiary = $matched ?: Beneficiary::query()->create([
                'name' => $beneficiarySnapshot['first_name'],
                'surname' => $beneficiarySnapshot['surname'],
                'dob' => $beneficiarySnapshot['date_of_birth'] ?? null,
                'email' => $beneficiarySnapshot['email'] ?? null,
                'phone' => $beneficiarySnapshot['mobile_number'] ?? null,
                'project_id' => $primaryOpportunity?->project_id ?? $data['project_id'],
                'program_id' => $primaryOpportunity?->program_id ?? ($data['program_id'] ?? null),
                'province_id' => $data['province_id'] ?? null,
                'participation_status' => 'registered',
                'attendance_status' => 'active',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->syncNextOfKinForRequester($intake, $beneficiary);

            if ($primaryOpportunity && ((int) $beneficiary->project_id !== (int) $primaryOpportunity->project_id || (int) $beneficiary->program_id !== (int) $primaryOpportunity->program_id)) {
                $beneficiary->update([
                    'project_id' => $primaryOpportunity->project_id,
                    'program_id' => $primaryOpportunity->program_id,
                    'updated_by' => $actor->id,
                ]);
            }

            if ($configuredNeeds->isEmpty()) {
                $this->ensureEnrollment($beneficiary, (int) $data['project_id'], $data['project_location_id'] ?? null);
            } else {
                $this->createOfferingWorkflow($intake, $beneficiary, $actor);
            }

            $intake->update([
                'status' => $matched ? 'linked_to_existing_beneficiary' : 'converted',
                'converted_beneficiary_id' => $matched ? null : $beneficiary->id,
                'linked_beneficiary_id' => $matched ? $beneficiary->id : null,
                'converted_at' => now(),
                'converted_by_user_id' => $actor->id,
            ]);

            $this->audit->record($matched ? 'intake.linked' : 'intake.converted', $intake, $actor, ['beneficiary_id' => $beneficiary->id]);

            return $beneficiary;
        });
    }

    public function linkBeneficiary(Intake $intake, Beneficiary $beneficiary, User $actor): Intake
    {
        return DB::transaction(function () use ($intake, $beneficiary, $actor) {
            if (! in_array($intake->status, ['converted', 'linked_to_existing_beneficiary'], true)) {
                $intake->update([
                    'status' => 'linked_to_existing_beneficiary',
                    'linked_beneficiary_id' => $beneficiary->id,
                    'converted_at' => now(),
                    'converted_by_user_id' => $actor->id,
                ]);
                $this->audit->record('intake.linked', $intake, $actor, ['beneficiary_id' => $beneficiary->id]);
            }

            $this->createOfferingWorkflow($intake->fresh(['needs.opportunity']), $beneficiary, $actor);

            return $intake->refresh();
        });
    }

    private function createOfferingWorkflow(Intake $intake, Beneficiary $beneficiary, User $actor): void
    {
        $intake->loadMissing(['needs.opportunity.project', 'needs.opportunity.projectLocation', 'needs.opportunity.requirementTemplate']);

        foreach ($intake->needs as $need) {
            $opportunity = $need->opportunity;
            if (! $opportunity) {
                continue;
            }

            $this->ensureCompleteOffering($opportunity);
            $this->ensureEnrollment($beneficiary, (int) $opportunity->project_id, (int) $opportunity->project_location_id);
            $this->projectService->syncProgramMilestones($opportunity->project);

            $case = \App\Domains\CitizenAccess\Models\SupportCase::query()
                ->where('beneficiary_id', $beneficiary->id)
                ->where('intake_id', $intake->id)
                ->where('opportunity_id', $opportunity->id)
                ->first();

            if (! $case) {
                $case = $this->caseService->createCase($beneficiary, [
                    'intake_id' => $intake->id,
                    'program_id' => $opportunity->program_id,
                    'project_id' => $opportunity->project_id,
                    'project_location_id' => $opportunity->project_location_id,
                    'service_stream_id' => $opportunity->service_stream_id,
                    'institution_id' => $opportunity->institution_id,
                    'opportunity_id' => $opportunity->id,
                    'template_version_id' => $opportunity->latestPublishedTemplateVersion()?->id,
                    'assigned_to_user_id' => $intake->assigned_to_user_id ?: $actor->id,
                    'priority' => $intake->priority,
                ], $actor);
            } elseif ($case->assessmentItems()->doesntExist() && ($version = $opportunity->latestPublishedTemplateVersion())) {
                $this->caseService->applyTemplate($case, $version->id, $actor);
            }

            $this->audit->record('offering.workflow_synced', $case, $actor, [
                'intake_id' => $intake->id,
                'opportunity_id' => $opportunity->id,
                'beneficiary_id' => $beneficiary->id,
            ]);
        }
    }

    private function createEnterpriseOfferingWorkflow(Intake $intake, Enterprise $enterprise, User $actor): void
    {
        $intake->loadMissing(['needs.opportunity.project', 'needs.opportunity.projectLocation', 'needs.opportunity.requirementTemplate']);

        foreach ($intake->needs as $need) {
            $opportunity = $need->opportunity;
            if (! $opportunity) {
                continue;
            }

            $this->ensureCompleteOffering($opportunity);

            $case = \App\Domains\CitizenAccess\Models\SupportCase::query()
                ->where('enterprise_id', $enterprise->id)
                ->where('intake_id', $intake->id)
                ->where('opportunity_id', $opportunity->id)
                ->first();

            if (! $case) {
                $case = $this->caseService->createEnterpriseCase($enterprise, [
                    'intake_id' => $intake->id,
                    'program_id' => $opportunity->program_id,
                    'project_id' => $opportunity->project_id,
                    'project_location_id' => $opportunity->project_location_id,
                    'service_stream_id' => $opportunity->service_stream_id,
                    'institution_id' => $opportunity->institution_id,
                    'opportunity_id' => $opportunity->id,
                    'template_version_id' => $opportunity->latestPublishedTemplateVersion()?->id,
                    'assigned_to_user_id' => $intake->assigned_to_user_id ?: $actor->id,
                    'priority' => $intake->priority,
                ], $actor);
            } elseif ($case->assessmentItems()->doesntExist() && ($version = $opportunity->latestPublishedTemplateVersion())) {
                $this->caseService->applyTemplate($case, $version->id, $actor);
            }

            $this->audit->record('offering.workflow_synced', $case, $actor, [
                'intake_id' => $intake->id,
                'opportunity_id' => $opportunity->id,
                'enterprise_id' => $enterprise->id,
            ]);
        }
    }

    private function ensureEnrollment(Beneficiary $beneficiary, int $projectId, ?int $projectLocationId): ?ProjectEnrollment
    {
        if (! $projectLocationId) {
            return null;
        }

        return ProjectEnrollment::query()->updateOrCreate([
            'project_id' => $projectId,
            'beneficiary_id' => $beneficiary->id,
        ], [
            'project_location_id' => $projectLocationId,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
    }

    private function ensureCompleteOffering(Opportunity $opportunity): void
    {
        $opportunity->loadMissing(['serviceStream', 'program', 'project', 'projectLocation', 'requirementTemplate.versions']);

        $messages = [];
        if (! $opportunity->is_active || ! $opportunity->is_published) {
            $messages['selected_needs'][] = "The {$opportunity->public_title} offering is not available for conversion.";
        }
        if (! $opportunity->serviceStream?->is_active) {
            $messages['service_stream_id'][] = 'The selected service stream is inactive.';
        }
        if (! $opportunity->program_id || ! $opportunity->project_id || ! $opportunity->project_location_id || ! $opportunity->requirement_template_id) {
            $messages['opportunity'][] = 'The selected offering has an incomplete programme, project, location or requirement setup.';
        }
        if ($opportunity->project && (int) $opportunity->project->program_id !== (int) $opportunity->program_id) {
            $messages['project_id'][] = 'The selected project does not belong to the offering programme.';
        }
        if ($opportunity->projectLocation && (int) $opportunity->projectLocation->project_id !== (int) $opportunity->project_id) {
            $messages['project_location_id'][] = 'The selected project location does not belong to the offering project.';
        }
        if (! $opportunity->latestPublishedTemplateVersion()) {
            $messages['requirement_template_id'][] = 'The selected requirement template does not have a published version.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function syncNeeds(Intake $intake, array $needs): void
    {
        $normalized = collect($needs)
            ->map(fn ($need) => Str::slug((string) $need))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->count() !== count($needs)) {
            throw ValidationException::withMessages(['selected_needs' => ['Choose each offering only once.']]);
        }

        $offerings = Opportunity::query()
            ->publishedPublic()
            ->with('serviceStream:id,slug,name')
            ->whereIn('public_slug', $normalized->all())
            ->get()
            ->keyBy('public_slug');

        if ($offerings->count() !== $normalized->count()) {
            throw ValidationException::withMessages(['selected_needs' => ['One or more selected offerings are no longer available. Refresh the page and choose again.']]);
        }

        $recipientContexts = $offerings
            ->map(fn (Opportunity $opportunity) => $opportunity->metadata['recipient_context'] ?? 'person')
            ->unique()
            ->values();
        if ($recipientContexts->contains('enterprise') && $recipientContexts->count() > 1) {
            throw ValidationException::withMessages([
                'selected_needs' => ['Choose offerings for one applicant or recipient type per submission. Use a separate form for a different child, adult, or business.'],
            ]);
        }

        foreach ($normalized as $slug) {
            $opportunity = $offerings->get($slug);
            $intake->needs()->create([
                'service_stream_id' => $opportunity->service_stream_id,
                'opportunity_id' => $opportunity->id,
                'need_key' => $opportunity->public_slug,
                'label' => $opportunity->public_title,
            ]);
        }
    }

    private function selectedRecipientContext(array $data): string
    {
        $requested = $data['recipient_context'] ?? 'person';
        $slugs = collect($data['selected_needs'] ?? [])
            ->map(fn ($need) => Str::slug((string) $need))
            ->filter()
            ->unique()
            ->values();

        $contexts = Opportunity::query()
            ->publishedPublic()
            ->whereIn('public_slug', $slugs->all())
            ->get(['metadata'])
            ->map(fn (Opportunity $opportunity) => $opportunity->metadata['recipient_context'] ?? 'person')
            ->unique()
            ->values();

        if ($contexts->contains('enterprise')) {
            return 'enterprise';
        }

        if ($contexts->contains('child') || $requested === 'child') {
            return 'child';
        }

        return 'person';
    }

    private function duplicateCandidates(array $data): array
    {
        $beneficiary = $this->beneficiarySnapshot($data);
        $attributes = $beneficiary ?: $this->requesterSnapshot($data);
        $candidate = $this->identityMatcher->findMatch([
            'name' => $attributes['first_name'] ?? null,
            'surname' => $attributes['surname'] ?? null,
            'dob' => $attributes['date_of_birth'] ?? null,
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['mobile_number'] ?? null,
        ]);

        return $candidate ? [[
            'beneficiary_id' => $candidate->id,
            'name' => trim($candidate->name.' '.$candidate->surname),
            'match_basis' => 'safe_contact_or_identity_match',
        ]] : [];
    }

    private function requesterSnapshot(array $data): array
    {
        return [
            'first_name' => $data['first_name'] ?? null,
            'surname' => $data['surname'] ?? null,
            'mobile_number' => $data['mobile_number'] ?? null,
            'email' => $data['email'] ?? null,
            'relationship_to_beneficiary' => $data['beneficiary_relationship'] ?? null,
        ];
    }

    private function beneficiarySnapshot(array $data): ?array
    {
        if (($data['recipient_context'] ?? 'person') !== 'child') {
            return null;
        }

        return [
            'first_name' => $data['beneficiary_first_name'] ?? null,
            'surname' => $data['beneficiary_surname'] ?? null,
            'date_of_birth' => $data['beneficiary_date_of_birth'] ?? null,
            'grade' => $data['beneficiary_grade'] ?? null,
            'school_year' => $data['beneficiary_school_year'] ?? null,
            'school_name' => $data['beneficiary_school_name'] ?? null,
            'relationship' => $data['beneficiary_relationship'] ?? null,
            'mobile_number' => null,
            'email' => null,
        ];
    }

    private function enterpriseSnapshot(array $data): ?array
    {
        if (($data['recipient_context'] ?? 'person') !== 'enterprise') {
            return null;
        }

        return [
            'name' => $data['enterprise_name'] ?? null,
            'registration_number' => $data['enterprise_registration_number'] ?? null,
            'sector' => $data['enterprise_sector'] ?? null,
            'registration_status' => $data['enterprise_registration_status'] ?? null,
        ];
    }

    private function recipientContext(Intake $intake): string
    {
        $context = $intake->meta['recipient_context'] ?? 'person';

        return in_array($context, ['person', 'child', 'enterprise'], true) ? $context : 'person';
    }

    private function recipientBeneficiarySnapshot(Intake $intake): array
    {
        if ($this->recipientContext($intake) === 'child') {
            $beneficiary = $intake->meta['beneficiary'] ?? [];

            return [
                'first_name' => $beneficiary['first_name'] ?? $intake->first_name,
                'surname' => $beneficiary['surname'] ?? $intake->surname,
                'date_of_birth' => $beneficiary['date_of_birth'] ?? null,
                'mobile_number' => null,
                'email' => null,
            ];
        }

        return [
            'first_name' => $intake->first_name,
            'surname' => $intake->surname,
            'date_of_birth' => $intake->date_of_birth?->format('Y-m-d'),
            'mobile_number' => $intake->mobile_number,
            'email' => $intake->email,
        ];
    }

    private function syncNextOfKinForRequester(Intake $intake, Beneficiary $beneficiary): void
    {
        if ($this->recipientContext($intake) !== 'child') {
            return;
        }

        $requester = $intake->meta['requester'] ?? [];
        if (blank($requester['first_name'] ?? null) && blank($requester['surname'] ?? null) && blank($requester['mobile_number'] ?? null)) {
            return;
        }

        $nextOfKin = $beneficiary->nextOfKin ?: new NextOfKin();
        $nextOfKin->fill([
            'name' => $requester['first_name'] ?? null,
            'surname' => $requester['surname'] ?? null,
            'relationship' => $requester['relationship_to_beneficiary'] ?? 'Parent or guardian',
            'phone' => $requester['mobile_number'] ?? null,
            'email' => $requester['email'] ?? null,
        ]);
        $nextOfKin->save();

        if ((int) $beneficiary->next_of_kin_id !== (int) $nextOfKin->id) {
            $beneficiary->update(['next_of_kin_id' => $nextOfKin->id]);
        }
    }

    private function resolveEnterprise(Intake $intake): Enterprise
    {
        $enterprise = $intake->meta['enterprise'] ?? [];
        $name = $enterprise['name'] ?? null;
        if (blank($name)) {
            throw ValidationException::withMessages(['enterprise_name' => ['Enter the business or enterprise name before conversion.']]);
        }

        $model = Enterprise::query()
            ->when($enterprise['registration_number'] ?? null, fn ($query, $number) => $query->where('registration_number', $number))
            ->when(blank($enterprise['registration_number'] ?? null), fn ($query) => $query->where('legal_name', $name))
            ->first();

        $model ??= Enterprise::query()->create([
            'legal_name' => $name,
            'registration_number' => $enterprise['registration_number'] ?? null,
            'sector' => $enterprise['sector'] ?? null,
            'registration_status' => $enterprise['registration_status'] ?? null,
            'province' => $intake->province,
            'municipality' => $intake->municipality,
            'primary_email' => $intake->email,
            'primary_telephone' => $intake->mobile_number,
            'is_active' => true,
        ]);

        $requester = $intake->meta['requester'] ?? [];
        $model->people()->updateOrCreate([
            'person_email' => $requester['email'] ?? null,
            'person_telephone' => $requester['mobile_number'] ?? null,
            'role' => 'public_intake_contact',
        ], [
            'person_name' => trim(($requester['first_name'] ?? '').' '.($requester['surname'] ?? '')),
            'is_primary_contact' => true,
            'is_authorised_representative' => true,
            'is_active' => true,
            'notes' => 'Created from Citizen Access public intake '.$intake->public_reference,
        ]);

        return $model->refresh();
    }

    private function enterpriseContactBeneficiary(Intake $intake, array $data, User $actor, ?Opportunity $primaryOpportunity): Beneficiary
    {
        $matched = $this->identityMatcher->findMatch([
            'name' => $intake->first_name,
            'surname' => $intake->surname,
            'email' => $intake->email,
            'phone' => $intake->mobile_number,
        ]);

        return $matched ?: Beneficiary::query()->create([
            'name' => $intake->first_name,
            'surname' => $intake->surname,
            'email' => $intake->email,
            'phone' => $intake->mobile_number,
            'project_id' => $primaryOpportunity?->project_id ?? $data['project_id'],
            'program_id' => $primaryOpportunity?->program_id ?? ($data['program_id'] ?? null),
            'province_id' => $data['province_id'] ?? null,
            'participation_status' => 'registered',
            'attendance_status' => 'active',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function nextReference(): string
    {
        do {
            $reference = 'POA-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Intake::query()->where('public_reference', $reference)->exists());

        return $reference;
    }
}
