<?php

namespace App\Domains\CitizenAccess\Services;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\AssessmentItem;
use App\Domains\CitizenAccess\Models\RequirementTemplateVersion;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CitizenAccessCaseService
{
    public function __construct(private CitizenAccessAuditService $audit) {}

    public function createCase(Beneficiary $beneficiary, array $data, User $actor): SupportCase
    {
        return DB::transaction(function () use ($beneficiary, $data, $actor) {
            $case = SupportCase::query()->create([
                'case_reference' => $this->nextCaseReference(),
                'beneficiary_id' => $beneficiary->id,
                'intake_id' => $data['intake_id'] ?? null,
                'program_id' => $data['program_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'project_location_id' => $data['project_location_id'] ?? null,
                'service_stream_id' => $data['service_stream_id'],
                'institution_id' => $data['institution_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'application_cycle_id' => $data['application_cycle_id'] ?? null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? $actor->id,
                'priority' => $data['priority'] ?? 'normal',
                'stage' => 'assessment_not_started',
                'eligibility_indication' => 'eligibility_unclear',
            ]);

            $this->audit->record('case.created', $case, $actor);

            if (! empty($data['template_version_id'])) {
                $this->applyTemplate($case, (int) $data['template_version_id'], $actor);
            }

            return $case->load(['beneficiary', 'serviceStream', 'assessmentItems', 'readinessActions']);
        });
    }

    public function applyTemplate(SupportCase $case, int $templateVersionId, User $actor): SupportCase
    {
        $version = RequirementTemplateVersion::query()
            ->with(['template', 'definitions'])
            ->where('status', 'published')
            ->findOrFail($templateVersionId);

        return DB::transaction(function () use ($case, $version, $actor) {
            foreach ($version->definitions as $definition) {
                if (! $this->definitionApplies($definition->applicability_rules ?? [], $case)) {
                    continue;
                }

                AssessmentItem::query()->firstOrCreate([
                    'support_case_id' => $case->id,
                    'requirement_definition_id' => $definition->id,
                ], [
                    'template_id' => $version->template_id,
                    'template_version_id' => $version->id,
                    'requirement_snapshot' => [
                        'name' => $definition->name,
                        'description' => $definition->description,
                        'category' => $definition->category,
                        'requirement_status' => $definition->requirement_status,
                        'evidence_type' => $definition->evidence_type,
                        'is_blocking' => $definition->is_blocking,
                        'source_url' => $definition->source_url,
                        'deadline' => $definition->deadline?->format('Y-m-d'),
                        'template_version' => $version->version_number,
                        'source_reference' => $version->source_reference,
                    ],
                    'status' => $definition->requirement_status === 'optional' ? 'not_assessed' : 'evidence_missing',
                    'is_blocking' => (bool) $definition->is_blocking,
                    'evidence_type' => $definition->evidence_type,
                ]);
            }

            $case->update(['stage' => 'assessment_in_progress']);
            $this->audit->record('case.template_snapshot_created', $case, $actor, ['template_version_id' => $version->id]);

            return $this->recalculateReadiness($case->refresh(), $actor);
        });
    }

    public function updateAssessmentItem(AssessmentItem $item, string $status, ?string $reason, User $actor): AssessmentItem
    {
        if (in_array($status, ['not_applicable', 'rejected', 'waived_with_reason'], true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => ['A reason is required for this assessment decision.']]);
        }

        $item->update([
            'status' => $status,
            'reason' => $reason,
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
        ]);

        $this->audit->record('assessment.updated', $item, $actor, ['status' => $status]);
        $this->recalculateReadiness($item->supportCase, $actor);

        return $item->refresh();
    }

    public function recalculateReadiness(SupportCase $case, ?User $actor = null): SupportCase
    {
        $items = $case->assessmentItems()->get();
        $blockingUnsatisfied = $items
            ->filter(fn (AssessmentItem $item) => $item->is_blocking)
            ->reject(fn (AssessmentItem $item) => in_array($item->status, ['verified', 'waived_with_reason', 'not_applicable'], true));

        $satisfied = $items->filter(fn (AssessmentItem $item) => in_array($item->status, ['verified', 'waived_with_reason', 'not_applicable'], true))->count();
        $percentage = $items->count() > 0 ? (int) floor(($satisfied / $items->count()) * 100) : 0;
        $state = $items->isEmpty()
            ? 'assessment_not_started'
            : ($blockingUnsatisfied->isEmpty() ? 'ready_for_application_support' : 'not_document_ready');

        $case->update([
            'readiness_state' => $state,
            'readiness_percentage' => $percentage,
            'readiness_reasons' => $blockingUnsatisfied->map(fn (AssessmentItem $item) => [
                'assessment_item_id' => $item->id,
                'requirement' => $item->requirement_snapshot['name'] ?? 'Requirement',
                'status' => $item->status,
            ])->values()->all(),
            'stage' => $state === 'ready_for_application_support' ? 'ready_to_apply' : $case->stage,
        ]);

        foreach ($blockingUnsatisfied as $item) {
            $case->readinessActions()->firstOrCreate([
                'assessment_item_id' => $item->id,
                'description' => 'Resolve readiness gap: '.($item->requirement_snapshot['name'] ?? 'required evidence'),
            ], [
                'responsible_party' => 'staff',
                'assigned_to_user_id' => $case->assigned_to_user_id,
                'priority' => $case->priority,
                'status' => 'open',
            ]);
        }

        $case->readinessActions()
            ->whereNotNull('assessment_item_id')
            ->whereNotIn('assessment_item_id', $blockingUnsatisfied->pluck('id')->all())
            ->where('status', 'open')
            ->update(['status' => 'completed']);

        $this->audit->record('case.readiness_recalculated', $case->refresh(), $actor, ['readiness_state' => $case->readiness_state]);

        return $case->refresh()->load(['assessmentItems', 'readinessActions']);
    }

    private function definitionApplies(array $rules, SupportCase $case): bool
    {
        foreach ($rules as $rule) {
            if (($rule['field'] ?? null) === 'service_stream_id' && (int) ($rule['equals'] ?? 0) !== (int) $case->service_stream_id) {
                return false;
            }
        }

        return true;
    }

    private function nextCaseReference(): string
    {
        do {
            $reference = 'CAS-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (SupportCase::query()->where('case_reference', $reference)->exists());

        return $reference;
    }
}
