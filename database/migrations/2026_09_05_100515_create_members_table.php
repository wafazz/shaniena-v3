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
        Schema::create('members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 100);
            $table->string('password');
            $table->string('name', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('postcode', 50)->nullable();
            $table->string('state', 50)->nullable();
            $table->string('verification_code', 10)->nullable();
            $table->enum('verification_status', ['unconfirm', 'confirm'])->nullable()->default('unconfirm');
            $table->enum('status', ['inactive', 'active', 'banned'])->nullable()->default('inactive');
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
