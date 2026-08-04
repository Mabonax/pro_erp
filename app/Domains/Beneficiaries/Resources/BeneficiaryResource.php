<?php

namespace App\Domains\Beneficiaries\Resources;

use App\Domains\Projects\Models\ProjectEnrollment;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class BeneficiaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $currentEnrollment = $this->resolveCurrentEnrollment();

        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'member_name' => $this->member
                ? trim($this->member->first_name.' '.$this->member->last_name)
                : null,
            'beneficiary_number' => $this->beneficiary_number,

            // Personal info
            'name' => $this->name,
            'surname' => $this->surname,
            'full_name' => trim("{$this->name} {$this->surname}"),
            'dob' => $this->dob?->format('Y-m-d'),
            'age' => $this->age,

            // Identification
            'id_number' => $this->id_number,
            'email' => $this->email,
            'phone' => $this->phone,

            // Demographics
            'gender' => $this->gender,

            // Project
            'project_id' => $this->project_id,
            'project_name' => $this->project?->name,
            'project_location_id' => $currentEnrollment?->project_location_id,
            'project_location' => $currentEnrollment?->location?->province?->name,
            'program_id' => $this->program_id ?? $this->project?->program?->id,
            'program_title' => $this->program?->title ?? $this->project?->program?->title,
            'member_branch' => $this->member?->branch?->name,
            'member_township' => $this->member?->township?->name,
            'member_province' => $this->member?->province?->name,

            // Address
            'street_address' => $this->street_address,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'province_id' => $this->province_id, // ✅ FIXED

            // Education
            'highest_qualification' => $this->highest_qualification,
            'attendance_status' => $this->attendance_status ?? 'active',
            'enrolment_date' => $this->enrolment_date?->format('Y-m-d'),
            'exit_date' => $this->exit_date?->format('Y-m-d'),
            'participation_status' => $this->participation_status ?? 'registered',
            'placement_status' => $this->placement_status,

            // Relations
            'next_of_kin_id' => $this->next_of_kin_id,
            'next_of_kin' => $this->whenLoaded('nextOfKin', function () {
                return [
                    'id' => $this->nextOfKin->id,
                    'name' => $this->nextOfKin->name,
                    'surname' => $this->nextOfKin->surname,
                    'relationship' => $this->nextOfKin->relationship,
                    'phone' => $this->nextOfKin->phone,
                    'email' => $this->nextOfKin->email,
                ];
            }),
            'current_participation' => $this->whenLoaded('projectEnrollments', function () {
                $currentEnrollment = $this->resolveCurrentEnrollment();

                if (! $currentEnrollment) {
                    return null;
                }

                return [
                    'project_id' => $currentEnrollment->project_id,
                    'project_name' => $currentEnrollment->project?->name,
                    'program_id' => $currentEnrollment->project?->program?->id,
                    'program_title' => $currentEnrollment->project?->program?->title,
                    'location_id' => $currentEnrollment->project_location_id,
                    'location_name' => $currentEnrollment->location?->province?->name,
                    'status' => $currentEnrollment->status,
                    'enrolled_at' => $currentEnrollment->enrolled_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'participation_history' => $this->whenLoaded('projectEnrollments', function () {
                return $this->projectEnrollments
                    ->sortByDesc(fn ($enrollment) => optional($enrollment->enrolled_at)?->timestamp ?? 0)
                    ->values()
                    ->map(fn ($enrollment) => [
                        'id' => $enrollment->id,
                        'project_id' => $enrollment->project_id,
                        'project_name' => $enrollment->project?->name,
                        'program_id' => $enrollment->project?->program?->id,
                        'program_title' => $enrollment->project?->program?->title,
                        'location_id' => $enrollment->project_location_id,
                        'location_name' => $enrollment->location?->province?->name,
                        'status' => $enrollment->status,
                        'project_start_date' => $enrollment->project?->start_date?->format('Y-m-d'),
                        'project_end_date' => $enrollment->project?->end_date?->format('Y-m-d'),
                        'enrolled_at' => $enrollment->enrolled_at?->format('Y-m-d H:i:s'),
                    ]);
            }),
            'support_cases' => $this->whenLoaded('supportCases', function () {
                return $this->supportCases
                    ->sortByDesc(fn ($case) => optional($case->created_at)?->timestamp ?? 0)
                    ->values()
                    ->map(fn ($case) => [
                        'id' => $case->id,
                        'case_reference' => $case->case_reference,
                        'service_stream' => $case->serviceStream?->name,
                        'opportunity' => $case->opportunity?->name,
                        'stage' => $case->stage,
                        'priority' => $case->priority,
                        'readiness_state' => $case->readiness_state,
                        'readiness_percentage' => $case->readiness_percentage,
                        'important_deadline' => $case->important_deadline?->format('Y-m-d'),
                        'created_at' => $case->created_at?->format('Y-m-d H:i'),
                    ]);
            }),
            'evidence_items' => $this->whenLoaded('evidenceItems', function () {
                return $this->evidenceItems
                    ->sortByDesc(fn ($item) => optional($item->created_at)?->timestamp ?? 0)
                    ->values()
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'document_file_id' => $item->document_file_id,
                        'document_title' => $item->documentFile?->title,
                        'document_original_name' => $item->documentFile?->original_name,
                        'download_url' => $item->documentFile
                            ? route('organization.document-library.files.download', $item->documentFile)
                            : null,
                        'evidence_type' => $item->evidence_type,
                        'issuer' => $item->issuer,
                        'verification_status' => $item->verification_status,
                        'issue_date' => $item->issue_date?->format('Y-m-d'),
                        'expiry_date' => $item->expiry_date?->format('Y-m-d'),
                        'sensitivity_classification' => $item->sensitivity_classification,
                        'archive_status' => $item->archive_status,
                        'created_at' => $item->created_at?->format('Y-m-d H:i'),
                    ]);
            }),
            'milestone_assessments' => $this->whenLoaded('milestoneAssessments', function () {
                return $this->milestoneAssessments
                    ->sortByDesc(fn ($assessment) => optional($assessment->assessed_at ?? $assessment->created_at)?->timestamp ?? 0)
                    ->values()
                    ->map(fn ($assessment) => [
                        'id' => $assessment->id,
                        'milestone' => $assessment->milestone?->title,
                        'project_id' => $assessment->milestone?->project_id,
                        'project_name' => $assessment->milestone?->project?->name,
                        'status' => $assessment->status,
                        'score' => $assessment->score,
                        'assessed_at' => $assessment->assessed_at?->format('Y-m-d H:i'),
                    ]);
            }),
            'service_journey_summary' => [
                'participation_count' => $this->whenLoaded('projectEnrollments', fn () => $this->projectEnrollments->count(), 0),
                'support_case_count' => $this->whenLoaded('supportCases', fn () => $this->supportCases->count(), 0),
                'open_support_case_count' => $this->whenLoaded(
                    'supportCases',
                    fn () => $this->supportCases->whereNull('closed_at')->count(),
                    0
                ),
                'evidence_item_count' => $this->whenLoaded('evidenceItems', fn () => $this->evidenceItems->count(), 0),
                'milestone_assessment_count' => $this->whenLoaded('milestoneAssessments', fn () => $this->milestoneAssessments->count(), 0),
                'completed_milestone_assessment_count' => $this->whenLoaded(
                    'milestoneAssessments',
                    fn () => $this->milestoneAssessments->where('status', 'completed')->count(),
                    0
                ),
            ],

            // Audit
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    protected function resolveCurrentEnrollment(): ?ProjectEnrollment
    {
        if (! $this->relationLoaded('projectEnrollments')) {
            return null;
        }

        $enrollments = $this->sortedProjectEnrollments();

        return $enrollments->firstWhere('project_id', $this->project_id)
            ?? $enrollments->first();
    }

    protected function sortedProjectEnrollments(): Collection
    {
        return $this->projectEnrollments
            ->sortByDesc(fn ($enrollment) => optional($enrollment->enrolled_at)?->timestamp ?? 0)
            ->values();
    }
}
