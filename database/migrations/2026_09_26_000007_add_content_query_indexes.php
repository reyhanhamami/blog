<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->index('created_at', 'posts_created_at_idx');
            $table->index('views', 'posts_views_idx');
            $table->index(['category_id', 'status', 'published_at'], 'posts_category_public_idx');
            $table->index(['author_id', 'status', 'published_at'], 'posts_author_public_idx');
        });
        Schema::table('videos', fn (Blueprint $table) => $table->index(['status', 'published_at'], 'videos_public_idx'));
    }

    public function down(): void
    {
        Schema::table('videos', fn (Blueprint $table) => $table->dropIndex('videos_public_idx'));
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_author_public_idx');
            $table->dropIndex('posts_category_public_idx');
            $table->dropIndex('posts_views_idx');
            $table->dropIndex('posts_created_at_idx');
        });
    }
};
