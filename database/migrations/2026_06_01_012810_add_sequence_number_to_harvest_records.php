<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('harvest_records', function (Blueprint $table) {
            if (! Schema::hasColumn('harvest_records', 'sequence_number')) {
                $table->unsignedInteger('sequence_number')->nullable()->after('weighed_at');
            }
            if (! Schema::hasIndex('harvest_records', 'idx_hr_dedup')) {
                $table->index(['company_id', 'product_id', 'harvester_number', 'weighed_at', 'sequence_number'], 'idx_hr_dedup');
            }
        });

        Schema::table('harvest_record_staging', function (Blueprint $table) {
            if (! Schema::hasColumn('harvest_record_staging', 'sequence_number')) {
                $table->unsignedInteger('sequence_number')->nullable()->after('weighed_at');
            }
            if (! Schema::hasIndex('harvest_record_staging', 'idx_hrs_dedup')) {
                $table->index(['company_id', 'product_id', 'harvester_number', 'weighed_at', 'sequence_number'], 'idx_hrs_dedup');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvest_records', function (Blueprint $table) {
            $table->dropIndex('idx_hr_dedup');
            $table->dropColumn('sequence_number');
        });

        Schema::table('harvest_record_staging', function (Blueprint $table) {
            $table->dropIndex('idx_hrs_dedup');
            $table->dropColumn('sequence_number');
        });
    }
};
