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
        Schema::create('cs_tickets', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('ticket_no', 30)->nullable()->unique('ticket_no');
            $table->integer('customer_id')->nullable()->index('customer_id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['new', 'in_progress', 'waiting_customer', 'resolved', 'closed'])->nullable()->default('new');
            $table->string('order_id')->nullable();
            $table->integer('assigned_to')->nullable()->index('assigned_to');
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->string('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cs_tickets');
    }
};
