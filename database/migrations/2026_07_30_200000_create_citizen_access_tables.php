<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_access_service_streams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('citizen_access_institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('institution_type', 80);
            $table->string('province')->nullable();
            $table->string('official_website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('citizen_access_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_stream_id')->constrained('citizen_access_service_streams')->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('citizen_access_institutions')->nullOnDelete();
            $table->string('name');
            $table->string('opportunity_type', 80);
            $table->text('description')->nullable();
            $table->string('official_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['service_stream_id', 'is_active']);
        });

        Schema::create('citizen_access_application_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('citizen_access_opportunities')->cascadeOnDelete();
            $table->string('name');
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->string('official_reference')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('citizen_access_requirement_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_stream_id')->constrained('citizen_access_service_streams')->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('citizen_access_institutions')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('citizen_access_opportunities')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 40)->default('draft');
            $table->timestamps();
        });

        Schema::create('citizen_access_requirement_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('citizen_access_requirement_templates')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 40)->default('draft');
            $table->string('source_reference')->nullable();
            $table->string('source_url')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users', indexName: 'ca_req_tpl_ver_pub_by_fk')->nullOnDelete();
            $table->json('readiness_rules')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'version_number'], 'citizen_access_template_version_unique');
        });

        Schema::create('citizen_access_requirement_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_version_id')->constrained('citizen_access_requirement_template_versions', indexName: 'ca_req_def_tpl_ver_fk')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('applicant_guidance')->nullable();
            $table->string('category', 80)->default('general');
            $table->string('requirement_status', 40)->default('mandatory');
            $table->string('evidence_type', 100)->nullable();
            $table->json('applicability_rules')->nullable();
            $table->string('verification_method', 100)->nullable();
            $table->string('source_url')->nullable();
            $table->date('deadline')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_blocking')->default(true);
            $table->json('expiry_rule')->nullable();
            $table->text('staff_guidance')->nullable();
            $table->timestamps();
        });

        Schema::create('citizen_access_intakes', function (Blueprint $table) {
            $table->id();
            $table->string('public_reference')->unique();
            $table->string('status', 60)->default('new')->index();
            $table->string('source_channel', 80)->default('public_website');
            $table->string('campaign_source')->nullable();
            $table->string('first_name');
            $table->string('surname');
            $table->string('identity_hash')->nullable()->index();
            $table->string('identity_last_four', 4)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('mobile_number');
            $table->string('email')->nullable();
            $table->string('preferred_contact_method', 40)->nullable();
            $table->string('province')->nullable();
            $table->string('municipality')->nullable();
            $table->string('ward_area')->nullable();
            $table->text('assistance_description')->nullable();
            $table->string('preferred_delivery_channel')->nullable();
            $table->boolean('consent_to_contact')->default(false);
            $table->boolean('privacy_notice_accepted')->default(false);
            $table->timestamp('consent_recorded_at')->nullable();
            $table->string('privacy_notice_version', 40)->nullable();
            $table->string('submission_ip_hash')->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 30)->default('normal');
            $table->json('duplicate_candidates')->nullable();
            $table->foreignId('converted_beneficiary_id')->nullable()->constrained('beneficiaries')->nullOnDelete();
            $table->foreignId('linked_beneficiary_id')->nullable()->constrained('beneficiaries')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('correlation_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['mobile_number', 'email']);
        });

        Schema::create('citizen_access_intake_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intake_id')->constrained('citizen_access_intakes')->cascadeOnDelete();
            $table->foreignId('service_stream_id')->nullable()->constrained('citizen_access_service_streams')->nullOnDelete();
            $table->string('need_key');
            $table->string('label');
            $table->timestamps();
            $table->unique(['intake_id', 'need_key'], 'citizen_access_intake_need_unique');
        });

        Schema::create('citizen_access_needs_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->cascadeOnDelete();
            $table->foreignId('intake_id')->nullable()->constrained('citizen_access_intakes')->nullOnDelete();
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('profile');
            $table->text('summary')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('citizen_access_support_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_reference')->unique();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->cascadeOnDelete();
            $table->foreignId('intake_id')->nullable()->constrained('citizen_access_intakes')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('service_stream_id')->constrained('citizen_access_service_streams');
            $table->foreignId('institution_id')->nullable()->constrained('citizen_access_institutions')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('citizen_access_opportunities')->nullOnDelete();
            $table->foreignId('application_cycle_id')->nullable()->constrained('citizen_access_application_cycles')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 30)->default('normal');
            $table->string('stage', 80)->default('needs_identified')->index();
            $table->string('readiness_state', 80)->default('assessment_not_started');
            $table->unsignedTinyInteger('readiness_percentage')->default(0);
            $table->string('eligibility_indication', 80)->default('eligibility_unclear');
            $table->json('readiness_reasons')->nullable();
            $table->date('important_deadline')->nullable();
            $table->string('closure_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['beneficiary_id', 'stage']);
        });

        Schema::create('citizen_access_assessment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained('citizen_access_support_cases')->cascadeOnDelete();
            $table->foreignId('requirement_definition_id')->nullable()->constrained('citizen_access_requirement_definitions', indexName: 'ca_assess_req_def_fk')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('citizen_access_requirement_templates')->nullOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('citizen_access_requirement_template_versions')->nullOnDelete();
            $table->json('requirement_snapshot');
            $table->string('status', 60)->default('not_assessed')->index();
            $table->boolean('is_blocking')->default(true);
            $table->string('evidence_type', 100)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('citizen_access_evidence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->cascadeOnDelete();
            $table->foreignId('document_file_id')->nullable()->constrained('document_files')->nullOnDelete();
            $table->string('evidence_type', 100);
            $table->string('issuer')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('upload_source', 80)->default('erp');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verification_status', 60)->default('pending');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('sensitivity_classification', 80)->default('personal');
            $table->string('retention_category', 80)->default('beneficiary_support');
            $table->string('archive_status', 60)->default('active');
            $table->timestamps();
        });

        Schema::create('citizen_access_assessment_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_item_id')->constrained('citizen_access_assessment_items')->cascadeOnDelete();
            $table->foreignId('evidence_item_id')->constrained('citizen_access_evidence_items')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['assessment_item_id', 'evidence_item_id'], 'citizen_access_assessment_evidence_unique');
        });

        Schema::create('citizen_access_case_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained('citizen_access_support_cases')->cascadeOnDelete();
            $table->foreignId('evidence_item_id')->constrained('citizen_access_evidence_items')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['support_case_id', 'evidence_item_id'], 'citizen_access_case_evidence_unique');
        });

        Schema::create('citizen_access_readiness_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained('citizen_access_support_cases')->cascadeOnDelete();
            $table->foreignId('assessment_item_id')->nullable()->constrained('citizen_access_assessment_items')->nullOnDelete();
            $table->foreignId('work_task_id')->nullable()->constrained('work_tasks')->nullOnDelete();
            $table->string('description');
            $table->string('responsible_party', 80)->default('staff');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('priority', 30)->default('normal');
            $table->string('status', 60)->default('open');
            $table->text('completion_evidence')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['support_case_id', 'assessment_item_id', 'description'], 'citizen_access_readiness_action_unique');
        });

        Schema::create('citizen_access_case_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained('citizen_access_support_cases')->cascadeOnDelete();
            $table->string('activity_type', 80);
            $table->string('official_channel')->nullable();
            $table->string('external_reference')->nullable();
            $table->date('submission_date')->nullable();
            $table->foreignId('assisted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submission_evidence_id')->nullable()->constrained('citizen_access_evidence_items')->nullOnDelete();
            $table->string('referral_institution')->nullable();
            $table->string('referral_contact')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('external_status')->nullable();
            $table->string('outcome_category')->nullable();
            $table->date('outcome_date')->nullable();
            $table->foreignId('outcome_evidence_id')->nullable()->constrained('citizen_access_evidence_items')->nullOnDelete();
            $table->string('outcome_verification_status', 60)->default('unverified');
            $table->text('closure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('citizen_access_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->index();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_reference')->nullable();
            $table->string('correlation_id')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_access_audit_events');
        Schema::dropIfExists('citizen_access_case_applications');
        Schema::dropIfExists('citizen_access_readiness_actions');
        Schema::dropIfExists('citizen_access_case_evidence');
        Schema::dropIfExists('citizen_access_assessment_evidence');
        Schema::dropIfExists('citizen_access_evidence_items');
        Schema::dropIfExists('citizen_access_assessment_items');
        Schema::dropIfExists('citizen_access_support_cases');
        Schema::dropIfExists('citizen_access_needs_assessments');
        Schema::dropIfExists('citizen_access_intake_needs');
        Schema::dropIfExists('citizen_access_intakes');
        Schema::dropIfExists('citizen_access_requirement_definitions');
        Schema::dropIfExists('citizen_access_requirement_template_versions');
        Schema::dropIfExists('citizen_access_requirement_templates');
        Schema::dropIfExists('citizen_access_application_cycles');
        Schema::dropIfExists('citizen_access_opportunities');
        Schema::dropIfExists('citizen_access_institutions');
        Schema::dropIfExists('citizen_access_service_streams');
    }
};
