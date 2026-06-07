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
        Schema::create('weather_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('temperature_min', 4, 1)->nullable();
            $table->decimal('temperature_max', 4, 1)->nullable();
            $table->decimal('precipitation_sum', 5, 1)->nullable();
            $table->decimal('wind_speed_max', 5, 1)->nullable();
            $table->smallInteger('weather_code')->nullable();
            $table->json('hourly_precipitation')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_records');
    }
};
