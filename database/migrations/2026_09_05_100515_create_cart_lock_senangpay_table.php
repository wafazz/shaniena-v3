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
        Schema::create('cart_lock_senangpay', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->bigInteger('cart_id');
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
            $table->dateTime('updated_at')->useCurrent();
            $table->dateTime('locked_date')->nullable()->index('idx_locked_date');
            $table->dateTime('deleted_at')->nullable();
            $table->integer('status')->comment('0-new, 1-confirm, 2-return, 3-cancel, 4-abandon cart');

            $table->index(['deleted_at', 'locked_date'], 'idx_deleted_locked');
            $table->index(['session_id', 'locked_date'], 'idx_session_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_lock_senangpay');
    }
};
