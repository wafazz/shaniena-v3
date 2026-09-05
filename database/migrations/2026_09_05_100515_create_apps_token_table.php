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
        Schema::create('apps_token', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->bigInteger('user_id');
            $table->string('token');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('expired_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apps_token');
    }
};
