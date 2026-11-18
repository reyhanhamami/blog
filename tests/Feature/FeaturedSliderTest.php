<?php

use App\Models\Post;

test('featured article counts preserve real anchors and load carousel assets only when needed', function (int $count) {
    foreach (range(1, $count) as $number) {
        Post::create([
            'title' => 'Unggulan '.$number, 'slug' => 'slider-'.$number,
            'status' => 'published', 'published_at' => now()->subDays($number),
            'is_featured' => true, 'featured_image' => '/uploads/fixture-'.$number.'.png',
        ]);
    }
    $html = $this->get('/')->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $slides = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " swiper-slide ")]');
    expect($slides)->toHaveCount($count);
    foreach ($slides as $index => $slide) {
        $slug = 'slider-'.($index + 1);
        foreach (['.//h3/a', './/a[contains(@class,"public-gold-button")]'] as $selector) {
            expect($xpath->query($selector, $slide)->item(0)->getAttribute('href'))->toBe(route('post.show', $slug));
        }
        expect($xpath->query('.//img[@class="featured-image"]', $slide)->item(0)->getAttribute('loading'))->toBe($index === 0 ? 'eager' : 'lazy');
    }
    expect(str_contains($html, 'data-featured-toggle'))->toBe($count > 1);
    expect((bool) preg_match('/(?:public-home-|resources\/js\/public-home\.js)/', $html))->toBe($count > 1);
})->with([1, 2, 3]);
