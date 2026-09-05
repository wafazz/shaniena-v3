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
        Schema::table('variant_attribute_values', function (Blueprint $table) {
            $table->foreign(['variant_id'], 'variant_attribute_values_ibfk_1')->references(['id'])->on('product_variants')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['attribute_value_id'], 'variant_attribute_values_ibfk_2')->references(['id'])->on('product_attribute_values')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variant_attribute_values', function (Blueprint $table) {
            $table->dropForeign('variant_attribute_values_ibfk_1');
            $table->dropForeign('variant_attribute_values_ibfk_2');
        });
    }
};
