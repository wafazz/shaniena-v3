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
        Schema::create('cs_ticket_attachments', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('ticket_id')->nullable()->index('ticket_id');
            $table->string('filename')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type', 50)->nullable();
            $table->enum('uploaded_by', ['customer', 'staff'])->nullable()->default('customer');
            $table->dateTime('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cs_ticket_attachments');
    }
};
