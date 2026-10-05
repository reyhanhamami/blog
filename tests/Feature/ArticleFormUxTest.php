<?php

use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

test('article create and edit use shared taxonomy and image picker controls', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $post = Post::create(['title' => 'Artikel Lama', 'slug' => 'artikel-lama', 'status' => 'draft', 'content_type' => 'article', 'featured_image' => 'https://example.test/cover.jpg', 'og_image' => 'https://example.test/og.jpg']);

    $this->actingAs($admin)->get(route('admin.posts.create'))->assertOk()
        ->assertSee('data-taxonomy-select="categories"', false)
        ->assertSee('data-taxonomy-open="tags"', false)
        ->assertSee('data-article-media-picker', false)
        ->assertSee('data-article-image-field="featured"', false)
        ->assertSee('data-article-image-field="og"', false)
        ->assertSee('data-media-gallery', false)
        ->assertDontSee('data-editor-media-url', false);
    $this->get(route('admin.posts.edit', $post))->assertOk()
        ->assertSee('https://example.test/cover.jpg')
        ->assertSee('https://example.test/og.jpg');
});

test('quick taxonomy creation reuses CRUD routes and returns selectable records', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    foreach (['categories' => Category::class, 'tags' => Tag::class, 'topics' => Topic::class] as $module => $model) {
        $name = ucfirst($module).' Baru';
        $response = $this->postJson(route('admin.'.$module.'.store'), ['name' => $name, 'slug' => '']);
        $response->assertCreated()->assertJsonPath('name', $name);
        expect($model::findOrFail($response->json('id'))->slug)->toBe(str($name)->slug()->toString());
    }
    expect(Category::firstOrFail()->is_active)->toBeTrue();
});

test('quick create detects case insensitive duplicates without adding another record', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $category = Category::create(['name' => 'Artificial Intelligence', 'slug' => 'artificial-intelligence']);

    $this->postJson(route('admin.categories.store'), ['name' => 'artificial intelligence', 'slug' => 'another-slug'])
        ->assertStatus(409)->assertJsonPath('existing.id', $category->id);
    expect(Category::count())->toBe(1);
    $this->postJson(route('admin.categories.store'), ['name' => 'Baru', 'slug' => $category->slug])
        ->assertUnprocessable()->assertJsonValidationErrors('slug');
});

test('article author can select existing taxonomy but cannot quick create it', function () {
    $author = User::factory()->create(['role' => 'author']);
    Category::create(['name' => 'Existing', 'slug' => 'existing']);
    $this->actingAs($author)->get(route('admin.posts.create'))->assertOk()
        ->assertSee('Existing')->assertDontSee('data-taxonomy-open="categories"', false)
        ->assertDontSee('data-taxonomy-open="tags"', false)
        ->assertDontSee('data-taxonomy-open="topics"', false);
    $this->postJson(route('admin.categories.store'), ['name' => 'Forbidden'])->assertForbidden();
    $this->postJson(route('admin.tags.store'), ['name' => 'Forbidden'])->assertForbidden();
    $this->postJson(route('admin.topics.store'), ['name' => 'Forbidden'])->assertForbidden();
});

test('lazy media library search returns image pages and respects access', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    foreach (range(1, 25) as $index) {
        Media::create(['path' => 'uploads/demo-'.$index.'.png', 'mime_type' => 'image/png', 'size' => 100, 'alt_text' => 'Demo '.$index, 'uploaded_by' => $admin->id]);
    }
    Media::create(['path' => 'uploads/file.pdf', 'mime_type' => 'application/pdf', 'size' => 100, 'alt_text' => 'Document', 'uploaded_by' => $admin->id]);

    $this->actingAs($admin)->getJson(route('admin.media.index'))->assertOk()
        ->assertJsonCount(24, 'items')->assertJsonPath('last_page', 2);
    $this->getJson(route('admin.media.index', ['page' => 2]))->assertOk()->assertJsonCount(1, 'items');
    $this->getJson(route('admin.media.index', ['q' => 'Demo 25']))->assertOk()->assertJsonCount(1, 'items');
    $this->actingAs(User::factory()->create(['role' => 'reader']))->getJson(route('admin.media.index'))->assertForbidden();
});

test('uploaded media URL and taxonomy selections persist in article and public OG fallback', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $category = Category::create(['name' => 'AI', 'slug' => 'ai']);
    $tag = Tag::create(['name' => 'Prompt', 'slug' => 'prompt']);
    $topic = Topic::create(['name' => 'Developer', 'slug' => 'developer']);
    $this->actingAs($admin);
    $upload = $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('cover.png', 1200, 630), 'alt_text' => 'Diagram AI']);
    $upload->assertCreated();
    $url = $upload->json('url');
    $path = Media::firstOrFail()->path;
    try {
        $this->post(route('admin.posts.store'), [
            'title' => 'Artikel dengan Gambar', 'slug' => '', 'content_type' => 'article', 'status' => 'published',
            'category_id' => $category->id, 'tags' => [$tag->id], 'topics' => [$topic->id],
            'featured_image' => $url, 'featured_image_alt' => 'Diagram AI', 'og_image' => '',
        ])->assertRedirect();
        $post = Post::where('slug', 'artikel-dengan-gambar')->firstOrFail();
        expect($post->category_id)->toBe($category->id);
        expect($post->tags->modelKeys())->toBe([$tag->id]);
        expect($post->topics->modelKeys())->toBe([$topic->id]);
        expect($post->featured_image)->toBe($url);
        expect($post->og_image)->toBeNull();
        $this->get('/artikel-dengan-gambar')->assertOk()->assertSee('property="og:image" content="'.$url.'"', false);

        $this->patch(route('admin.posts.update', $post), [
            'title' => $post->title, 'slug' => $post->slug, 'content_type' => 'article', 'status' => 'published',
            'category_id' => $category->id, 'tags' => [$tag->id], 'topics' => [$topic->id],
            'featured_image' => $url, 'featured_image_alt' => 'Diagram AI', 'og_image' => 'https://example.test/other.jpg',
        ])->assertRedirect();
        expect($post->fresh()->og_image)->toBe('https://example.test/other.jpg');
        $this->get('/artikel-dengan-gambar')->assertOk()->assertSee('property="og:image" content="https://example.test/other.jpg"', false);
    } finally {
        File::delete(public_path($path));
    }
});

test('article image fields reject unsafe schemes and unknown taxonomy ids', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $payload = ['title' => 'Gambar Tidak Aman', 'content_type' => 'article', 'status' => 'draft'];
    $this->post(route('admin.posts.store'), $payload + ['featured_image' => 'javascript:alert(1)'])->assertSessionHasErrors('featured_image');
    $this->post(route('admin.posts.store'), $payload + ['og_image' => 'data:text/html,evil'])->assertSessionHasErrors('og_image');
    $this->post(route('admin.posts.store'), $payload + ['category_id' => 999999, 'tags' => [999999], 'topics' => [999999]])
        ->assertSessionHasErrors(['category_id', 'tags.0', 'topics.0']);
});

test('edit form can replace relations and clear optional featured and OG images', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $oldTag = Tag::create(['name' => 'Old', 'slug' => 'old']);
    $newTag = Tag::create(['name' => 'New', 'slug' => 'new']);
    $post = Post::create([
        'title' => 'Edit Gambar', 'slug' => 'edit-gambar', 'status' => 'draft', 'content_type' => 'article',
        'featured_image' => 'https://example.test/old.jpg', 'og_image' => 'https://example.test/old-og.jpg',
    ]);
    $post->tags()->attach($oldTag);

    $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()
        ->assertSee('value="'.$oldTag->id.'" selected', false)
        ->assertSee('https://example.test/old.jpg');
    $this->patch(route('admin.posts.update', $post), [
        'title' => $post->title, 'slug' => $post->slug, 'content_type' => 'article', 'status' => 'draft',
        'tags' => [$newTag->id], 'featured_image' => '', 'featured_image_alt' => '', 'og_image' => '',
    ])->assertRedirect();
    expect($post->fresh()->tags->modelKeys())->toBe([$newTag->id]);
    expect($post->fresh()->featured_image)->toBeNull();
    expect($post->fresh()->og_image)->toBeNull();
});
