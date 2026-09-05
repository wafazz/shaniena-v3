<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ported from the source project's pending sql/blog_views.sql.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_views', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('blog_id')->index();
            $table->string('visitor_ip', 45);
            $table->dateTime('created_at');

            $table->unique(['blog_id', 'visitor_ip'], 'unique_view');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_views');
    }
};
