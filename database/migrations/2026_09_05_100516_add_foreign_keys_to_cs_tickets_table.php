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
        Schema::table('cs_tickets', function (Blueprint $table) {
            $table->foreign(['customer_id'], 'cs_tickets_ibfk_1')->references(['id'])->on('cs_customers')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cs_tickets', function (Blueprint $table) {
            $table->dropForeign('cs_tickets_ibfk_1');
        });
    }
};
