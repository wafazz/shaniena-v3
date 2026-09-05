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
        Schema::create('news_blog', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('post_by');
            $table->string('update_by', 1500);
            $table->string('title', 1500);
            $table->text('contents');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
            $table->dateTime('deleted_at')->nullable();
            $table->string('reader', 1500);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_blog');
    }
};
