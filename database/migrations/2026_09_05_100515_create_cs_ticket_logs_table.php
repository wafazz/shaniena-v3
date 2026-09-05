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
        Schema::create('cs_ticket_logs', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('ticket_id')->nullable()->index('ticket_id');
            $table->string('action')->nullable();
            $table->integer('action_by')->nullable();
            $table->string('previous_value')->nullable();
            $table->string('new_value')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cs_ticket_logs');
    }
};
