<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sensor_data_id')
                  ->constrained('sensor_data')
                  ->cascadeOnDelete();

            $table->string('compartment');
            $table->string('status');
            $table->text('message');

            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};