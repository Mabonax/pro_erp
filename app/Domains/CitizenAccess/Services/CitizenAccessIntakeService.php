<?php

namespace App\Domains\CitizenAccess\Services;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\Beneficiaries\Support\BeneficiaryIdentityMatcher;
use App\Domains\CitizenAccess\Models\Intake;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CitizenAccessIntakeService
{
    public function __construct(
        private CitizenAccessAuditService $audit,
        private BeneficiaryIdentityMatcher $identityMatcher,
    ) {}

    public function createPublicIntake(array $data, ?string $ipAddress = null, ?string $userAgent = null): Intake
    {
        $existing = Intake::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            return $existing->load(['needs.stream']);
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

            return $intake->load(['needs.stream']);
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
            throw ValidationException::withMessages(['intake' => ['This intake has already been converted or linked.']]);
        }

        return DB::transaction(function () use ($intake, $data, $actor) {
            $beneficiary = Beneficiary::query()->create([
                'name' => $intake->first_name,
                'surname' => $intake->surname,
                'dob' => $intake->date_of_birth,
                'email' => $intake->email,
                'phone' => $intake->mobile_number,
                'project_id' => $data['project_id'],
                'program_id' => $data['program_id'] ?? null,
                'province_id' => $data['province_id'] ?? null,
                'participation_status' => 'registered',
                'attendance_status' => 'active',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $intake->update([
                'status' => 'converted',
                'converted_beneficiary_id' => $beneficiary->id,
                'converted_at' => now(),
                'converted_by_user_id' => $actor->id,
            ]);

            $this->audit->record('intake.converted', $intake, $actor, ['beneficiary_id' => $beneficiary->id]);

            return $beneficiary;
        });
    }

    public function linkBeneficiary(Intake $intake, Beneficiary $beneficiary, User $actor): Intake
    {
        $intake->update([
            'status' => 'linked_to_existing_beneficiary',
            'linked_beneficiary_id' => $beneficiary->id,
            'converted_at' => now(),
            'converted_by_user_id' => $actor->id,
        ]);
        $this->audit->record('intake.linked', $intake, $actor, ['beneficiary_id' => $beneficiary->id]);

        return $intake->refresh();
    }

    private function syncNeeds(Intake $intake, array $needs): void
    {
        $streams = ServiceStream::query()->pluck('id', 'slug');
        foreach (array_values(array_unique($needs)) as $need) {
            $key = Str::slug((string) $need);
            $intake->needs()->create([
                'service_stream_id' => $streams[$key] ?? null,
                'need_key' => $key,
                'label' => Str::headline(str_replace(['-', '_'], ' ', (string) $need)),
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
