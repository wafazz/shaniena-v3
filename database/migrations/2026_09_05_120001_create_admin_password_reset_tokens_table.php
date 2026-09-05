<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admins reset passwords against their own token table.
 *
 * `password_reset_tokens` is keyed by email alone, so sharing it between the
 * `web` (members) and `admin` (member_hq) guards would let one staff member's
 * reset request overwrite a customer's — the same address exists in both
 * tables for anyone who shops on the store they also administer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
    }
};
