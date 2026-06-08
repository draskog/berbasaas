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
        Schema::create('current_weather_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->dateTime('time');
            $table->decimal('temperature', 4, 1)->nullable();
            $table->smallInteger('weather_code')->nullable();
            $table->decimal('wind_speed', 5, 1)->nullable();
            $table->decimal('precipitation', 5, 1)->nullable();
            $table->smallInteger('humidity')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('current_weather_records');
    }
};
