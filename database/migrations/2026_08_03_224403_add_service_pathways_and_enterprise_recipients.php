<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_categories')) {
            Schema::create('program_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('programs', 'program_category_id')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->foreignId('program_category_id')->nullable()->after('id')->constrained('program_categories')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('citizen_access_service_pathways')) {
            Schema::create('citizen_access_service_pathways', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_category_id')->nullable()->constrained('program_categories', indexName: 'ca_pathway_category_fk')->nullOnDelete();
                $table->foreignId('service_stream_id')->nullable()->constrained('citizen_access_service_streams', indexName: 'ca_pathway_stream_fk')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('purpose')->nullable();
                $table->text('description')->nullable();
                $table->string('recipient_type', 40)->default('person');
                $table->string('status', 40)->default('draft');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('citizen_access_service_pathway_versions')) {
            Schema::create('citizen_access_service_pathway_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_pathway_id')->constrained('citizen_access_service_pathways', indexName: 'ca_path_ver_pathway_fk')->cascadeOnDelete();
                $table->foreignId('requirement_template_version_id')->nullable()->constrained('citizen_access_requirement_template_versions', indexName: 'ca_path_ver_req_tpl_ver_fk')->nullOnDelete();
                $table->unsignedInteger('version_number');
                $table->string('label');
                $table->string('status', 40)->default('draft');
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->foreignId('activated_by_user_id')->nullable()->constrained('users', indexName: 'ca_path_ver_activated_by_fk')->nullOnDelete();
                $table->text('change_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['service_pathway_id', 'version_number'], 'ca_pathway_version_unique');
            });
        }

        if (! Schema::hasTable('citizen_access_pathway_stages')) {
            Schema::create('citizen_access_pathway_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_pathway_version_id')->constrained('citizen_access_service_pathway_versions', indexName: 'ca_path_stage_version_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['service_pathway_version_id', 'slug'], 'ca_pathway_stage_slug_unique');
            });
        }

        if (! Schema::hasTable('citizen_access_outcome_definitions')) {
            Schema::create('citizen_access_outcome_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_pathway_version_id')->constrained('citizen_access_service_pathway_versions', indexName: 'ca_outcome_version_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('outcome_type', 40);
            $table->text('description')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_success_indicator')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('enterprises')) {
            Schema::create('enterprises', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->string('registration_number')->nullable()->index();
            $table->string('enterprise_type', 80)->nullable();
            $table->string('sector', 120)->nullable();
            $table->string('registration_status', 80)->nullable();
            $table->string('trading_status', 80)->nullable();
            $table->string('province')->nullable();
            $table->string('municipality')->nullable();
            $table->text('physical_address')->nullable();
            $table->string('primary_email')->nullable();
            $table->string('primary_telephone')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('enterprise_person_roles')) {
            Schema::create('enterprise_person_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('beneficiaries')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('person_name')->nullable();
            $table->string('person_email')->nullable();
            $table->string('person_telephone')->nullable();
            $table->string('role', 80);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_authorised_representative')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['enterprise_id', 'role', 'is_active'], 'enterprise_person_role_lookup');
            });
        }

        Schema::table('citizen_access_opportunities', function (Blueprint $table) {
            if (! Schema::hasColumn('citizen_access_opportunities', 'service_pathway_id')) {
                $table->foreignId('service_pathway_id')->nullable()->after('requirement_template_id')->constrained('citizen_access_service_pathways', indexName: 'ca_opp_pathway_fk')->nullOnDelete();
            }
            if (! Schema::hasColumn('citizen_access_opportunities', 'service_pathway_version_id')) {
                $table->foreignId('service_pathway_version_id')->nullable()->after('service_pathway_id')->constrained('citizen_access_service_pathway_versions', indexName: 'ca_opp_pathway_version_fk')->nullOnDelete();
            }
            if (! Schema::hasColumn('citizen_access_opportunities', 'opens_on')) {
                $table->date('opens_on')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('citizen_access_opportunities', 'closes_on')) {
                $table->date('closes_on')->nullable()->after('opens_on');
            }
            if (! Schema::hasColumn('citizen_access_opportunities', 'capacity')) {
                $table->unsignedInteger('capacity')->nullable()->after('closes_on');
            }
        });

        Schema::table('citizen_access_support_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('citizen_access_support_cases', 'enterprise_id')) {
                $table->foreignId('enterprise_id')->nullable()->after('beneficiary_id')->constrained('enterprises')->nullOnDelete();
            }
            if (! Schema::hasColumn('citizen_access_support_cases', 'service_pathway_version_id')) {
                $table->foreignId('service_pathway_version_id')->nullable()->after('opportunity_id')->constrained('citizen_access_service_pathway_versions', indexName: 'ca_case_pathway_version_fk')->nullOnDelete();
            }
            if (! Schema::hasColumn('citizen_access_support_cases', 'recipient_type')) {
                $table->string('recipient_type', 40)->default('person')->after('service_pathway_version_id');
            }
            $table->index(['enterprise_id', 'stage'], 'ca_case_enterprise_stage_idx');
        });

        Schema::table('citizen_access_support_cases', function (Blueprint $table) {
            $table->foreignId('beneficiary_id')->nullable()->change();
        });

        Schema::table('citizen_access_evidence_items', function (Blueprint $table) {
            if (! Schema::hasColumn('citizen_access_evidence_items', 'enterprise_id')) {
                $table->foreignId('enterprise_id')->nullable()->after('beneficiary_id')->constrained('enterprises')->nullOnDelete();
            }
            $table->index(['enterprise_id', 'evidence_type'], 'ca_evidence_enterprise_type_idx');
        });

        Schema::table('citizen_access_evidence_items', function (Blueprint $table) {
            $table->foreignId('beneficiary_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('citizen_access_evidence_items', function (Blueprint $table) {
            $table->dropIndex('ca_evidence_enterprise_type_idx');
            $table->dropForeign(['enterprise_id']);
            $table->dropColumn('enterprise_id');
        });

        Schema::table('citizen_access_support_cases', function (Blueprint $table) {
            $table->dropIndex('ca_case_enterprise_stage_idx');
            $table->dropForeign(['enterprise_id']);
            $table->dropForeign('ca_case_pathway_version_fk');
            $table->dropColumn(['enterprise_id', 'service_pathway_version_id', 'recipient_type']);
        });

        Schema::table('citizen_access_opportunities', function (Blueprint $table) {
            $table->dropForeign('ca_opp_pathway_fk');
            $table->dropForeign('ca_opp_pathway_version_fk');
            $table->dropColumn(['service_pathway_id', 'service_pathway_version_id', 'opens_on', 'closes_on', 'capacity']);
        });

        Schema::dropIfExists('enterprise_person_roles');
        Schema::dropIfExists('enterprises');
        Schema::dropIfExists('citizen_access_outcome_definitions');
        Schema::dropIfExists('citizen_access_pathway_stages');
        Schema::dropIfExists('citizen_access_service_pathway_versions');
        Schema::dropIfExists('citizen_access_service_pathways');

        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['program_category_id']);
            $table->dropColumn('program_category_id');
        });

        Schema::dropIfExists('program_categories');
    }
};
