@php $image = $post->featured_image ?: $defaultImage; @endphp
<article class="home-article-card">
    <div class="home-card-image">
        @if($image)
            <img src="{{ $image }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="640" height="360" loading="lazy">
        @else
            <div class="public-image-fallback home-card-fallback" aria-hidden="true"><span>BESOFTON<br>INSIGHTS<span class="fallback-dot">.</span></span></div>
        @endif
    </div>
    <div class="home-card-body">
        <span class="home-card-category">{{ $post->category?->name ?? ucfirst($post->content_type) }}</span>
        <h3><a wire:navigate href="{{ route('post.show', $post->slug) }}">{{ $post->title }}</a></h3>
        @if($post->excerpt)<p class="home-card-excerpt">{{ $post->excerpt }}</p>@endif
        <div class="home-card-bottom">
            <div class="public-post-meta">
                <span><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>{{ $post->published_at?->locale('id')->translatedFormat('j F Y') }}</span>
                <span><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ max(1, ceil(str_word_count($post->content_plain ?? '') / 200)) }} menit</span>
            </div>
            <a wire:navigate href="{{ route('post.show', $post->slug) }}" class="home-card-arrow" aria-label="Baca {{ $post->title }}">→</a>
        </div>
    </div>
</article>
