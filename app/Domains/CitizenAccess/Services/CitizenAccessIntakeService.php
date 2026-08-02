<?php

namespace App\Domains\CitizenAccess\Services;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\Beneficiaries\Support\BeneficiaryIdentityMatcher;
use App\Domains\CitizenAccess\Models\Intake;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\Projects\Models\ProjectEnrollment;
use App\Domains\Projects\Services\ProjectService;
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
                    'current_position' => $data['current_position'] ?? null,
                    'preferred_contact_time' => $data['preferred_contact_time'] ?? null,
                    'heard_about_poa' => $data['heard_about_poa'] ?? null,
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

            if ($configuredNeeds->isEmpty() && empty($data['project_id'])) {
                throw ValidationException::withMessages(['project_id' => ['Select a project when converting an intake without configured offerings.']]);
            }

            $matched = $this->identityMatcher->findMatch([
                'name' => $intake->first_name,
                'surname' => $intake->surname,
                'email' => $intake->email,
                'phone' => $intake->mobile_number,
            ]);

            $beneficiary = $matched ?: Beneficiary::query()->create([
                'name' => $intake->first_name,
                'surname' => $intake->surname,
                'dob' => $intake->date_of_birth,
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

    private function duplicateCandidates(array $data): array
    {
        $candidate = $this->identityMatcher->findMatch([
            'name' => $data['first_name'] ?? null,
            'surname' => $data['surname'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['mobile_number'] ?? null,
        ]);

        return $candidate ? [[
            'beneficiary_id' => $candidate->id,
            'name' => trim($candidate->name.' '.$candidate->surname),
            'match_basis' => 'safe_contact_or_identity_match',
        ]] : [];
    }

    private function nextReference(): string
    {
        do {
            $reference = 'POA-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Intake::query()->where('public_reference', $reference)->exists());

        return $reference;
    }
}
