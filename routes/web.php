<?php

use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\CourseContentController;
use App\Http\Controllers\Admin\LearningPathItemController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PostSourceController;
use App\Http\Controllers\Admin\QuestionModerationController;
use App\Http\Controllers\Admin\QuizQuestionController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ReaderAuthController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReaderController;
use App\Http\Middleware\EnsureCmsAccess;
use App\Models\Course;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [ReaderAuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [ReaderAuthController::class, 'login'])->middleware('throttle:5,1')->name('reader.login');
    Route::get('/register', [ReaderAuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [ReaderAuthController::class, 'register'])->middleware('throttle:5,1')->name('reader.register');
    Route::get('/forgot-password', [ReaderAuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [ReaderAuthController::class, 'forgot'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ReaderAuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [ReaderAuthController::class, 'reset'])->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [ReaderAuthController::class, 'logout'])->name('reader.logout');
    Route::get('/akun', [ReaderController::class, 'account'])->name('reader.account');
    Route::post('/artikel/{post}/bookmark', [ReaderController::class, 'bookmark'])->name('reader.bookmark');
    Route::post('/kelas/{course:slug}/pelajaran/{lesson}/selesai', [ReaderController::class, 'complete'])->name('reader.lesson.complete');
});
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('admin.login.store');
});
Route::post('/admin/logout', [LoginController::class, 'destroy'])->middleware(['auth', EnsureCmsAccess::class])->name('admin.logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureCmsAccess::class])->group(function () {
    Route::get('/', function () {
        $metrics = [
            'Artikel' => Post::count(), 'Terbit' => Post::published()->count(),
            'Draft' => Post::where('status', 'draft')->count(), 'Terjadwal' => Post::where('status', 'scheduled')->count(),
            'Penulis' => User::whereIn('role', ['author', 'editor'])->count(), 'Video' => Video::count(),
            'Kuis' => Quiz::count(), 'Kelas' => Course::count(), 'Tayangan' => Post::sum('views'),
            'Percobaan kuis' => DB::table('quiz_attempts')->count(), 'Masukan pembaca' => DB::table('article_feedback')->count(),
        ];

        return view('admin.dashboard', ['metrics' => $metrics, 'latest' => Post::latest()->take(8)->get(), 'scheduled' => Post::where('status', 'scheduled')->orderBy('scheduled_at')->take(8)->get(), 'activity' => DB::table('activities')->latest('created_at')->take(8)->get()]);
    })->name('dashboard');

    Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
    Route::get('/menus/{menu}/items/create', [MenuController::class, 'create'])->name('menus.items.create');
    Route::post('/menus/{menu}/items', [MenuController::class, 'store'])->name('menus.items.store');
    Route::get('/menus/{menu}/items/{item}/edit', [MenuController::class, 'edit'])->name('menus.items.edit');
    Route::patch('/menus/{menu}/items/{item}', [MenuController::class, 'update'])->name('menus.items.update');
    Route::delete('/menus/{menu}/items/{item}', [MenuController::class, 'destroy'])->name('menus.items.destroy');
    Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('/pages/create', [PageController::class, 'create'])->name('pages.create');
    Route::post('/pages', [PageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
    Route::patch('/pages/{page}', [PageController::class, 'update'])->name('pages.update');
    Route::delete('/pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    Route::get('/questions', [QuestionModerationController::class, 'index'])->name('questions.index');
    Route::get('/questions/{question}', [QuestionModerationController::class, 'show'])->name('questions.show');
    Route::patch('/questions/{question}', [QuestionModerationController::class, 'update'])->name('questions.update');
    Route::post('/questions/{question}/answers', [QuestionModerationController::class, 'answer'])->name('questions.answer');
    Route::delete('/questions/{question}', [QuestionModerationController::class, 'destroy'])->name('questions.destroy');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::patch('/media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/redirects', [RedirectController::class, 'index'])->name('redirects.index');
    Route::post('/redirects', [RedirectController::class, 'store'])->name('redirects.store');
    Route::delete('/redirects/{redirect}', [RedirectController::class, 'destroy'])->name('redirects.destroy');
    Route::get('/analytics', function () {
        Gate::authorize('manage-content');

        return view('admin.analytics', ['views' => Post::sum('views'), 'attempts' => DB::table('quiz_attempts')->count(), 'helpful' => DB::table('article_feedback')->where('helpful', true)->count(), 'top' => Post::orderByDesc('views')->take(10)->get()]);
    })->name('analytics');
    Route::get('/editorial-calendar', function () {
        Gate::authorize('manage-content');
        $events = Post::whereIn('status', ['scheduled', 'published'])->get()->map(fn ($post) => ['title' => $post->title, 'start' => ($post->scheduled_at ?: $post->published_at)?->toDateString(), 'url' => route('admin.posts.edit', $post), 'color' => $post->status === 'scheduled' ? '#d97706' : '#4338ca'])->filter(fn ($event) => $event['start'])->values();

        return view('admin.calendar', compact('events'));
    })->name('editorial-calendar');
    Route::get('/posts/{post}/sources', [PostSourceController::class, 'index'])->name('posts.sources.index');
    Route::get('/posts/{post}/sources/create', [PostSourceController::class, 'create'])->name('posts.sources.create');
    Route::post('/posts/{post}/sources', [PostSourceController::class, 'store'])->name('posts.sources.store');
    Route::get('/posts/{post}/sources/{source}/edit', [PostSourceController::class, 'edit'])->name('posts.sources.edit');
    Route::patch('/posts/{post}/sources/{source}', [PostSourceController::class, 'update'])->name('posts.sources.update');
    Route::delete('/posts/{post}/sources/{source}', [PostSourceController::class, 'destroy'])->name('posts.sources.destroy');
    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts/bulk', [PostController::class, 'bulk'])->name('posts.bulk');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/restore', [PostController::class, 'restore'])->name('posts.restore');
    Route::delete('/posts/{post}/force', [PostController::class, 'forceDelete'])->name('posts.force');
    Route::get('/posts/{post}/preview', [PostController::class, 'preview'])->name('posts.preview');
    Route::post('/posts/{post}/duplicate', [PostController::class, 'duplicate'])->name('posts.duplicate');
    Route::get('/posts/{post}/revisions', [PostController::class, 'revisions'])->name('posts.revisions');
    Route::post('/posts/{post}/revisions/{version}/restore', [PostController::class, 'restoreRevision'])->name('posts.revisions.restore');

    Route::get('/quizzes/{quiz}/questions', [QuizQuestionController::class, 'index'])->name('quizzes.questions.index');
    Route::get('/quizzes/{quiz}/questions/create', [QuizQuestionController::class, 'create'])->name('quizzes.questions.create');
    Route::post('/quizzes/{quiz}/questions', [QuizQuestionController::class, 'store'])->name('quizzes.questions.store');
    Route::get('/quizzes/{quiz}/questions/{question}/edit', [QuizQuestionController::class, 'edit'])->name('quizzes.questions.edit');
    Route::patch('/quizzes/{quiz}/questions/{question}', [QuizQuestionController::class, 'update'])->name('quizzes.questions.update');
    Route::delete('/quizzes/{quiz}/questions/{question}', [QuizQuestionController::class, 'destroy'])->name('quizzes.questions.destroy');
    Route::get('/learning-paths/{path}/items', [LearningPathItemController::class, 'index'])->name('learning-paths.items.index');
    Route::post('/learning-paths/{path}/items', [LearningPathItemController::class, 'store'])->name('learning-paths.items.store');
    Route::patch('/learning-paths/{path}/items/{item}', [LearningPathItemController::class, 'update'])->name('learning-paths.items.update');
    Route::delete('/learning-paths/{path}/items/{item}', [LearningPathItemController::class, 'destroy'])->name('learning-paths.items.destroy');
    Route::get('/courses/{course}/content', [CourseContentController::class, 'index'])->name('courses.content.index');
    Route::post('/courses/{course}/modules', [CourseContentController::class, 'storeModule'])->name('courses.modules.store');
    Route::delete('/courses/{course}/modules/{module}', [CourseContentController::class, 'deleteModule'])->name('courses.modules.destroy');
    Route::get('/courses/{course}/modules/{module}/lessons/create', [CourseContentController::class, 'createLesson'])->name('courses.lessons.create');
    Route::post('/courses/{course}/modules/{module}/lessons', [CourseContentController::class, 'storeLesson'])->name('courses.lessons.store');
    Route::get('/courses/{course}/modules/{module}/lessons/{lesson}/edit', [CourseContentController::class, 'editLesson'])->name('courses.lessons.edit');
    Route::patch('/courses/{course}/modules/{module}/lessons/{lesson}', [CourseContentController::class, 'updateLesson'])->name('courses.lessons.update');
    Route::delete('/courses/{course}/modules/{module}/lessons/{lesson}', [CourseContentController::class, 'deleteLesson'])->name('courses.lessons.destroy');
    foreach (array_keys(CatalogController::MODULES) as $module) {
        Route::get('/'.$module, [CatalogController::class, 'index'])->defaults('module', $module)->name($module.'.index');
        Route::get('/'.$module.'/create', [CatalogController::class, 'create'])->defaults('module', $module)->name($module.'.create');
        Route::post('/'.$module, [CatalogController::class, 'store'])->defaults('module', $module)->name($module.'.store');
        Route::get('/'.$module.'/{item}/edit', [CatalogController::class, 'edit'])->defaults('module', $module)->name($module.'.edit');
        Route::patch('/'.$module.'/{item}', [CatalogController::class, 'update'])->defaults('module', $module)->name($module.'.update');
        Route::delete('/'.$module.'/{item}', [CatalogController::class, 'destroy'])->defaults('module', $module)->name($module.'.destroy');
        if (in_array($module, ['categories', 'videos', 'quizzes', 'learning-paths', 'courses'])) {
            Route::post('/'.$module.'/{item}/restore', [CatalogController::class, 'restore'])->defaults('module', $module)->name($module.'.restore');
        }
    }
});

Route::get('/', [PublicContentController::class, 'home'])->name('home');
Route::get('/cari', [PublicContentController::class, 'search'])->name('search');
Route::get('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribeForm'])->name('newsletter.unsubscribe.form');
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:5,1')->name('newsletter.unsubscribe');
Route::get('/newsletter/unsubscribe/confirm/{subscriber}', [NewsletterController::class, 'confirmUnsubscribe'])->middleware('signed')->name('newsletter.unsubscribe.confirm');
Route::get('/kategori', [PublicContentController::class, 'categories'])->name('categories.index');
Route::get('/kategori/{category:slug}', [PublicContentController::class, 'category'])->name('category.show');
Route::get('/tag/{tag:slug}', [PublicContentController::class, 'tag'])->name('tag.show');
Route::get('/topik/{topic:slug}', [PublicContentController::class, 'topic'])->name('topic.show');
Route::get('/author/{author:slug}', [PublicContentController::class, 'author'])->name('author.show');
Route::get('/video/{video:slug}', [PublicContentController::class, 'video'])->name('video.show');
Route::get('/kuis/{quiz:slug}', [PublicContentController::class, 'quiz'])->name('quiz.show');
Route::post('/kuis/{quiz:slug}', [PublicContentController::class, 'submitQuiz'])->middleware('throttle:10,1')->name('quiz.submit');
Route::get('/belajar/{path:slug}', [PublicContentController::class, 'path'])->name('path.show');
Route::get('/kelas/{course:slug}', [PublicContentController::class, 'course'])->name('course.show');
Route::get('/kelas/{course:slug}/pelajaran/{lesson}', [ReaderController::class, 'lesson'])->name('course.lesson');
Route::get('/sitemap.xml', [PublicContentController::class, 'sitemap'])->name('sitemap');
Route::get('/feed.xml', [PublicContentController::class, 'feed'])->name('feed');
Route::get('/robots.txt', [PublicContentController::class, 'robots'])->name('robots');
Route::post('/artikel/{post}/pertanyaan', [QuestionController::class, 'store'])->middleware('throttle:5,1')->name('post.question');
Route::post('/artikel/{post}/feedback', [PublicContentController::class, 'feedback'])->middleware('throttle:5,1')->name('post.feedback');
foreach (['about', 'privacy-policy', 'editorial-policy', 'contact', 'terms'] as $pageSlug) {
    Route::get('/'.$pageSlug, [PublicContentController::class, 'page'])->defaults('pageSlug', $pageSlug)->name('page.'.$pageSlug);
}Route::get('/{slug}', [PublicContentController::class, 'post'])->where('slug', '[a-z0-9][a-z0-9-]*')->name('post.show');
