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
        Schema::create('jt_code', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->bigInteger('order_id');
            $table->string('awb');
            $table->string('jt_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jt_code');
    }
};
