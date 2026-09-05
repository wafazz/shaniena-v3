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
        Schema::create('billplz', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('sandbox_production')->comment('0-sandbox, 1-production');
            $table->string('sand_box_url');
            $table->string('production_url');
            $table->string('api_key', 100);
            $table->string('x_signature', 1500);
            $table->string('bill_collection_id', 50);
            $table->string('payment_collection_slug', 100);
            $table->float('bill_charge', 10);
            $table->float('payment_charge', 10)->comment('1-Seller/Company, 2-Customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billplz');
    }
};
