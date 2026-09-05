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
        Schema::create('list_country_product_price', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->bigInteger('country_id');
            $table->bigInteger('product_id');
            $table->float('market_price', 10);
            $table->float('sale_price', 10);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_country_product_price');
    }
};
