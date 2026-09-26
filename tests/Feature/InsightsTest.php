<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function adminUser(string $role = 'superadmin'): User
{
    return User::factory()->create(['role' => $role]);
}

function publishedPost(array $attributes = []): Post
{
    return Post::create(array_merge([
        'title' => 'Panduan Laravel', 'slug' => 'panduan-laravel',
        'excerpt' => 'Panduan singkat Laravel', 'content' => '<p>Selamat belajar.</p>',
        'content_plain' => 'Selamat belajar.', 'status' => 'published',
        'published_at' => now()->subMinute(), 'content_type' => 'tutorial',
    ], $attributes));
}

test('seeded administrator can log in and password is hashed', function () {
    $this->seed();
    $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
    expect($admin->role)->toBe('superadmin');
    expect(Hash::check('asddsa123', $admin->password))->toBeTrue();
    $this->post('/admin/login', ['email' => 'admin@gmail.com', 'password' => 'asddsa123'])->assertRedirect('/admin');
});

test('invalid password and reader CMS access are rejected', function () {
    $reader = adminUser('reader');
    $this->post('/admin/login', ['email' => $reader->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->actingAs($reader)->get('/admin')->assertForbidden();
});

test('superadmin can create and update a post with sanitized content and revision', function () {
    $admin = adminUser();
    $this->actingAs($admin)->post('/admin/posts', [
        'title' => 'Artikel Aman', 'slug' => '', 'content' => '<p>Halo</p><script>alert(1)</script>',
        'status' => 'published', 'content_type' => 'article',
    ])->assertRedirect();
    $post = Post::firstOrFail();
    expect($post->slug)->toBe('artikel-aman');
    expect($post->content)->not->toContain('<script>');
    expect(DB::table('post_revisions')->where('post_id', $post->id)->count())->toBe(1);
    $this->actingAs($admin)->patch('/admin/posts/'.$post->id, [
        'title' => 'Artikel Baru', 'slug' => 'artikel-baru', 'content' => '<p>Baru</p>',
        'status' => 'published', 'content_type' => 'article',
    ])->assertRedirect();
    expect(DB::table('post_revisions')->where('post_id', $post->id)->count())->toBe(2);
    $this->get('/artikel-aman')->assertRedirect('/artikel-baru')->assertStatus(301);
});

test('author cannot edit another authors post', function () {
    $author = adminUser('author');
    $post = publishedPost(['created_by' => adminUser()->id]);
    $this->actingAs($author)->get('/admin/posts/'.$post->id.'/edit')->assertForbidden();
});

test('draft is private and omitted from sitemap while published post has SEO metadata', function () {
    $author = Author::create(['name' => 'Dina', 'slug' => 'dina']);
    publishedPost(['author_id' => $author->id]);
    publishedPost(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'published_at' => null]);
    publishedPost(['title' => 'Noindex', 'slug' => 'noindex', 'noindex' => true]);
    $this->get('/panduan-laravel')->assertOk()->assertSee('rel="canonical"', false)->assertSee('application/ld+json', false)->assertSee('Dina');
    $this->get('/draft')->assertNotFound();
    $this->get('/sitemap.xml')->assertSee('/panduan-laravel')->assertDontSee('/draft')->assertDontSee('/noindex');
});

test('catalog category can be created and listed', function () {
    $admin = adminUser();
    $this->actingAs($admin)->post('/admin/categories', ['name' => 'Laravel', 'slug' => '', 'description' => 'Artikel Laravel', 'is_active' => '1'])->assertRedirect('/admin/categories');
    expect(Category::firstOrFail()->slug)->toBe('laravel');
    $this->actingAs($admin)->get('/admin/categories')->assertOk()->assertSee('Laravel');
});

test('public article emits valid structured data and rendered head metadata', function () {
    publishedPost();
    $html = $this->get('/panduan-laravel')->assertOk()->getContent();
    expect($html)->toContain('<title>Panduan Laravel | Besofton Insights</title>');
    expect($html)->not->toContain("@yield('seo_title'");
    preg_match_all('/<script type="application\\/ld\\+json">(.*?)<\\/script>/s', $html, $matches);
    expect(count($matches[1]))->toBeGreaterThanOrEqual(3);
    $schemas = array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
    expect(collect($schemas)->pluck('@type'))->toContain('Article', 'BreadcrumbList');
});
