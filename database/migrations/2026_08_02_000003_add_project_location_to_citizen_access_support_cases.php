<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_access_support_cases', function (Blueprint $table) {
            $table->foreignId('project_location_id')->nullable()->after('project_id')->constrained('project_locations', indexName: 'ca_case_location_fk')->nullOnDelete();
            $table->index(['intake_id', 'opportunity_id'], 'ca_case_intake_opp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('citizen_access_support_cases', function (Blueprint $table) {
            $table->dropIndex('ca_case_intake_opp_idx');
            $table->dropForeign('ca_case_location_fk');
            $table->dropColumn('project_location_id');
        });
    }
};
