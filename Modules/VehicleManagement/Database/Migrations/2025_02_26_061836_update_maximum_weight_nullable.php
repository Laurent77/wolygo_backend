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
        Schema::table('vehicle_models', function (Blueprint $table) {
             // Modify the 'maximum_weight' column to allow NULL values
             $table->decimal('maximum_weight')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            // Revert back to not allowing NULL values for 'maximum_weight'
            $table->decimal('maximum_weight')->nullable(false)->change();
        });
    }
};
