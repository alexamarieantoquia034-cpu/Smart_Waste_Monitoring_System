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
        Schema::create('sensor_data', function (Blueprint $table) {
            $table->id();
            
            $table->float('plastic_level')->default(0);
            $table->float('paper_level')->default(0);
            $table->float('biodegradable_level')->default(0);
            $table->float('reject_level')->default(0);

            $table->float('plastic_distance')->default(0);
            $table->float('paper_distance')->default(0);
            $table->float('biodegradable_distance')->default(0);
            $table->float('reject_distance')->default(0);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_data');
    }
};
