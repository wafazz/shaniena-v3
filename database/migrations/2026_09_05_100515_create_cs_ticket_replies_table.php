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
        Schema::create('cs_ticket_replies', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('ticket_id')->nullable()->index('ticket_id');
            $table->enum('user_type', ['customer', 'staff'])->nullable();
            $table->integer('user_id')->nullable();
            $table->text('message')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cs_ticket_replies');
    }
};
