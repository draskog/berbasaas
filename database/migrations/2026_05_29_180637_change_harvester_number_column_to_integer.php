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
            $table->unsignedInteger('harvester_number')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvest_records', function (Blueprint $table) {
            $table->smallInteger('harvester_number')->change();
        });
    }
};
