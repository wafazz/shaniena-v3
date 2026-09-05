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
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('session_id')->index('idx_orders_session');
            $table->bigInteger('order_to');
            $table->string('product_var_id', 1500);
            $table->integer('total_qty');
            $table->decimal('total_price', 10);
            $table->decimal('postage_cost', 10);
            $table->string('currency_sign');
            $table->integer('country_id')->index('idx_orders_country');
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->string('postcode', 50);
            $table->string('address_2');
            $table->string('address_1');
            $table->string('customer_name');
            $table->string('customer_name_last');
            $table->string('customer_phone', 30);
            $table->string('customer_email', 150);
            $table->integer('status')->default(0);
            $table->string('payment_channel');
            $table->string('payment_code');
            $table->string('payment_url');
            $table->string('ship_channel');
            $table->string('courier_service');
            $table->string('awb_number');
            $table->string('tracking_url');
            $table->dateTime('created_at')->useCurrent()->index('idx_created_at');
            $table->dateTime('updated_at')->useCurrent();
            $table->dateTime('deleted_at')->nullable();
            $table->text('remark_comment');
            $table->text('tracking_milestone');
            $table->decimal('to_myr_rate', 10);
            $table->decimal('myr_value_include_postage', 10);
            $table->decimal('myr_value_without_postage', 10);
            $table->integer('printed_awb')->default(0);

            $table->index(['status', 'deleted_at', 'created_at'], 'idx_admin_filter');
            $table->index(['country_id', 'created_at'], 'idx_country_created');
            $table->index(['status', 'deleted_at', 'created_at'], 'idx_orders_checkout');
            $table->index(['created_at'], 'idx_orders_created');
            $table->index(['status', 'deleted_at'], 'idx_orders_status_deleted');
            $table->index(['session_id', 'created_at'], 'idx_session_created');
            $table->index(['status', 'created_at'], 'idx_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_orders');
    }
};
