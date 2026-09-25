<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classification_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sensor_data_id')
                  ->constrained('sensor_data')
                  ->cascadeOnDelete();

            $table->string('image_path')->nullable();
            $table->string('waste_type');
            $table->float('confidence')->default(0);
            $table->string('compartment');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classification_logs');
    }
};