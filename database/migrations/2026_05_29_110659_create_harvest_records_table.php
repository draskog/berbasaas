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
        Schema::create('harvest_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upload_id')->constrained('harvest_uploads')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('harvester_number');
            $table->decimal('weight', 8, 3);
            $table->decimal('tare', 8, 3);
            $table->decimal('gross', 8, 3);
            $table->dateTime('weighed_at');
            $table->timestamps();
            $table->index(['company_id', 'product_id', 'weighed_at']);
            $table->index(['company_id', 'harvester_number', 'weighed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_records');
    }
};
