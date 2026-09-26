<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

function opsAdmin(): User
{
    return User::factory()->create(['role' => 'superadmin']);
}

test('editor can publish but author cannot', function () {
    $author = User::factory()->create(['role' => 'author']);
    $editor = User::factory()->create(['role' => 'editor']);
    $data = ['title' => 'Status flow', 'slug' => 'status-flow', 'content_type' => 'article', 'status' => 'published'];
    $this->actingAs($author)->post('/admin/posts', $data)->assertSessionHasErrors('status');
    $this->actingAs($editor)->post('/admin/posts', $data)->assertRedirect();
    $this->assertDatabaseHas('posts', ['slug' => 'status-flow', 'status' => 'published']);
});

test('post slug must be unique and trashed post can be restored', function () {
    $admin = opsAdmin();
    $data = ['title' => 'Judul Sama', 'slug' => '', 'content_type' => 'article', 'status' => 'draft'];
    $this->actingAs($admin)->post('/admin/posts', $data)->assertRedirect();
    $this->actingAs($admin)->post('/admin/posts', $data)->assertSessionHasErrors('slug');
    $post = Post::firstOrFail();
    $this->actingAs($admin)->delete(route('admin.posts.destroy', $post))->assertRedirect();
    expect(Post::find($post->id))->toBeNull();
    $this->actingAs($admin)->post(route('admin.posts.restore', $post->id))->assertRedirect();
    expect(Post::find($post->id))->not->toBeNull();
});

test('search finds published content by title and tag', function () {
    $post = Post::create(['title' => 'Belajar PHP', 'slug' => 'belajar-php', 'status' => 'published', 'published_at' => now()->subMinute()]);
    $tag = Tag::create(['name' => 'Pemrograman', 'slug' => 'pemrograman']);
    $post->tags()->attach($tag);
    $this->get('/cari?q=PHP')->assertOk()->assertSee('Belajar PHP');
    $this->get('/cari?q=Pemrograman')->assertOk()->assertSee('Belajar PHP');
});

test('valid image upload is stored under public uploads', function () {
    $admin = opsAdmin();
    $this->actingAs($admin)->post(route('admin.media.store'), ['file' => UploadedFile::fake()->image('cover.jpg', 32, 32), 'alt_text' => 'Ilustrasi artikel'])->assertRedirect();
    $media = Media::firstOrFail();
    try {
        expect($media->path)->toStartWith('uploads/');
        expect(File::exists(public_path($media->path)))->toBeTrue();
    } finally {
        File::delete(public_path($media->path));
    }
});

test('scheduled article is published by scheduler', function () {
    $post = Post::create(['title' => 'Terjadwal', 'slug' => 'terjadwal', 'status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);
    $this->artisan('schedule:run')->assertExitCode(0);
    expect($post->fresh()->status)->toBe('published');
    $this->get('/terjadwal')->assertOk();
});

test('bulk post actions enforce permissions and update selected posts', function () {
    $author = User::factory()->create(['role' => 'author']);
    $editor = User::factory()->create(['role' => 'editor']);
    $first = Post::create(['title' => 'Satu', 'slug' => 'satu', 'status' => 'draft', 'created_by' => $author->id]);
    $second = Post::create(['title' => 'Dua', 'slug' => 'dua', 'status' => 'draft', 'created_by' => $author->id]);
    $payload = ['ids' => [$first->id, $second->id], 'action' => 'publish'];
    $this->actingAs($author)->post(route('admin.posts.bulk'), $payload)->assertForbidden();
    expect($first->fresh()->status)->toBe('draft');
    $this->actingAs($editor)->post(route('admin.posts.bulk'), $payload)->assertRedirect();
    expect($first->fresh()->status)->toBe('published');
    expect($second->fresh()->published_at)->not->toBeNull();
    $this->actingAs($editor)->post(route('admin.posts.bulk'), ['ids' => [$first->id], 'action' => 'trash'])->assertRedirect();
    expect(Post::find($first->id))->toBeNull();
    expect(Post::find($second->id))->not->toBeNull();
});
