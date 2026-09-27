<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->string('type', 30)->default('single_choice');
            $table->text('explanation')->nullable();
        });
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->foreignId('course_lesson_id')->nullable()->constrained()->nullOnDelete());
        Schema::table('posts', fn (Blueprint $table) => $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete());
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->string('type', 20)->default('custom');
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('video_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('requires_pass')->default(false);
        });
        Schema::table('learning_path_items', function (Blueprint $table) {
            $table->foreignId('post_id')->nullable()->change();
            $table->string('type', 20)->default('article');
            $table->foreignId('video_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['learning_path_id', 'video_id']);
            $table->unique(['learning_path_id', 'quiz_id']);
        });
        Schema::create('course_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_lesson_id')->nullable()->constrained('course_lessons')->nullOnDelete();
            $table->timestamp('last_opened_at');
            $table->unique(['user_id', 'course_id']);
            $table->index(['user_id', 'last_opened_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('learning_path_items')->whereNull('post_id')->exists()) {
            throw new RuntimeException('Move quiz and video learning path items before rolling back.');
        }

        Schema::dropIfExists('course_activity');
        Schema::table('learning_path_items', function (Blueprint $table) {
            $table->dropUnique(['learning_path_id', 'video_id']);
            $table->dropUnique(['learning_path_id', 'quiz_id']);
            $table->dropConstrainedForeignId('video_id');
            $table->dropConstrainedForeignId('quiz_id');
            $table->dropColumn('type');
            $table->foreignId('post_id')->nullable(false)->change();
        });
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('post_id');
            $table->dropConstrainedForeignId('video_id');
            $table->dropConstrainedForeignId('quiz_id');
            $table->dropColumn(['type', 'requires_pass']);
        });
        Schema::table('posts', fn (Blueprint $table) => $table->dropConstrainedForeignId('quiz_id'));
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropConstrainedForeignId('course_lesson_id'));
        Schema::table('quiz_questions', fn (Blueprint $table) => $table->dropColumn(['type', 'explanation']));
    }
};
