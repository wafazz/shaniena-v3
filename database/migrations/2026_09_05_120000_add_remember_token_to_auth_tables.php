<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The source login form had a "Remember me" checkbox, but neither auth table
 * carried a remember_token column — the checkbox did nothing. Laravel's
 * Auth::attempt($credentials, $remember) writes this column, so it has to
 * exist before remember-me can work on either guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['member_hq', 'members'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->rememberToken();
            });
        }
    }

    public function down(): void
    {
        foreach (['member_hq', 'members'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('remember_token');
            });
        }
    }
};
