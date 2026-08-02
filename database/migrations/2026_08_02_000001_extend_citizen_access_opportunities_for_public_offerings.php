<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_access_opportunities', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('institution_id')->constrained('programs', indexName: 'ca_opp_program_fk')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->after('program_id')->constrained('projects', indexName: 'ca_opp_project_fk')->nullOnDelete();
            $table->foreignId('project_location_id')->nullable()->after('project_id')->constrained('project_locations', indexName: 'ca_opp_location_fk')->nullOnDelete();
            $table->foreignId('requirement_template_id')->nullable()->after('project_location_id')->constrained('citizen_access_requirement_templates', indexName: 'ca_opp_req_tpl_fk')->nullOnDelete();
            $table->string('public_slug')->nullable()->after('official_url');
            $table->string('public_title')->nullable()->after('public_slug');
            $table->text('public_summary')->nullable()->after('public_title');
            $table->text('public_help_text')->nullable()->after('public_summary');
            $table->boolean('is_published')->default(false)->after('is_active');
            $table->timestamp('published_at')->nullable()->after('is_published');
            $table->unsignedInteger('display_order')->default(0)->after('published_at');

            $table->unique('public_slug', 'ca_opp_public_slug_unique');
            $table->index(['is_published', 'is_active', 'display_order'], 'ca_opp_public_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::table('citizen_access_opportunities', function (Blueprint $table) {
            $table->dropUnique('ca_opp_public_slug_unique');
            $table->dropIndex('ca_opp_public_listing_idx');
            $table->dropForeign('ca_opp_req_tpl_fk');
            $table->dropForeign('ca_opp_location_fk');
            $table->dropForeign('ca_opp_project_fk');
            $table->dropForeign('ca_opp_program_fk');
            $table->dropColumn([
                'program_id',
                'project_id',
                'project_location_id',
                'requirement_template_id',
                'public_slug',
                'public_title',
                'public_summary',
                'public_help_text',
                'is_published',
                'published_at',
                'display_order',
            ]);
        });
    }
};
