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
        Schema::create('cart', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('session_id');
            $table->bigInteger('p_id');
            $table->bigInteger('pv_id');
            $table->integer('quantity');
            $table->decimal('price', 10);
            $table->integer('weight');
            $table->integer('total_weight');
            $table->string('currency_sign');
            $table->bigInteger('country_id');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->index('idx_updated_at');
            $table->dateTime('deleted_at')->nullable();
            $table->integer('status')->comment('0-new, 1-confirm, 2-return, 3-cancel, 4-abandon cart');

            $table->index(['session_id', 'status', 'deleted_at', 'pv_id', 'quantity'], 'idx_cart_checkout');
            $table->index(['status', 'deleted_at'], 'idx_cart_order_status');
            $table->index(['pv_id', 'status'], 'idx_cart_pv_status');
            $table->index(['session_id', 'status', 'deleted_at'], 'idx_cart_session_status');
            $table->index(['deleted_at', 'updated_at'], 'idx_deleted_updated');
            $table->index(['session_id', 'updated_at'], 'idx_session_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart');
    }
};
