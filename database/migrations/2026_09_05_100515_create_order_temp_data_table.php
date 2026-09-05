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
        Schema::create('order_temp_data', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('session_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('add_1');
            $table->string('add_2');
            $table->string('city');
            $table->string('state');
            $table->string('postcode', 50);
            $table->string('country_name');
            $table->bigInteger('country_id');
            $table->string('phone');
            $table->string('email');
            $table->string('remark');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
            $table->dateTime('deleted_at')->nullable();
            $table->string('method');
            $table->string('currency_sign');
            $table->decimal('amount', 10);
            $table->decimal('shipping_cost', 10)->default(0);
            $table->integer('status')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_temp_data');
    }
};
