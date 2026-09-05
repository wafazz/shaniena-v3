<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ported from the source project's pending migration_bayarcash.sql.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bayarcash_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->index('idx_order_id');
            $table->string('order_number', 100)->index('idx_order_number');
            $table->string('payment_intent_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->tinyInteger('payment_channel')->nullable()->comment('1=FPX, 2=DuitNow QR, 3=DuitNow Online, 4=Credit Card, 5=SPayLater');
            $table->decimal('amount', 10, 2)->default(0);
            $table->tinyInteger('status')->default(0)->index('idx_status')->comment('0=New, 1=Pending, 2=Failed, 3=Successful, -1=Cancelled');
            $table->text('callback_payload')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bayarcash_transactions');
    }
};
