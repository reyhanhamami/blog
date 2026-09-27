<?php

use App\Livewire\NewsletterForm;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

function homePost(string $slug, array $attributes = []): Post
{
    return Post::create(array_merge([
        'title' => 'Artikel '.$slug,
        'slug' => $slug,
        'excerpt' => 'Ringkasan '.$slug,
        'content' => '<p>Konten artikel.</p>',
        'content_plain' => 'Konten artikel.',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'content_type' => 'article',
    ], $attributes));
}

test('homepage renders real featured and latest articles and excludes unpublished content', function () {
    $category = Category::create(['name' => 'Strategi', 'slug' => 'strategi', 'is_active' => true]);
    homePost('utama', ['title' => 'Artikel Utama', 'is_featured' => true, 'category_id' => $category->id]);
    homePost('terbaru', ['title' => 'Artikel Terbaru Nyata', 'category_id' => $category->id]);
    homePost('draf', ['title' => 'Artikel Draf', 'status' => 'draft']);
    homePost('jadwal', ['title' => 'Artikel Terjadwal', 'published_at' => now()->addDay()]);
    homePost('noindex', ['title' => 'Artikel Noindex', 'noindex' => true]);

    $this->get(route('home'))->assertOk()
        ->assertSee('Artikel Utama')->assertSee('Artikel Terbaru Nyata')
        ->assertSee('Strategi')->assertSee(route('post.show', 'utama'))
        ->assertSee('action="'.route('search').'"', false)
        ->assertSee('/storage/logo.png')
        ->assertDontSee('Artikel Draf')->assertDontSee('Artikel Terjadwal')->assertDontSee('Artikel Noindex');
    $this->get(route('categories.index'))->assertOk()->assertSee('Strategi');
});

test('homepage has a genuine empty state and no fake article cards', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('Artikel terbaru sedang disiapkan.')
        ->assertDontSee('Artikel Unggulan', false)
        ->assertSee('Masukkan alamat email Anda');
});

test('multiple featured articles are server rendered with usable slider controls', function () {
    homePost('unggulan-satu', ['is_featured' => true]);
    homePost('unggulan-dua', ['is_featured' => true]);

    $html = $this->get(route('home'))->assertOk()
        ->assertSee('Artikel unggulan sebelumnya')
        ->assertSee('Artikel unggulan berikutnya')
        ->assertSee('Artikel unggulan-satu')
        ->assertSee('Artikel unggulan-dua')->getContent();

    expect(substr_count($html, 'class="swiper-slide"'))->toBe(2);
    expect($html)->toContain('public-home-');
});

test('newsletter validates email, stores one subscriber, and permits unsubscribe', function () {
    Mail::fake();
    RateLimiter::clear('newsletter:127.0.0.1');
    Livewire::test(NewsletterForm::class)->set('email', 'not-an-email')->call('subscribe')->assertHasErrors(['email']);
    Livewire::test(NewsletterForm::class)->set('email', 'Test@Example.com')->call('subscribe')->assertHasNoErrors();
    $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'test@example.com', 'status' => NewsletterSubscriber::SUBSCRIBED]);

    Livewire::test(NewsletterForm::class)->set('email', 'test@example.com')->call('subscribe')->assertSee('sudah terdaftar');
    expect(NewsletterSubscriber::count())->toBe(1);

    $this->post(route('newsletter.unsubscribe'), ['email' => 'TEST@example.com'])->assertRedirect();
    Mail::assertSentCount(1);
    $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'test@example.com', 'status' => NewsletterSubscriber::SUBSCRIBED]);
    $subscriber = NewsletterSubscriber::firstOrFail();
    $this->get(route('newsletter.unsubscribe.confirm', $subscriber))->assertForbidden();
    $signedUrl = URL::temporarySignedRoute('newsletter.unsubscribe.confirm', now()->addHour(), ['subscriber' => $subscriber]);
    $this->get($signedUrl)->assertRedirect(route('home'));
    $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'test@example.com', 'status' => NewsletterSubscriber::UNSUBSCRIBED]);
});

test('newsletter unsubscribe response does not disclose subscriber existence', function () {
    Mail::fake();
    $existing = $this->post(route('newsletter.unsubscribe'), ['email' => 'missing@example.com'])->assertRedirect();
    expect($existing->getSession()->get('success'))->toContain('Jika email terdaftar');
    $this->assertDatabaseCount('newsletter_subscribers', 0);
    Mail::assertNothingSent();
});
