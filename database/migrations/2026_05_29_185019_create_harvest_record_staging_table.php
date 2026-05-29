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
        Schema::create('harvest_record_staging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upload_id')->references('id')->on('harvest_uploads')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('harvester_number');
            $table->decimal('weight', 8, 3);
            $table->decimal('tare', 8, 3);
            $table->decimal('gross', 8, 3);
            $table->dateTime('weighed_at');
            $table->enum('status', ['pending', 'valid', 'invalid'])->default('pending');
            $table->string('validation_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'upload_id', 'status'], 'hrs_company_upload_status_idx');
            $table->index(['company_id', 'harvester_number', 'weighed_at'], 'hrs_company_harvester_weighed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_record_staging');
    }
};
