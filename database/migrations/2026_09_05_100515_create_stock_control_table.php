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
        Schema::create('stock_control', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->bigInteger('p_id');
            $table->bigInteger('pv_id')->index('idx_stock_control_pv');
            $table->integer('stock_in');
            $table->integer('stock_out');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->text('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_control');
    }
};
