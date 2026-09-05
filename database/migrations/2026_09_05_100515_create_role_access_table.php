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
        Schema::create('role_access', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('page_url');
            $table->string('name');
            $table->string('allowed_user', 1500);
            $table->integer('sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_access');
    }
};
