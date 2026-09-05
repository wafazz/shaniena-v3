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
        Schema::create('dhl_ship', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('order_id');
            $table->string('deliveryConfirmationNo');
            $table->string('deliveryDepotCode');
            $table->string('primarySortCode');
            $table->string('secondarySortCode');
            $table->string('shipmentID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dhl_ship');
    }
};
