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
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique('slug');
            $table->text('description')->nullable();
            $table->enum('type', ['simple', 'variable'])->default('simple');
            $table->integer('category_id')->index('idx_products_category');
            $table->unsignedInteger('brand_id')->nullable()->index('brand_id');
            $table->decimal('price_capital', 10);
            $table->boolean('status')->nullable()->default(true);
            $table->integer('weight');
            $table->integer('length');
            $table->integer('width');
            $table->integer('height');
            $table->dateTime('created_at')->nullable()->useCurrent()->index('idx_products_created');
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->dateTime('deleted_at')->nullable();

            $table->index(['brand_id'], 'idx_products_brand');
            $table->index(['status', 'deleted_at'], 'idx_products_status_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
