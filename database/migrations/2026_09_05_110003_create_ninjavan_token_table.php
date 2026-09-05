<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ported from the source project's pending sql/ninjavan_token.sql.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ninjavan_token', function (Blueprint $table) {
            $table->increments('id');
            $table->string('mode', 20)->index('idx_mode')->comment('production or sandbox');
            $table->text('access_token');
            $table->string('token_type', 50)->default('Bearer');
            $table->integer('expires_in')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('expired_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ninjavan_token');
    }
};
