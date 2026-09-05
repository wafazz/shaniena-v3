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
        Schema::create('jt_setting', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('production_sandbox')->default(0)->comment('0-Sandbox, 1-Production');
            $table->string('url_sandbox');
            $table->string('username_sanbox', 50);
            $table->string('password_sandbox', 50);
            $table->string('cuscode_sandbox', 50);
            $table->string('key_sandbox', 100);
            $table->string('url_production');
            $table->string('username_production', 50);
            $table->string('password_production');
            $table->string('cuscode_production', 50);
            $table->string('key_production', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jt_setting');
    }
};
