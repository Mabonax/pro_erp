<?php

namespace Database\Seeders;

use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\OutcomeDefinition;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\PathwayStage;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServicePathway;
use App\Domains\Programs\Models\ProgramCategory;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Staff\Models\StaffMember;
use App\Models\Provinces;
use Illuminate\Database\Seeder;

class CitizenAccessSeeder extends Seeder
{
    public function run(): void
    {
        $streams = [
            'gauteng-school-admissions' => 'Gauteng public-school admissions',
            'nsfas-funding' => 'NSFAS and post-school funding',
            'university-applications' => 'Public university applications',
            'tvet-applications' => 'TVET college applications',
            'bursaries' => 'Bursary applications',
            'opportunity-platforms' => 'Unemployment and opportunity platforms',
            'seta-learnerships' => 'SETA learnerships',
            'internships-wil' => 'Internships and WIL opportunities',
            'employment-readiness' => 'Employment readiness',
            'agriculture-support' => 'Agriculture support programmes',
            'enterprise-livelihood' => 'Enterprise and livelihood support',
            'wellbeing-referral' => 'Youth and family wellbeing referrals',
        ];

        foreach ($streams as $slug => $name) {
            ServiceStream::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => 'Development/sample configuration for citizen access case management.',
                    'is_active' => true,
                ]
            );
        }

        $institution = Institution::query()->updateOrCreate(
            ['name' => 'Sample public institution / official channel'],
            [
                'institution_type' => 'sample',
                'notes' => 'Development/sample configuration. Verify official requirements before production use.',
                'is_active' => true,
            ]
        );
        $deliveryGraph = $this->ensurePublicOfferingDeliveryGraph();

        foreach (ServiceStream::query()->get() as $stream) {
            $opportunity = Opportunity::query()->updateOrCreate(
                ['service_stream_id' => $stream->id, 'name' => $stream->name.' support'],
                [
                    'institution_id' => $institution->id,
                    'program_id' => $deliveryGraph['program']->id,
                    'project_id' => $deliveryGraph['project']->id,
                    'project_location_id' => $deliveryGraph['location']->id,
                    'opportunity_type' => 'access_offering',
                    'status' => 'published',
                    'description' => 'Development/sample opportunity for workflow verification.',
                    'public_slug' => $stream->slug,
                    'public_title' => $stream->name,
                    'public_summary' => 'Development/sample assistance offering for '.$stream->name.'.',
                    'public_help_text' => 'Staff must verify current official requirements before actioning this request.',
                    'is_active' => true,
                    'is_published' => true,
                    'published_at' => now(),
                    'archived_at' => null,
                ]
            );

            $template = RequirementTemplate::query()->updateOrCreate(
                ['service_stream_id' => $stream->id, 'name' => $stream->name.' readiness checklist'],
                [
                    'institution_id' => $institution->id,
                    'opportunity_id' => $opportunity->id,
                    'description' => 'Development/sample checklist. Not an official policy statement.',
                    'status' => 'published',
                ]
            );

            $version = $template->versions()->updateOrCreate(
                ['version_number' => 1],
                [
                    'status' => 'published',
                    'source_reference' => 'Development/sample configuration only',
                    'published_at' => now(),
                ]
            );

            RequirementDefinition::query()->updateOrCreate(
                ['template_version_id' => $version->id, 'name' => 'Identity and contact details verified'],
                [
                    'description' => 'Confirm minimum identity and contact details before application/referral support.',
                    'applicant_guidance' => 'POA will confirm contact details during screening.',
                    'category' => 'screening',
                    'requirement_status' => 'mandatory',
                    'evidence_type' => 'identity_or_contact_record',
                    'verification_method' => 'officer_review',
                    'display_order' => 1,
                    'is_blocking' => true,
                    'staff_guidance' => 'Do not collect sensitive evidence through the anonymous public form.',
                ]
            );

            $opportunity->update(['requirement_template_id' => $template->id]);
        }

        $this->seedRepresentativePathway(
            'Citizen Access',
            'nsfas-funding',
            'NSFAS Application Support',
            'NSFAS 2027',
            'person',
            [
                'Intake',
                'Eligibility screening',
                'Evidence collection',
                'Application preparation',
                'Submission support',
                'Follow-up',
                'Outcome capture',
                'Appeal or escalation',
            ],
            [
                ['South African identity verification', 'eligibility', 'identity_document'],
                ['Household income assessment', 'eligibility', 'income_evidence'],
                ['Academic record', 'evidence', 'academic_record'],
                ['Institution application or acceptance proof', 'conditional', 'institution_proof'],
                ['Verified contact details', 'readiness', 'contact_confirmation'],
            ],
            [
                ['Application prepared', 'service_output'],
                ['Application submitted', 'service_output'],
                ['Funding approved', 'immediate_outcome'],
                ['Funding rejected', 'immediate_outcome'],
                ['Appeal submitted', 'service_output'],
                ['Enrolled', 'longer_term_impact'],
                ['Funded', 'longer_term_impact'],
            ]
        );

        $this->seedRepresentativePathway(
            'Business Support',
            'enterprise-livelihood',
            'Business Compliance Readiness',
            'Compliance Readiness 2027',
            'enterprise',
            [
                'Business intake',
                'Compliance diagnosis',
                'Missing requirement identification',
                'Evidence collection',
                'Referral or assisted registration',
                'Submission tracking',
                'Compliance verification',
                'Outcome capture',
            ],
            [
                ['CIPC registration status', 'eligibility', 'cipc_registration'],
                ['SARS tax number', 'evidence', 'sars_tax_number'],
                ['Tax Compliance Status', 'readiness', 'tax_compliance_status'],
                ['B-BBEE affidavit or certificate', 'conditional', 'bbbee_evidence'],
                ['Business bank account', 'readiness', 'business_bank_account'],
                ['Municipal trading permit where applicable', 'conditional', 'municipal_trading_permit'],
                ['Industry-specific licence where applicable', 'conditional', 'industry_specific_licence'],
            ],
            [
                ['CIPC registered', 'immediate_outcome'],
                ['Tax compliant', 'immediate_outcome'],
                ['B-BBEE evidence completed', 'service_output'],
                ['Tender-ready', 'immediate_outcome'],
                ['Funding-ready', 'immediate_outcome'],
                ['Host-employer ready', 'longer_term_impact'],
                ['Compliance blocked', 'immediate_outcome'],
            ]
        );
    }

    private function ensurePublicOfferingDeliveryGraph(): array
    {
        $department = StaffDepartment::query()->firstOrCreate(
            ['name' => 'Citizen Access Operations'],
            ['description' => 'Development/sample owner for public assistance offerings.']
        );

        $manager = StaffMember::query()->updateOrCreate(
            ['email' => 'citizen-access-manager@poa.local'],
            [
                'department_id' => $department->id,
                'first_name' => 'Citizen',
                'last_name' => 'Access Manager',
                'employee_number' => 'POA-CAP-MGR',
                'status' => 'active',
            ]
        );

        $category = ProgramCategory::query()->firstOrCreate(
            ['slug' => 'citizen-access'],
            ['name' => 'Citizen Access', 'description' => 'Public access-to-opportunity support.']
        );

        $program = Program::query()->updateOrCreate(
            ['slug' => 'citizen-access-programme'],
            [
                'program_category_id' => $category->id,
                'title' => 'Citizen Access Programme',
                'code' => 'CAP',
                'description' => 'Development/sample programme connecting public requests to internal support workflows.',
                'start_date' => now()->startOfYear()->toDateString(),
                'status' => 'active',
                'programme_manager_id' => $manager->id,
            ]
        );

        $project = Project::query()->updateOrCreate(
            ['project_code' => 'CAP-PUBLIC-INTAKE'],
            [
                'program_id' => $program->id,
                'project_manager_id' => $manager->id,
                'name' => 'Public Assistance Intake',
                'primary_location' => 'National remote intake',
                'start_date' => now()->startOfYear()->toDateString(),
                'status' => 'active',
                'description' => 'Development/sample project used to receive public website assistance requests.',
            ]
        );

        $province = Provinces::query()->firstOrCreate(['name' => 'Gauteng']);

        $facilitator = Facilitator::query()->updateOrCreate(
            ['email' => 'citizen-access-desk@poa.local'],
            [
                'name' => 'Citizen',
                'surname' => 'Access Desk',
                'dob' => '1990-01-01',
                'id_number' => '9001015000000',
                'address' => 'Program of Action public intake desk',
                'cell' => '0700000000',
                'specialization' => 'Citizen access intake',
                'province_id' => $province->id,
            ]
        );

        $location = ProjectLocation::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'training_venue_address' => 'Program of Action public intake desk',
            ],
            [
                'facilitator_id' => $facilitator->id,
                'province_id' => $province->id,
            ]
        );

        return compact('program', 'project', 'location');
    }

    private function seedRepresentativePathway(
        string $categoryName,
        string $streamSlug,
        string $pathwayName,
        string $versionLabel,
        string $recipientType,
        array $stages,
        array $requirements,
        array $outcomes
    ): void {
        $category = ProgramCategory::query()->firstOrCreate(
            ['slug' => str($categoryName)->slug()->toString()],
            ['name' => $categoryName, 'description' => 'Representative POA intervention category.']
        );
        $stream = ServiceStream::query()->where('slug', $streamSlug)->first();

        if (! $stream) {
            return;
        }

        $template = RequirementTemplate::query()->updateOrCreate(
            ['service_stream_id' => $stream->id, 'name' => $pathwayName.' requirements'],
            [
                'description' => 'Representative structured requirements for '.$pathwayName.'.',
                'status' => 'published',
            ]
        );
        $requirementVersion = $template->versions()->updateOrCreate(
            ['version_number' => 1],
            [
                'status' => 'published',
                'source_reference' => $versionLabel.' development configuration',
                'published_at' => now(),
            ]
        );

        foreach ($requirements as $index => [$name, $categoryName, $evidenceType]) {
            RequirementDefinition::query()->updateOrCreate(
                ['template_version_id' => $requirementVersion->id, 'name' => $name],
                [
                    'description' => $name.' must be assessed by the support officer.',
                    'category' => $categoryName,
                    'requirement_status' => $categoryName === 'advisory' ? 'optional' : 'mandatory',
                    'evidence_type' => $evidenceType,
                    'display_order' => $index + 1,
                    'is_blocking' => $categoryName !== 'advisory',
                    'staff_guidance' => 'Use human review and official channel guidance; this is not an automated decision.',
                ]
            );
        }

        $pathway = ServicePathway::query()->updateOrCreate(
            ['slug' => str($pathwayName)->slug()->toString()],
            [
                'program_category_id' => $category->id,
                'service_stream_id' => $stream->id,
                'name' => $pathwayName,
                'purpose' => 'Guide Access to Action to Outcome delivery for '.$pathwayName.'.',
                'recipient_type' => $recipientType,
                'status' => 'active',
                'is_active' => true,
            ]
        );
        $pathwayVersion = $pathway->versions()->updateOrCreate(
            ['version_number' => 1],
            [
                'requirement_template_version_id' => $requirementVersion->id,
                'label' => $versionLabel,
                'status' => 'active',
                'activated_at' => now(),
            ]
        );

        foreach ($stages as $index => $stage) {
            PathwayStage::query()->updateOrCreate(
                ['service_pathway_version_id' => $pathwayVersion->id, 'slug' => str($stage)->slug()->toString()],
                ['name' => $stage, 'display_order' => $index + 1, 'is_active' => true]
            );
        }

        foreach ($outcomes as $index => [$name, $type]) {
            OutcomeDefinition::query()->updateOrCreate(
                ['service_pathway_version_id' => $pathwayVersion->id, 'name' => $name],
                ['outcome_type' => $type, 'display_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}
