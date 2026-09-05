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
        Schema::table('pickup_hub_staff', function (Blueprint $table) {
            $table->foreign(['hub_id'], 'fk_pickup_staff_hub')->references(['id'])->on('pickup_hubs')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pickup_hub_staff', function (Blueprint $table) {
            $table->dropForeign('fk_pickup_staff_hub');
        });
    }
};
