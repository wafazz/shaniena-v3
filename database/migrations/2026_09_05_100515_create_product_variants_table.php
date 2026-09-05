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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id')->index('product_id');
            $table->string('variant_name')->nullable();
            $table->string('sku', 100)->nullable()->unique('sku');
            $table->decimal('price_retail', 10);
            $table->decimal('price_sale', 10);
            $table->unsignedInteger('stock')->nullable()->default(0);
            $table->string('image')->nullable();
            $table->integer('max_purchase');
            $table->boolean('status')->nullable()->default(true);
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
