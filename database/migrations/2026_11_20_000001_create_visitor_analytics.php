<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_views')->default(0);
            $table->unsignedBigInteger('tracked_views')->default(0);
            $table->dateTime('last_viewed_at')->nullable();
        });
        // Preserve the existing counter verbatim. Never invent its daily distribution.
        DB::table('posts')->update(['legacy_views' => DB::raw('views')]);
        Schema::create('analytics_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->dateTime('started_at');
            $table->dateTime('last_pruned_at')->nullable();
        });
        DB::table('analytics_state')->insert(['id' => 1, 'started_at' => now('UTC')->format('Y-m-d H:i:s')]);
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->date('date');
            $table->string('page_key', 64);
            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);
            $table->dateTime('last_seen_at')->nullable();
            $table->primary(['date', 'page_key']);
            $table->index(['post_id', 'date']);
        });
        Schema::create('analytics_page_visitors', function (Blueprint $table) {
            $table->date('date');
            $table->string('page_key', 64);
            $table->char('visitor_hash', 64);
            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->dateTime('last_seen_at')->nullable();
            $table->primary(['date', 'page_key', 'visitor_hash']);
            $table->index(['post_id', 'date', 'visitor_hash'], 'analytics_article_unique');
        });
        Schema::create('analytics_site_visitors', function (Blueprint $table) {
            $table->date('date');
            $table->char('visitor_hash', 64);
            $table->primary(['date', 'visitor_hash']);
        });
        Schema::create('analytics_receipts', function (Blueprint $table) {
            $table->char('id', 64)->primary();
            $table->dateTime('expires_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['analytics_receipts', 'analytics_site_visitors', 'analytics_page_visitors', 'analytics_daily', 'analytics_state'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn(['legacy_views', 'tracked_views', 'last_viewed_at']));
        // posts.views retains every recorded view even on rollback.
    }
};
