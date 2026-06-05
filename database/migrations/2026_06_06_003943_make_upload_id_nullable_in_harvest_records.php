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
            $table->dropForeign(['upload_id']);
            $table->foreignId('upload_id')->nullable()->change();
            $table->foreign('upload_id')
                ->references('id')
                ->on('harvest_uploads')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvest_records', function (Blueprint $table) {
            $table->dropForeign(['upload_id']);
            $table->foreignId('upload_id')->change();
            $table->foreign('upload_id')
                ->references('id')
                ->on('harvest_uploads')
                ->cascadeOnDelete();
        });
    }
};
