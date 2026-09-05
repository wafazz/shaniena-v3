<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ported from the source project's pending migration_bayarcash.sql, with
// sql/bayarcash_alter_columns.sql already folded in (all six credential
// columns were widened from VARCHAR(255) to TEXT).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bayarcash_api', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('type', ['sandbox', 'production'])->default('sandbox');
            $table->text('sandbox_api_token')->nullable();
            $table->text('sandbox_secret_key')->nullable();
            $table->text('sandbox_portal_key')->nullable();
            $table->text('api_token')->nullable();
            $table->text('secret_key')->nullable();
            $table->text('portal_key')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bayarcash_api');
    }
};
