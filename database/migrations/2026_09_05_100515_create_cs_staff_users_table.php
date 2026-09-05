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
        Schema::create('cs_staff_users', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 150)->nullable();
            $table->string('email', 150)->nullable()->unique('email');
            $table->string('password')->nullable();
            $table->enum('role', ['admin', 'staff'])->nullable()->default('staff');
            $table->dateTime('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cs_staff_users');
    }
};
