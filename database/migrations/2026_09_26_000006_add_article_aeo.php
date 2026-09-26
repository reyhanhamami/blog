<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('direct_answer')->nullable();
            $table->text('key_takeaways')->nullable();
            $table->text('summary')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
        });
        Schema::create('post_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('url', 2048);
            $table->string('publisher')->nullable();
            $table->date('published_at')->nullable();
            $table->date('accessed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_sources');
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn(['direct_answer', 'key_takeaways', 'summary', 'og_title', 'og_description', 'og_image']));
    }
};
