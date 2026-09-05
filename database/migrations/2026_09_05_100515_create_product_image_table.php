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
        Schema::create('product_image', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->bigInteger('product_id');
            $table->text('image');
            $table->dateTime('created_at')->useCurrent();

            $table->index(['product_id', 'id'], 'idx_product_image');
            $table->index(['product_id', 'id'], 'idx_product_image_pid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_image');
    }
};
