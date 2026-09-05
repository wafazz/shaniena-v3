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
        Schema::create('list_country', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('name');
            $table->string('sign', 50);
            $table->decimal('rate', 10);
            $table->string('phone_code');
            $table->dateTime('created_at')->useCurrentOnUpdate()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->integer('status')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_country');
    }
};
