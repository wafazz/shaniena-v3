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
        Schema::table('cs_ticket_logs', function (Blueprint $table) {
            $table->foreign(['ticket_id'], 'cs_ticket_logs_ibfk_1')->references(['id'])->on('cs_tickets')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cs_ticket_logs', function (Blueprint $table) {
            $table->dropForeign('cs_ticket_logs_ibfk_1');
        });
    }
};
