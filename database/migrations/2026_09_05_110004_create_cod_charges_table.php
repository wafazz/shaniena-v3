<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ported from the source project's pending migration_cod_charges.sql.
// Benchmark-based COD fee: one row per country + shipping zone.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_charges', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('country_id');
            $table->string('shipping_zone', 10)->default('1')->comment('1=West MY, 2=East MY, 1=default for other countries');
            $table->decimal('benchmark_amount', 10, 2)->default(0)->comment('The threshold amount');
            $table->decimal('cod_fee_below', 10, 2)->default(0)->comment('COD fee if sales < benchmark');
            $table->decimal('cod_fee_above', 10, 2)->default(0)->comment('COD fee if sales >= benchmark');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['country_id', 'shipping_zone'], 'idx_country_zone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_charges');
    }
};
