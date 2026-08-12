<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_access_opportunities', function (Blueprint $table) {
            if (! Schema::hasColumn('citizen_access_opportunities', 'status')) {
                $table->string('status', 40)->default('draft')->after('opportunity_type')->index('ca_opp_status_idx');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'delivery_channel')) {
                $table->string('delivery_channel', 80)->nullable()->after('description');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'delivery_mode')) {
                $table->string('delivery_mode', 40)->nullable()->after('delivery_channel');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'target_audience')) {
                $table->string('target_audience')->nullable()->after('delivery_mode');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'province')) {
                $table->string('province', 120)->nullable()->after('target_audience');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'municipality')) {
                $table->string('municipality', 160)->nullable()->after('province');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'external_provider')) {
                $table->string('external_provider')->nullable()->after('official_url');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'contact_reference')) {
                $table->string('contact_reference')->nullable()->after('external_provider');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'owner_staff_id')) {
                $table->foreignId('owner_staff_id')->nullable()->after('requirement_template_id')->constrained('staff_members', indexName: 'ca_opp_owner_staff_fk')->nullOnDelete();
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'facilitator_id')) {
                $table->foreignId('facilitator_id')->nullable()->after('owner_staff_id')->constrained('facilitators', indexName: 'ca_opp_facilitator_fk')->nullOnDelete();
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'notes')) {
                $table->text('notes')->nullable()->after('capacity');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'metadata')) {
                $table->json('metadata')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('citizen_access_opportunities', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('published_at')->index('ca_opp_archived_at_idx');
            }
        });

        DB::table('citizen_access_opportunities')
            ->where('is_published', true)
            ->whereNull('archived_at')
            ->update(['status' => 'published']);

        DB::table('citizen_access_opportunities')
            ->where('is_published', false)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->update(['status' => 'ready']);

    }

    public function down(): void
    {
        Schema::table('citizen_access_opportunities', function (Blueprint $table) {
            foreach ([
                'ca_opp_facilitator_fk' => 'facilitator_id',
                'ca_opp_owner_staff_fk' => 'owner_staff_id',
            ] as $foreign => $column) {
                if (Schema::hasColumn('citizen_access_opportunities', $column)) {
                    $table->dropForeign($foreign);
                }
            }

            if (Schema::hasColumn('citizen_access_opportunities', 'status')) {
                $table->dropIndex('ca_opp_status_idx');
            }

            if (Schema::hasColumn('citizen_access_opportunities', 'archived_at')) {
                $table->dropIndex('ca_opp_archived_at_idx');
            }

            $columns = [
                'status',
                'delivery_channel',
                'delivery_mode',
                'target_audience',
                'province',
                'municipality',
                'external_provider',
                'contact_reference',
                'owner_staff_id',
                'facilitator_id',
                'notes',
                'metadata',
                'archived_at',
            ];

            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('citizen_access_opportunities', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
