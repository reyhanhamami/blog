<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('role', 20)->default('reader')->index());

        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('photo')->nullable();
            $table->string('job_title')->nullable();
            $table->text('short_bio')->nullable();
            $table->longText('full_bio')->nullable();
            $table->string('expertise')->nullable();
            $table->string('website')->nullable();
            $table->string('github_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->timestamps();
        });

        foreach (['categories', 'tags', 'topics'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                if ($name === 'categories') {
                    $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
                    $table->boolean('is_active')->default(true);
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->softDeletes();
                }
                $table->timestamps();
            });
        }

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->longText('content_plain')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('content_type', 30)->default('article');
            $table->string('status', 30)->default('draft')->index();
            $table->string('difficulty', 20)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('allow_comments')->default(false);
            $table->boolean('noindex')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('review_due_at')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        foreach (['tag', 'topic'] as $name) {
            Schema::create('post_'.$name, function (Blueprint $table) use ($name) {
                $table->foreignId('post_id')->constrained()->cascadeOnDelete();
                $table->foreignId($name.'_id')->constrained($name.'s')->cascadeOnDelete();
                $table->primary(['post_id', $name.'_id']);
            });
        }

        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('summary')->nullable();
            $table->timestamp('created_at');
            $table->unique(['post_id', 'version']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('youtube_id', 20);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->text('label');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('correct_count');
            $table->unsignedInteger('total_count');
            $table->unsignedTinyInteger('score');
            $table->timestamp('created_at');
        });

        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('learning_path_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['learning_path_id', 'post_id']);
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('course_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unique(['user_id', 'course_lesson_id']);
        });
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'post_id']);
        });
        Schema::create('article_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->string('visitor_hash', 64);
            $table->timestamps();
            $table->unique(['post_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        foreach (['article_feedback', 'bookmarks', 'course_progress', 'course_lessons', 'course_modules', 'courses', 'learning_path_items', 'learning_paths', 'quiz_attempts', 'quiz_options', 'quiz_questions', 'quizzes', 'videos', 'activities', 'settings', 'redirects', 'post_revisions', 'post_topic', 'post_tag', 'posts', 'topics', 'tags', 'categories', 'authors'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
