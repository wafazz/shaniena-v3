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
        Schema::create('dhl_bulk_print', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->text('order_id');
            $table->integer('status')->default(0);
            $table->softDeletes()->nullable(false)->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dhl_bulk_print');
    }
};
