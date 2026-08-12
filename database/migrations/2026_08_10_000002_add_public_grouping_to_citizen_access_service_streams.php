<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_access_service_streams', function (Blueprint $table) {
            if (! Schema::hasColumn('citizen_access_service_streams', 'public_label')) {
                $table->string('public_label', 120)->nullable()->after('name');
            }

            if (! Schema::hasColumn('citizen_access_service_streams', 'public_summary')) {
                $table->text('public_summary')->nullable()->after('description');
            }

            if (! Schema::hasColumn('citizen_access_service_streams', 'public_display_order')) {
                $table->unsignedInteger('public_display_order')->default(0)->after('sort_order')->index('ca_stream_public_order_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('citizen_access_service_streams', function (Blueprint $table) {
            if (Schema::hasColumn('citizen_access_service_streams', 'public_display_order')) {
                $table->dropIndex('ca_stream_public_order_idx');
            }

            $columns = array_values(array_filter([
                'public_label',
                'public_summary',
                'public_display_order',
            ], fn (string $column): bool => Schema::hasColumn('citizen_access_service_streams', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
