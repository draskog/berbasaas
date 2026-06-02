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
        Schema::table('harvest_record_staging', function (Blueprint $table) {
            $table->unsignedInteger('duplicate_of_sequence')->nullable()->after('sequence_number');
        });

        Schema::table('harvest_records', function (Blueprint $table) {
            $table->dropIndex('idx_hr_dedup');
            $table->unique(['company_id', 'product_id', 'harvester_number', 'weighed_at'], 'unique_hr_per_weigh');
        });

        Schema::table('harvest_record_staging', function (Blueprint $table) {
            $table->dropIndex('idx_hrs_dedup');
            $table->index(['company_id', 'product_id', 'harvester_number', 'weighed_at'], 'idx_hrs_dedup_new');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvest_records', function (Blueprint $table) {
            $table->dropUnique('unique_hr_per_weigh');
            $table->index(['company_id', 'product_id', 'harvester_number', 'weighed_at', 'sequence_number'], 'idx_hr_dedup');
        });

        Schema::table('harvest_record_staging', function (Blueprint $table) {
            $table->dropIndex('idx_hrs_dedup_new');
            $table->index(['company_id', 'product_id', 'harvester_number', 'weighed_at', 'sequence_number'], 'idx_hrs_dedup');
        });

        Schema::table('harvest_record_staging', function (Blueprint $table) {
            $table->dropColumn('duplicate_of_sequence');
        });
    }
};
