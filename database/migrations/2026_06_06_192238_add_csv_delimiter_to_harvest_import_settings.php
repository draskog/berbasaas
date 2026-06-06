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
        Schema::table('harvest_import_settings', function (Blueprint $table) {
            $table->string('csv_delimiter', 1)->default(',');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvest_import_settings', function (Blueprint $table) {
            $table->dropColumn('csv_delimiter');
        });
    }
};
