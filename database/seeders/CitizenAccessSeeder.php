<?php

namespace Database\Seeders;

use App\Domains\CitizenAccess\Models\Institution;
use App\Domains\CitizenAccess\Models\Opportunity;
use App\Domains\CitizenAccess\Models\RequirementDefinition;
use App\Domains\CitizenAccess\Models\RequirementTemplate;
use App\Domains\CitizenAccess\Models\ServiceStream;
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

        foreach (ServiceStream::query()->get() as $stream) {
            $opportunity = Opportunity::query()->updateOrCreate(
                ['service_stream_id' => $stream->id, 'name' => $stream->name.' support'],
                [
                    'institution_id' => $institution->id,
                    'opportunity_type' => 'support_stream',
                    'description' => 'Development/sample opportunity for workflow verification.',
                    'is_active' => true,
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
        }
    }
}
