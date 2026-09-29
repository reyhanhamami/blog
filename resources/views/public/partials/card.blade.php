<article class="public-card discovery-article-card">
    <a wire:navigate href="{{ route('post.show', $post->slug) }}" class="public-card-media" aria-label="Baca {{ $post->title }}">
        @if($post->featured_image)<img src="{{ $post->featured_image }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" width="640" height="360">
        @else<div class="public-card-fallback" aria-hidden="true"><span>IDE. PANDUAN.<br><em>INSIGHTS.</em></span></div>@endif
    </a>
    <div class="public-card-body">
        <span class="public-badge">{{ $post->category?->name ?? (\App\Services\ContentDiscoveryService::CONTENT_TYPES[$post->content_type] ?? ucfirst(str_replace('_', ' ', $post->content_type))) }}</span>
        <h3><a wire:navigate href="{{ route('post.show', $post->slug) }}">{{ $post->title }}</a></h3>
        @if($post->excerpt)<p class="discovery-card-excerpt">{{ $post->excerpt }}</p>@endif
        <div class="discovery-card-end"><p class="public-card-meta">@if($post->author)<span>{{ $post->author->name }}</span>@endif <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->timezone('Asia/Jakarta')->format('d M Y') }}</time>@if($post->content_plain)<span>{{ max(1, ceil(str_word_count($post->content_plain) / 200)) }} menit baca</span>@endif</p><a wire:navigate href="{{ route('post.show', $post->slug) }}" class="discovery-card-arrow" aria-label="Baca {{ $post->title }}">→</a></div>
    </div>
</article>
