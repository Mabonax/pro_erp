<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_access_intake_needs', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->nullable()->after('service_stream_id')->constrained('citizen_access_opportunities', indexName: 'ca_need_opp_fk')->nullOnDelete();
            $table->index(['opportunity_id', 'intake_id'], 'ca_need_opp_intake_idx');
        });
    }

    public function down(): void
    {
        Schema::table('citizen_access_intake_needs', function (Blueprint $table) {
            $table->dropIndex('ca_need_opp_intake_idx');
            $table->dropForeign('ca_need_opp_fk');
            $table->dropColumn('opportunity_id');
        });
    }
};
