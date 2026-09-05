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
        Schema::create('dhl', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('production_sandbox')->default(1)->comment('1-production, 2-sandbox');
            $table->string('clientid');
            $table->string('password');
            $table->string('format');
            $table->string('url');
            $table->string('clientid_test');
            $table->string('password_test');
            $table->string('format_test');
            $table->string('url_test');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dhl');
    }
};
