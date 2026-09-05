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
        Schema::create('postage_cost', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('country_id');
            $table->integer('shipping_zone')->nullable();
            $table->string('currency');
            $table->decimal('first_kilo', 10);
            $table->decimal('next_kilo', 10);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postage_cost');
    }
};
