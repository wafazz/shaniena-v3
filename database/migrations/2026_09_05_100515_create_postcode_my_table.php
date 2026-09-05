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
        Schema::create('postcode_my', function (Blueprint $table) {
            $table->char('postcode', 5)->nullable()->index('idx_postcode');
            $table->string('area_name', 100)->nullable()->index('idx_place_name');
            $table->string('post_office', 50)->nullable()->index('idx_city_name');
            $table->char('state_code', 3)->nullable()->index('idx_state_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postcode_my');
    }
};
