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
        Schema::create('member_hq', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('email');
            $table->string('password');
            $table->string('sec_pin');
            $table->string('f_name');
            $table->string('l_name');
            $table->string('phone');
            $table->integer('role')->comment('1-HQ, 2-Account, 3-Staff Admin, 4-Staff Sales, 5-Staff Logistic');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
            $table->dateTime('deleted_at')->nullable();
            $table->string('status', 11)->comment('0-Inactive, 1-Active, 2-Banned/Blocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_hq');
    }
};
