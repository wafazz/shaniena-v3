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
        Schema::create('online_visitor_return', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('ip_address', 50);
            $table->dateTime('created_at')->useCurrent()->index('idx_created_at');
            $table->dateTime('updated_at')->useCurrent();
            $table->dateTime('session_end_at')->nullable()->index('idx_session_end_at');

            $table->index(['ip_address', 'created_at'], 'idx_ip_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_visitor_return');
    }
};
