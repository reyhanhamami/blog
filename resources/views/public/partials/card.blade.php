<article class="public-card">
    <a wire:navigate href="{{ route('post.show', $post->slug) }}" class="public-card-media" aria-label="Baca {{ $post->title }}">
        @if($post->featured_image)<img src="{{ $post->featured_image }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" width="640" height="360">
        @else<div class="public-card-fallback" aria-hidden="true"><span>IDE. PANDUAN.<br><em>INSIGHTS.</em></span></div>@endif
    </a>
    <div class="public-card-body">
        <span class="public-badge">{{ $post->category?->name ?? ucfirst(str_replace('_', ' ', $post->content_type)) }}</span>
        <h3><a wire:navigate href="{{ route('post.show', $post->slug) }}">{{ $post->title }}</a></h3>
        @if($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
        <p class="public-card-meta">{{ $post->author?->name ?? 'Tim Besofton' }} · <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->timezone('Asia/Jakarta')->format('d M Y') }}</time></p>
    </div>
</article>
