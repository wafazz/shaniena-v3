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
        Schema::create('senangpay_api', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('merchant_id');
            $table->string('secret_key');
            $table->string('pro_merchant_id')->nullable();
            $table->string('pro_secret_key')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('type', ['sandbox', 'production'])->default('sandbox');
            $table->string('sandbox_url')->default('https://sandbox.senangpay.my/');
            $table->string('production_url')->default('https://app.senangpay.my/');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('senangpay_api');
    }
};
