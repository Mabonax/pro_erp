<?php

namespace App\Domains\CitizenAccess\Services;

use App\Domains\CitizenAccess\Models\ApplicationCycle;
use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServiceStream;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Programs\Models\Program;
use App\Domains\Programs\Models\ProgramCategory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Staff\Models\StaffMember;
use App\Models\Provinces;
use Illuminate\Support\Str;

class CitizenAccessCatalogueService
{
    private array $counts = [
        'programmes_created' => 0,
        'programmes_updated' => 0,
        'projects_created' => 0,
        'projects_updated' => 0,
        'locations_created' => 0,
        'offerings_created' => 0,
        'offerings_updated' => 0,
        'templates_created' => 0,
        'templates_updated' => 0,
        'cycles_created' => 0,
        'cycles_updated' => 0,
        'publishable_offerings' => 0,
    ];

    public function seed(): array
    {
        $manager = $this->manager();
        $facilitator = $this->facilitator();
        $programmes = $this->programmes($manager);
        $projects = $this->projects($programmes, $manager, $facilitator);
        $institutions = $this->institutions();
        $templates = $this->templates();

        foreach ($this->offerings() as $index => $definition) {
            $streamPublic = $this->streamPublicCopy($definition['stream']);
            $stream = ServiceStream::query()->updateOrCreate(
                ['slug' => Str::slug($definition['stream'])],
                [
                    'name' => $definition['stream'],
                    'public_label' => $streamPublic['label'],
                    'description' => 'Program of Action Citizen Access service stream.',
                    'public_summary' => $streamPublic['summary'],
                    'is_active' => true,
                    'sort_order' => $definition['stream_order'],
                    'public_display_order' => $definition['stream_order'],
                ]
            );

            $program = $programmes[$definition['program']];
            $project = $projects[$definition['project']];
            $location = $project['location'];
            $template = $templates[$definition['template']];

            $opportunity = Opportunity::query()->firstOrNew(['public_slug' => Str::slug($definition['code'])]);
            $wasNew = ! $opportunity->exists;

            if ($wasNew) {
                $opportunity->fill([
                    'service_stream_id' => $stream->id,
                    'institution_id' => $definition['provider'] ? $institutions[$definition['provider']]->id : null,
                    'program_id' => $program->id,
                    'project_id' => $project['project']->id,
                    'project_location_id' => $location->id,
                    'requirement_template_id' => $template->id,
                    'owner_staff_id' => $manager->id,
                    'facilitator_id' => $facilitator->id,
                    'name' => $definition['title'],
                    'opportunity_type' => 'access_offering',
                    'status' => 'published',
                    'description' => $definition['purpose'],
                    'delivery_channel' => $definition['delivery'] ?? 'assisted_access',
                    'delivery_mode' => $definition['mode'] ?? 'hybrid',
                    'target_audience' => $definition['audience'] ?? 'Citizens requiring access and application support',
                    'province' => 'Gauteng',
                    'municipality' => null,
                    'official_url' => null,
                    'external_provider' => $definition['provider'],
                    'contact_reference' => $definition['reference'] ?? null,
                    'public_title' => $definition['title'],
                    'public_summary' => $definition['purpose'],
                    'public_help_text' => 'Program of Action provides access, readiness, application and follow-up support. External institutions keep authority over their own admissions, funding and outcome decisions.',
                    'is_active' => true,
                    'is_published' => true,
                    'published_at' => now(),
                    'archived_at' => null,
                    'opens_on' => null,
                    'closes_on' => null,
                    'capacity' => null,
                    'display_order' => $index + 1,
                    'notes' => null,
                    'metadata' => [
                        'canonical_code' => $definition['code'],
                        'catalogue' => 'program-of-action-citizen-access',
                        'source' => 'production_catalogue_seed',
                        'recipient_context' => $definition['recipient_context'] ?? 'person',
                        'allows_guardian_submission' => $definition['allows_guardian_submission'] ?? false,
                        'requires_beneficiary_details' => $definition['requires_beneficiary_details'] ?? false,
                    ],
                ]);
            } else {
                $metadata = $opportunity->metadata ?? [];
                $metadata['canonical_code'] ??= $definition['code'];
                $metadata['catalogue'] ??= 'program-of-action-citizen-access';
                $metadata['source'] ??= 'production_catalogue_seed';
                $metadata['recipient_context'] ??= $definition['recipient_context'] ?? 'person';
                $metadata['allows_guardian_submission'] ??= $definition['allows_guardian_submission'] ?? false;
                $metadata['requires_beneficiary_details'] ??= $definition['requires_beneficiary_details'] ?? false;

                $opportunity->fill([
                    'metadata' => $metadata,
                ]);
            }

            $opportunity->save();

            if ($wasNew) {
                $this->counts['offerings_created']++;
            } elseif ($opportunity->wasChanged()) {
                $this->counts['offerings_updated']++;
            }

            if ($definition['code'] === 'CA-DOA-BURSARY') {
                $cycle = ApplicationCycle::query()->updateOrCreate(
                    ['opportunity_id' => $opportunity->id, 'name' => 'Department of Agriculture Bursary Awards 2027'],
                    [
                        'opens_on' => null,
                        'closes_on' => '2026-09-30',
                        'official_reference' => '2027 academic cycle',
                        'source_url' => null,
                        'is_active' => true,
                    ]
                );
                $this->countModel($cycle, 'cycles_created', 'cycles_updated');
            }
        }

        $this->counts['publishable_offerings'] = Opportunity::query()->publishedPublic()->count();

        return $this->counts;
    }

    private function programmes(StaffMember $manager): array
    {
        $category = ProgramCategory::query()->firstOrCreate(
            ['slug' => 'program-of-action-service-programmes'],
            ['name' => 'Program of Action Service Programmes', 'description' => 'Canonical programme structure for POA service delivery.']
        );

        $definitions = [
            'citizen-access' => ['Citizen Access Program', 'CA-PROGRAM', 'Help citizens identify, prepare for, apply for and progress through education, funding, employment, skills-development and public opportunities.'],
            'youth-development' => ['Youth Development Program', 'YD-PROGRAM', 'Training, mentoring, readiness and opportunity pathways that move young people toward productive participation.'],
            'entrepreneurship' => ['Entrepreneurship Program', 'ENT-PROGRAM', 'Practical business readiness, formalisation, funding readiness, market access and enterprise development support.'],
            'community-support' => ['Community Support Program', 'CS-PROGRAM', 'Structured access and referral support responding to community needs and connecting people to appropriate institutions and services.'],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $slug) use ($category, $manager) {
            [$title, $code, $description] = $definition;
            $program = Program::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'program_category_id' => $category->id,
                    'title' => $title,
                    'code' => $code,
                    'description' => $description,
                    'start_date' => '2026-01-01',
                    'status' => 'active',
                    'programme_manager_id' => $manager->id,
                ]
            );
            $this->countModel($program, 'programmes_created', 'programmes_updated');

            return [$slug => $program];
        })->all();
    }

    private function projects(array $programmes, StaffMember $manager, Facilitator $facilitator): array
    {
        $definitions = [
            'general' => ['citizen-access', 'CA-GP-2026-27', 'Citizen Access 2026/27 - Gauteng'],
            'nsfas' => ['citizen-access', 'NSFAS-GP-2027', 'NSFAS 2027 Application Support - Gauteng'],
            'postschool' => ['citizen-access', 'POSTSCHOOL-GP-2027', '2027 Post-School Application Support - Gauteng'],
            'second-chance' => ['youth-development', 'SCM-GP-2027', 'Second Chance Matric Access Support - Gauteng'],
            'employment' => ['youth-development', 'YEA-GP-2026-27', 'Youth Employment Access - Gauteng'],
            'entrepreneurship' => ['entrepreneurship', 'ENT-GP-2026-27', 'Entrepreneurship Access Support - Gauteng'],
            'community' => ['community-support', 'COM-GP-2026-27', 'Community Support Access - Gauteng'],
        ];

        $province = Provinces::query()->firstOrCreate(['name' => 'Gauteng']);

        return collect($definitions)->mapWithKeys(function (array $definition, string $key) use ($programmes, $manager, $facilitator, $province) {
            [$programKey, $code, $name] = $definition;
            $project = Project::query()->updateOrCreate(
                ['project_code' => $code],
                [
                    'program_id' => $programmes[$programKey]->id,
                    'project_manager_id' => $manager->id,
                    'name' => $name,
                    'primary_location' => 'Gauteng hybrid access desk',
                    'start_date' => '2026-01-01',
                    'end_date' => null,
                    'status' => 'active',
                    'description' => 'Canonical Citizen Access delivery run for '.$name.'.',
                ]
            );
            $this->countModel($project, 'projects_created', 'projects_updated');

            $location = ProjectLocation::query()->updateOrCreate(
                ['project_id' => $project->id, 'training_venue_address' => 'Gauteng hybrid access desk'],
                [
                    'facilitator_id' => $facilitator->id,
                    'province_id' => $province->id,
                ]
            );
            $this->countModel($location, 'locations_created');

            return [$key => ['project' => $project, 'location' => $location]];
        })->all();
    }

    private function templates(): array
    {
        $definitions = [
            'School Admissions' => [
                ['name' => 'Learner birth certificate or identity document', 'category' => 'identity_or_profile', 'source_url' => 'https://www.education.gov.za/Informationfor/ParentsandGuardians/SchoolAdmissions.aspx'],
                ['name' => 'Parent or guardian contact details', 'category' => 'guardian_contact', 'source_url' => 'https://www.education.gov.za/Informationfor/ParentsandGuardians/SchoolAdmissions.aspx'],
                ['name' => 'Immunisation card', 'category' => 'readiness', 'source_url' => 'https://www.education.gov.za/Informationfor/ParentsandGuardians/SchoolAdmissions.aspx'],
                ['name' => 'Transfer card or latest school report where applicable', 'category' => 'academic_record', 'requirement_status' => 'conditional', 'source_url' => 'https://www.education.gov.za/Informationfor/ParentsandGuardians/SchoolAdmissions.aspx'],
                ['name' => 'Proof of residence or school-zone evidence where requested', 'category' => 'residence', 'requirement_status' => 'conditional', 'source_url' => 'https://www.gdeadmissions.gov.za/'],
                ['name' => 'Study permit or residence permit for non-South African learners where applicable', 'category' => 'immigration', 'requirement_status' => 'conditional', 'source_url' => 'https://www.education.gov.za/Informationfor/ParentsandGuardians/SchoolAdmissions.aspx'],
            ],
            'University Applications' => ['Identity document', 'Latest academic record', 'Programme choices and application profile'],
            'TVET Applications' => ['Identity document', 'Latest academic record', 'Campus and course preference'],
            'NSFAS' => [
                ['name' => 'Student ID document or birth certificate', 'category' => 'identity_or_profile', 'source_url' => 'https://nsfas.org.za/content/faqs.html'],
                ['name' => 'Applicant email address and cellphone number', 'category' => 'contact_profile', 'source_url' => 'https://nsfas.org.za/content/faqs.html'],
                ['name' => 'Parent, guardian or spouse ID documents where applicable', 'category' => 'household_profile', 'requirement_status' => 'conditional', 'source_url' => 'https://nsfas.org.za/content/faqs.html'],
                ['name' => 'Proof of household income where requested', 'category' => 'financial_readiness', 'requirement_status' => 'conditional', 'source_url' => 'https://nsfas.org.za/content/faqs.html'],
                ['name' => 'NSFAS consent form where required', 'category' => 'consent', 'requirement_status' => 'conditional', 'source_url' => 'https://nsfas.org.za/content/faqs.html'],
                ['name' => 'Disability Annexure A where applicable', 'category' => 'disability_support', 'requirement_status' => 'conditional', 'source_url' => 'https://nsfas.org.za/content/faqs.html'],
                ['name' => 'Vulnerable child declaration where applicable', 'category' => 'social_support', 'requirement_status' => 'conditional', 'source_url' => 'https://nsfas.org.za/content/faqs.html'],
            ],
            'Generic Bursary' => ['Identity document', 'Academic record', 'Motivation or supporting statement'],
            'Learnership' => ['Identity document', 'Highest qualification record', 'CV or work-readiness profile'],
            'Internship' => ['Identity document', 'CV', 'Qualification or academic transcript'],
            'WIL' => ['Student registration evidence', 'Institution WIL requirement note', 'CV or profile'],
            'Employment' => ['Identity document', 'CV', 'Contactable references where available'],
            'Second Chance Matric' => ['Identity document', 'Previous matric record or statement of results', 'Contact profile'],
            'Entrepreneurship Assessment' => ['Business profile', 'Owner contact details', 'Current trading or idea description'],
            'Business Compliance' => [
                ['name' => 'Business registration status or CIPC readiness', 'category' => 'business_profile', 'source_url' => 'https://www.cipc.co.za/?page_id=10462'],
                ['name' => 'Owner or authorised representative details', 'category' => 'contact_profile', 'source_url' => 'https://www.cipc.co.za/?page_id=10462'],
                ['name' => 'Tax registration or SARS readiness where applicable', 'category' => 'tax_readiness', 'requirement_status' => 'conditional', 'source_url' => 'https://www.sars.gov.za/businesses-and-employers/small-businesses-taxpayers/starting-a-business-and-tax/'],
            ],
            'Business Funding' => ['Business profile', 'Funding need description', 'Supporting financial or readiness evidence where available'],
            'Workplace Host Readiness' => ['Business profile', 'Workplace safety and supervision capacity', 'Administrative contact details'],
            'CASP' => ['Farmer or enterprise profile', 'Production activity description', 'Location and support need summary'],
            'Document Readiness' => ['Opportunity target', 'Missing-document list', 'Follow-up action plan'],
        ];

        $stream = ServiceStream::query()->updateOrCreate(
            ['slug' => 'catalogue-requirements'],
            ['name' => 'Catalogue requirement templates', 'description' => 'Shared catalogue templates.', 'is_active' => true, 'sort_order' => 99]
        );

        return collect($definitions)->mapWithKeys(function (array $requirements, string $name) use ($stream) {
            $template = RequirementTemplate::query()->updateOrCreate(
                ['service_stream_id' => $stream->id, 'name' => $name.' readiness checklist'],
                [
                    'description' => 'Canonical readiness checklist for '.$name.'. Officers assess readiness without making unsupported legal eligibility decisions.',
                    'status' => 'published',
                ]
            );
            $this->countModel($template, 'templates_created', 'templates_updated');

            $version = $template->versions()->updateOrCreate(
                ['version_number' => 1],
                [
                    'status' => 'published',
                    'source_reference' => 'Program of Action production catalogue',
                    'published_at' => now(),
                    'readiness_rules' => ['decision_model' => 'human_review_only'],
                ]
            );

            foreach ($requirements as $index => $requirement) {
                $definition = is_array($requirement) ? $requirement : ['name' => $requirement];
                RequirementDefinition::query()->updateOrCreate(
                    ['template_version_id' => $version->id, 'name' => $definition['name']],
                    [
                        'description' => ($definition['description'] ?? $definition['name']).' should be checked by a support officer where relevant.',
                        'applicant_guidance' => 'A support officer will confirm whether this applies to your situation.',
                        'category' => $definition['category'] ?? ($index === 0 ? 'identity_or_profile' : 'readiness'),
                        'requirement_status' => $definition['requirement_status'] ?? ($index === 0 ? 'mandatory' : ($index === 1 ? 'mandatory' : 'conditional')),
                        'evidence_type' => $definition['evidence_type'] ?? Str::slug($definition['name'], '_'),
                        'verification_method' => 'officer_review',
                        'source_url' => $definition['source_url'] ?? null,
                        'display_order' => $index + 1,
                        'is_blocking' => $definition['is_blocking'] ?? $index < 2,
                        'staff_guidance' => 'Do not automatically disqualify a citizen; record the evidence/readiness state and follow official channel guidance.',
                    ]
                );
            }

            return [$name => $template];
        })->all();
    }

    private function institutions(): array
    {
        $names = [
            'Department of Basic Education',
            'Department of Agriculture',
            'Department of Agriculture / Provincial Departments of Agriculture',
        ];

        return collect($names)->mapWithKeys(function (string $name) {
            return [$name => Institution::query()->updateOrCreate(
                ['name' => $name],
                ['institution_type' => 'external_public_provider', 'is_active' => true]
            )];
        })->all();
    }

    private function manager(): StaffMember
    {
        $department = StaffDepartment::query()->firstOrCreate(
            ['name' => 'Citizen Access Operations'],
            ['description' => 'Canonical Citizen Access catalogue owner.']
        );

        return StaffMember::query()->updateOrCreate(
            ['email' => 'citizen-access-manager@poa.local'],
            [
                'department_id' => $department->id,
                'first_name' => 'Citizen',
                'last_name' => 'Access Manager',
                'employee_number' => 'POA-CAP-MGR',
                'status' => 'active',
            ]
        );
    }

    private function facilitator(): Facilitator
    {
        $province = Provinces::query()->firstOrCreate(['name' => 'Gauteng']);

        return Facilitator::query()->updateOrCreate(
            ['email' => 'citizen-access-desk@poa.local'],
            [
                'name' => 'Citizen',
                'surname' => 'Access Desk',
                'dob' => '1990-01-01',
                'id_number' => '9001015000000',
                'address' => 'Program of Action Gauteng hybrid access desk',
                'cell' => '0700000000',
                'specialization' => 'Citizen access intake',
                'province_id' => $province->id,
            ]
        );
    }

    private function offerings(): array
    {
        return [
            ['code' => 'CA-SCHOOL-ADMISSIONS', 'title' => 'School Admissions Support', 'stream' => 'Education Access', 'stream_order' => 1, 'template' => 'School Admissions', 'program' => 'citizen-access', 'project' => 'general', 'purpose' => 'Assist parents/guardians and learners with public-school admission processes, application readiness, documentation and follow-up.', 'provider' => null, 'recipient_context' => 'child', 'allows_guardian_submission' => true, 'requires_beneficiary_details' => true],
            ['code' => 'CA-UNIVERSITY-APPLICATIONS', 'title' => 'University Application Support', 'stream' => 'Education Access', 'stream_order' => 1, 'template' => 'University Applications', 'program' => 'citizen-access', 'project' => 'postschool', 'purpose' => 'Support applicants with university opportunity identification, application preparation, documentation, submission and follow-up.', 'provider' => null],
            ['code' => 'CA-TVET-APPLICATIONS', 'title' => 'TVET College Application Support', 'stream' => 'Education Access', 'stream_order' => 1, 'template' => 'TVET Applications', 'program' => 'citizen-access', 'project' => 'postschool', 'purpose' => 'Support applicants to identify appropriate TVET pathways, prepare applications and complete submission/follow-up.', 'provider' => null],
            ['code' => 'CA-POSTSCHOOL-GUIDANCE', 'title' => 'Post-School Opportunity Guidance', 'stream' => 'Education Access', 'stream_order' => 1, 'template' => 'University Applications', 'program' => 'citizen-access', 'project' => 'postschool', 'purpose' => 'Help school-leavers compare university, TVET, skills, employment and entrepreneurship pathways.', 'provider' => null],
            ['code' => 'CA-SECOND-CHANCE-MATRIC', 'title' => 'Second Chance Matric Support', 'stream' => 'Education Access', 'stream_order' => 1, 'template' => 'Second Chance Matric', 'program' => 'youth-development', 'project' => 'second-chance', 'purpose' => 'Support qualifying candidates to access the Department of Basic Education Second Chance Matric Programme, understand registration options, access available learning support/resources and follow through toward examination readiness.', 'provider' => 'Department of Basic Education'],
            ['code' => 'CA-NSFAS', 'title' => 'NSFAS Application Support', 'stream' => 'Student Funding Access', 'stream_order' => 2, 'template' => 'NSFAS', 'program' => 'citizen-access', 'project' => 'nsfas', 'purpose' => 'Support eligible citizens with NSFAS readiness, documentation, application submission and application follow-up.', 'provider' => null],
            ['code' => 'CA-BURSARIES', 'title' => 'Bursary Search and Application Support', 'stream' => 'Student Funding Access', 'stream_order' => 2, 'template' => 'Generic Bursary', 'program' => 'citizen-access', 'project' => 'postschool', 'purpose' => 'Help applicants discover suitable bursaries, assess eligibility, prepare documentation, complete applications and track outcomes.', 'provider' => null],
            ['code' => 'CA-DOA-BURSARY', 'title' => 'Department of Agriculture Bursary Support', 'stream' => 'Student Funding Access', 'stream_order' => 2, 'template' => 'Generic Bursary', 'program' => 'citizen-access', 'project' => 'postschool', 'purpose' => 'Provide application-access support for qualifying applicants interested in Department of Agriculture bursary opportunities.', 'provider' => 'Department of Agriculture'],
            ['code' => 'CA-LEARNERSHIPS', 'title' => 'Learnership Application Support', 'stream' => 'Skills and Work Experience Access', 'stream_order' => 3, 'template' => 'Learnership', 'program' => 'youth-development', 'project' => 'employment', 'purpose' => 'Help citizens identify relevant learnership opportunities, meet requirements, prepare applications and track submissions.', 'provider' => null],
            ['code' => 'CA-INTERNSHIPS', 'title' => 'Internship Application Support', 'stream' => 'Skills and Work Experience Access', 'stream_order' => 3, 'template' => 'Internship', 'program' => 'youth-development', 'project' => 'employment', 'purpose' => 'Support applicants in accessing structured internship opportunities.', 'provider' => null],
            ['code' => 'CA-WIL', 'title' => 'Workplace Integrated Learning Support', 'stream' => 'Skills and Work Experience Access', 'stream_order' => 3, 'template' => 'WIL', 'program' => 'youth-development', 'project' => 'employment', 'purpose' => 'Support students/graduates requiring workplace exposure or WIL placement, including readiness and employer referral.', 'provider' => null],
            ['code' => 'CA-GRADUATE-PLACEMENT', 'title' => 'Graduate Placement Support', 'stream' => 'Skills and Work Experience Access', 'stream_order' => 3, 'template' => 'Internship', 'program' => 'youth-development', 'project' => 'employment', 'purpose' => 'Connect eligible unemployed graduates to available graduate placement opportunities and support application readiness.', 'provider' => null],
            ['code' => 'CA-AGRI-GRADUATE', 'title' => 'Agriculture Graduate Opportunity Support', 'stream' => 'Skills and Work Experience Access', 'stream_order' => 3, 'template' => 'Internship', 'program' => 'youth-development', 'project' => 'employment', 'purpose' => 'Support qualifying agriculture-related graduates to identify and apply for relevant Department of Agriculture / sector graduate development opportunities, including graduate placement pathways where available. Placement is not guaranteed.', 'provider' => 'Department of Agriculture'],
            ['code' => 'CA-EMPLOYMENT', 'title' => 'Employment Opportunity Support', 'stream' => 'Employment Access', 'stream_order' => 4, 'template' => 'Employment', 'program' => 'youth-development', 'project' => 'employment', 'purpose' => 'Help work-seekers identify opportunities, prepare applications, improve CV/readiness and follow applications.', 'provider' => null],
            ['code' => 'CA-CV-JOB-READINESS', 'title' => 'CV and Job Application Readiness', 'stream' => 'Employment Access', 'stream_order' => 4, 'template' => 'Employment', 'program' => 'youth-development', 'project' => 'employment', 'purpose' => 'Provide practical CV, application and interview-readiness support.', 'provider' => null],
            ['code' => 'CA-OPPORTUNITY-PLATFORMS', 'title' => 'Opportunity Platform Registration Support', 'stream' => 'Employment Access', 'stream_order' => 4, 'template' => 'Employment', 'program' => 'citizen-access', 'project' => 'general', 'purpose' => 'Assist citizens to register on recognised employment, skills-development and opportunity platforms.', 'provider' => null],
            ['code' => 'CA-ENTREPRENEUR-ASSESSMENT', 'title' => 'Entrepreneurship Readiness Assessment', 'stream' => 'Entrepreneurship and Economic Participation', 'stream_order' => 5, 'template' => 'Entrepreneurship Assessment', 'program' => 'entrepreneurship', 'project' => 'entrepreneurship', 'purpose' => 'Assess an entrepreneur/business against readiness requirements, identify gaps and generate a development roadmap.', 'provider' => null, 'recipient_context' => 'enterprise'],
            ['code' => 'CA-BUSINESS-COMPLIANCE', 'title' => 'Business Formalisation and Compliance Support', 'stream' => 'Entrepreneurship and Economic Participation', 'stream_order' => 5, 'template' => 'Business Compliance', 'program' => 'entrepreneurship', 'project' => 'entrepreneurship', 'purpose' => 'Help informal and emerging businesses identify and address registration/compliance requirements required to participate in markets, funding and placement ecosystems.', 'provider' => null, 'recipient_context' => 'enterprise'],
            ['code' => 'CA-BUSINESS-FUNDING', 'title' => 'Business Funding Readiness', 'stream' => 'Entrepreneurship and Economic Participation', 'stream_order' => 5, 'template' => 'Business Funding', 'program' => 'entrepreneurship', 'project' => 'entrepreneurship', 'purpose' => 'Assess funding requirements, supporting documentation, business readiness and match businesses to appropriate funding opportunities.', 'provider' => null, 'recipient_context' => 'enterprise'],
            ['code' => 'CA-BUSINESS-MARKET', 'title' => 'Market and Opportunity Linkage', 'stream' => 'Entrepreneurship and Economic Participation', 'stream_order' => 5, 'template' => 'Business Funding', 'program' => 'entrepreneurship', 'project' => 'entrepreneurship', 'purpose' => 'Connect enterprises to appropriate market, procurement, development and partnership opportunities.', 'provider' => null, 'recipient_context' => 'enterprise'],
            ['code' => 'CA-HOST-READINESS', 'title' => 'Workplace Host Readiness', 'stream' => 'Entrepreneurship and Economic Participation', 'stream_order' => 5, 'template' => 'Workplace Host Readiness', 'program' => 'entrepreneurship', 'project' => 'entrepreneurship', 'purpose' => 'Help suitable small businesses become sufficiently structured, compliant and administratively ready to host interns, learners or WIL students where applicable.', 'provider' => null, 'recipient_context' => 'enterprise'],
            ['code' => 'CA-CASP', 'title' => 'CASP Access Support', 'stream' => 'Agricultural Development Access', 'stream_order' => 6, 'template' => 'CASP', 'program' => 'entrepreneurship', 'project' => 'entrepreneurship', 'purpose' => 'Help qualifying emerging/smallholder farmers understand, prepare for and access Comprehensive Agricultural Support Programme opportunities administered through relevant provincial agriculture departments. Program of Action does not control CASP funding decisions.', 'provider' => 'Department of Agriculture / Provincial Departments of Agriculture'],
            ['code' => 'CA-AGRI-TRAINING', 'title' => 'Agricultural Training Opportunity Support', 'stream' => 'Agricultural Development Access', 'stream_order' => 6, 'template' => 'CASP', 'program' => 'youth-development', 'project' => 'employment', 'purpose' => 'Help eligible citizens identify relevant agricultural training, professional development and sector capacity-building opportunities.', 'provider' => null],
            ['code' => 'CA-COMMUNITY-NAVIGATION', 'title' => 'Community Service Navigation', 'stream' => 'Community Support', 'stream_order' => 7, 'template' => 'Document Readiness', 'program' => 'community-support', 'project' => 'community', 'purpose' => 'Assess a citizen/community support need and connect the person to the relevant available institution/service/opportunity.', 'provider' => null],
            ['code' => 'CA-DIGITAL-APPLICATION', 'title' => 'Digital Application Assistance', 'stream' => 'Community Support', 'stream_order' => 7, 'template' => 'Document Readiness', 'program' => 'community-support', 'project' => 'community', 'purpose' => 'Assist citizens who face digital-access barriers to complete legitimate online opportunity/application processes.', 'provider' => null],
            ['code' => 'CA-DOCUMENT-READINESS', 'title' => 'Document Readiness Support', 'stream' => 'Community Support', 'stream_order' => 7, 'template' => 'Document Readiness', 'program' => 'community-support', 'project' => 'community', 'purpose' => 'Help citizens identify missing evidence/documents required for an opportunity and create a readiness action plan.', 'provider' => null],
        ];
    }

    private function streamPublicCopy(string $stream): array
    {
        return match ($stream) {
            'Education Access' => [
                'label' => 'Education',
                'summary' => 'School, university, TVET and Second Chance Matric support.',
            ],
            'Student Funding Access' => [
                'label' => 'Student Funding',
                'summary' => 'NSFAS, bursaries and funding application support.',
            ],
            'Skills and Work Experience Access' => [
                'label' => 'Work & Experience',
                'summary' => 'Learnerships, internships, WIL and graduate opportunities.',
            ],
            'Employment Access' => [
                'label' => 'Employment',
                'summary' => 'Job readiness, applications and employment opportunity support.',
            ],
            'Entrepreneurship and Economic Participation' => [
                'label' => 'Business & Entrepreneurship',
                'summary' => 'Business readiness, compliance, funding and market access support.',
            ],
            'Agricultural Development Access' => [
                'label' => 'Agriculture',
                'summary' => 'Agricultural funding, training and development opportunities.',
            ],
            'Community Support' => [
                'label' => 'General Support',
                'summary' => 'Applications, documents and opportunity navigation.',
            ],
            default => [
                'label' => $stream,
                'summary' => 'Program of Action support area.',
            ],
        };
    }

    private function countModel(object $model, string $createdKey, ?string $updatedKey = null): void
    {
        if (($model->wasRecentlyCreated ?? false) === true) {
            $this->counts[$createdKey]++;

            return;
        }

        if ($updatedKey && method_exists($model, 'wasChanged') && $model->wasChanged()) {
            $this->counts[$updatedKey]++;
        }
    }
}
