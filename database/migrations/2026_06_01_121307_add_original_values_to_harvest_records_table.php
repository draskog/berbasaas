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
            $table->unsignedInteger('original_harvester_number')->nullable()->after('harvester_number');
            $table->decimal('original_tare', 8, 3)->nullable()->after('tare');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvest_records', function (Blueprint $table) {
            $table->dropColumn(['original_harvester_number', 'original_tare']);
        });
    }
};
