<?php

use App\Models\Post;
use App\Models\User;

test('CMS shell keeps all authorized menu routes and active article state', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $post = Post::create(['title' => 'Workspace QA', 'slug' => 'workspace-qa', 'status' => 'draft', 'created_by' => $admin->id]);
    $this->actingAs($admin);

    foreach ([route('admin.posts.create'), route('admin.posts.edit', $post), route('admin.categories.index')] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        expect($xpath->query('//*[@data-sidebar-toggle]')->length)->toBe(1);
        foreach (collect(config('cms_navigation'))->flatten(1) as $item) {
            $link = $xpath->query('//nav[@id="cms-sidebar-nav"]/div/a[@href="'.route($item['route']).'"]');
            expect($link->length)->toBe(1);
            expect($link->item(0)->getAttribute('aria-label'))->toBe($item['label']);
            expect($xpath->query('.//*[local-name()="svg"]', $link->item(0))->length)->toBe(1);
        }
        if (str_contains($url, '/posts/')) {
            $active = $xpath->query('//nav[@id="cms-sidebar-nav"]//a[@aria-current="page"]');
            expect($active->length)->toBe(1);
            expect($active->item(0)->getAttribute('href'))->toBe(route('admin.posts.index'));
        }
    }
});

test('only article create and edit expose one shared analyzer with drawer controls', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $post = Post::create(['title' => 'Workspace QA', 'slug' => 'workspace-qa', 'status' => 'draft', 'created_by' => $admin->id]);
    $this->actingAs($admin);

    foreach ([route('admin.posts.create'), route('admin.posts.edit', $post)] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect(substr_count($html, 'data-seo-analysis'))->toBe(1);
        expect(substr_count($html, 'data-analysis-open'))->toBe(1);
        expect(substr_count($html, 'data-analysis-close'))->toBe(1);
        expect($html)->toContain('aria-controls="article-analyzer"', 'data-taxonomy-quick-create', 'data-article-editor');
    }

    $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-seo-analysis', false)->assertDontSee('data-analysis-open', false);
});
