@php $image = $post->featured_image ?: $defaultImage; @endphp
<article class="featured-article">
    <div class="featured-visual">
        <span class="featured-badge">ARTIKEL UNGGULAN</span>
        @if($image)
            <img src="{{ $image }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="960" height="600" loading="lazy">
        @else
            <div class="public-image-fallback featured-fallback" role="img" aria-label="Besofton Insights — {{ $post->title }}">
                <span>BESOFTON<br>INSIGHTS<span class="fallback-dot">.</span></span>
            </div>
        @endif
    </div>
    <div class="featured-copy">
        <p class="public-eyebrow public-category-dot">{{ $post->category?->name ?? ucfirst($post->content_type) }}</p>
        <h3 class="featured-title"><a wire:navigate href="{{ route('post.show', $post->slug) }}">{{ $post->title }}</a></h3>
        @if($post->excerpt)<p class="featured-excerpt">{{ $post->excerpt }}</p>@endif
        <a wire:navigate href="{{ route('post.show', $post->slug) }}" class="public-gold-button">Baca Selengkapnya <span aria-hidden="true">→</span></a>
        <div class="public-post-meta">
            <span><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>{{ $post->published_at?->locale('id')->translatedFormat('j F Y') }}</span>
            <span><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ max(1, ceil(str_word_count($post->content_plain ?? '') / 200)) }} menit baca</span>
        </div>
    </div>
</article>
