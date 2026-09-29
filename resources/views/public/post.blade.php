@extends('public.layout')
@php $articleSeoTitle = $post->seo_title ?: $post->title; @endphp
@section('seo_title', \Illuminate\Support\Str::endsWith($articleSeoTitle, ' | Besofton Insights') ? $articleSeoTitle : $articleSeoTitle.' | Besofton Insights')
@section('seo_description', $post->seo_description ?: $post->excerpt ?: \Illuminate\Support\Str::limit($post->content_plain ?: strip_tags($post->content ?? '') ?: $post->title, 155))
@section('canonical', url('/'.$post->slug))
@section('robots', ($preview || $post->noindex) ? 'noindex,nofollow' : 'index,follow')
@section('og_type', 'article')
@section('og_title', $post->og_title ?: $post->seo_title ?: $post->title)
@section('og_description', $post->og_description ?: $post->seo_description ?: $post->excerpt ?: '')
@section('og_image', $post->og_image ?: $post->featured_image ?: '')
@push('head')
@php $crumbs = [['@type'=>'ListItem','position'=>1,'name'=>'Beranda','item'=>route('home')], ['@type'=>'ListItem','position'=>2,'name'=>$post->title,'item'=>url('/'.$post->slug)]]; $breadcrumbSchema = ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$crumbs]; @endphp
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@php
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post->title,
    'description' => $post->seo_description ?: $post->excerpt ?: \Illuminate\Support\Str::limit($post->content_plain ?: strip_tags($post->content ?? '') ?: $post->title, 155),
    'datePublished' => $post->published_at?->toAtomString(),
    'dateModified' => $post->updated_at?->toAtomString(),
    'author' => ['@type' => 'Person', 'name' => $post->author?->name ?? 'Tim Besofton'],
    'publisher' => ['@type' => 'Organization', 'name' => \App\Models\Setting::valueFor('site_name', 'Besofton Insights')],
    'mainEntityOfPage' => url('/'.$post->slug),
];
if ($post->featured_image) {
    $schema['image'] = $post->featured_image;
}
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
<div class="public-shell">
    @if($preview)<div class="public-notice" role="status">Preview privat · halaman ini tidak diindeks.</div>@endif
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span>@if($post->category)<a wire:navigate href="{{ route('category.show', $post->category) }}">{{ $post->category->name }}</a><span>/</span>@endif<span aria-current="page">{{ $post->title }}</span></nav>
    <div id="reading-progress" aria-hidden="true"></div>
    <article data-reading-article>
        <header class="article-hero">
            <p class="public-kicker">{{ $post->category?->name ?? ucfirst(str_replace('_', ' ', $post->content_type)) }}@if($post->topics->isNotEmpty()) <span aria-hidden="true">·</span> {{ $post->topics->first()->name }}@endif</p>
            <h1>{{ $post->title }}</h1>
            @if($post->excerpt)<p class="article-deck">{{ $post->excerpt }}</p>@endif
            <div class="article-byline">
                <div class="article-avatar" aria-hidden="true">{{ mb_substr($post->author?->name ?? 'Besofton', 0, 1) }}</div>
                <div><span>Oleh @if($post->author)<a wire:navigate href="{{ route('author.show', $post->author) }}">{{ $post->author->name }}</a>@else Tim Besofton @endif</span><span class="article-date"><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->timezone('Asia/Jakarta')->format('d M Y') ?? 'Belum terbit' }}</time> · {{ max(1, ceil(str_word_count($post->content_plain ?? '') / 200)) }} menit baca @if($post->updated_at && $post->published_at && $post->updated_at->greaterThan($post->published_at->copy()->addDay())) · Diperbarui <time datetime="{{ $post->updated_at->toDateString() }}">{{ $post->updated_at->timezone('Asia/Jakarta')->format('d M Y') }}</time>@endif</span></div>
            </div>
            <div class="article-actions"><a href="https://wa.me/?text={{ urlencode($post->title.' '.url('/'.$post->slug)) }}" target="_blank" rel="noopener noreferrer" class="public-outline-button">Bagikan ↗</a><button type="button" data-copy="{{ url('/'.$post->slug) }}" class="public-outline-button">Salin tautan</button>@auth<form action="{{ route('reader.bookmark', $post) }}" method="post">@csrf<button type="submit" class="public-outline-button">Simpan / hapus bookmark</button></form>@endauth</div>
        </header>
        @if($post->featured_image)<figure class="article-featured"><img src="{{ $post->featured_image }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="1200" height="675" fetchpriority="high"></figure>@endif
        <div class="article-layout">
            <div class="article-main">
                @if($post->direct_answer)<aside class="article-answer"><h2>Jawaban singkat</h2><p>{{ $post->direct_answer }}</p></aside>@endif
                @if($post->key_takeaways)<aside class="article-takeaways"><h2>Inti Pembahasan</h2><ul>@foreach(preg_split('/\r?\n/', $post->key_takeaways) as $point)@if(trim($point))<li>{{ trim($point) }}</li>@endif @endforeach</ul></aside>@endif
                <nav id="article-toc" aria-label="Daftar isi" class="article-toc-mobile" hidden><h2>Dalam artikel</h2><ol></ol></nav>
                <div class="article-content prose-besofton">{!! app(\App\Services\Content\ContentRenderer::class)->render($post->content) !!}</div>
                @if($post->summary)<section class="article-summary"><h2>Ringkasan</h2><p>{{ $post->summary }}</p></section>@endif
                @if($post->sources->isNotEmpty())<section class="article-references"><h2>Referensi</h2><ol>@foreach($post->sources as $source)<li><a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->title }}</a>@if($source->publisher) <span>· {{ $source->publisher }}</span>@endif</li>@endforeach</ol></section>@endif
                @if($post->tags->isNotEmpty())<nav class="public-chip-row article-tags" aria-label="Tag artikel">@foreach($post->tags as $tag)<a wire:navigate href="{{ route('tag.show', $tag) }}" class="public-chip">#{{ $tag->name }}</a>@endforeach</nav>@endif
                @if(!$preview && $post->quiz && $post->quiz->status === 'published')<section class="article-quiz-cta"><p class="public-kicker">UJI PEMAHAMAN</p><h2>Sudah memahami materi ini?</h2><p>{{ $post->quiz->title }}</p><a wire:navigate href="{{ route('quiz.show', $post->quiz) }}" class="public-button is-gold">Mulai Quiz <span aria-hidden="true">→</span></a></section>@endif
                @if(!$preview)<form class="article-feedback" action="{{ route('post.feedback', $post) }}" method="post">@csrf<div><p class="public-kicker">PENDAPAT ANDA</p><h2>Apakah artikel ini membantu?</h2></div><div class="public-form-actions"><button name="helpful" value="1" class="public-outline-button">Ya, membantu</button><button name="helpful" value="0" class="public-outline-button">Belum</button></div></form>@endif
                @if($post->author)<section class="article-author"><div class="article-avatar" aria-hidden="true">{{ mb_substr($post->author->name, 0, 1) }}</div><div><p class="public-kicker">DITULIS OLEH</p><h2><a wire:navigate href="{{ route('author.show', $post->author) }}">{{ $post->author->name }}</a></h2>@if($post->author->short_bio)<p>{{ $post->author->short_bio }}</p>@endif</div></section>@endif
                @if(!$preview && ($previous || $next))<nav aria-label="Navigasi artikel" class="article-adjacent">@if($previous)<a wire:navigate rel="prev" href="{{ route('post.show', $previous->slug) }}"><span>← Artikel Sebelumnya</span><strong>{{ $previous->title }}</strong></a>@endif @if($next)<a wire:navigate rel="next" href="{{ route('post.show', $next->slug) }}"><span>Artikel Berikutnya →</span><strong>{{ $next->title }}</strong></a>@endif</nav>@endif
            </div>
            <aside class="article-sidebar" aria-label="Perangkat baca"><div class="article-sidebar-inner"><p class="public-kicker">DALAM ARTIKEL</p><div id="article-toc-desktop"></div><div class="article-sidebar-share"><a href="https://wa.me/?text={{ urlencode($post->title.' '.url('/'.$post->slug)) }}" target="_blank" rel="noopener noreferrer">Bagikan ↗</a><button type="button" data-copy="{{ url('/'.$post->slug) }}">Salin tautan</button></div></div></aside>
        </div>
    </article>
    @if(!$preview)<section class="article-questions public-section"><div class="article-section-header"><p class="public-kicker">DISKUSI</p><h2 class="public-section-heading">Tanya jawab</h2><p>Pertanyaan ditinjau sebelum tampil.</p></div><div class="article-question-layout"><form action="{{ route('post.question', $post) }}" method="post" class="public-panel public-form-stack">@csrf @guest<div class="article-question-guest"><div class="public-field"><label for="question-name">Nama</label><input id="question-name" class="form-input" name="name" required value="{{ old('name') }}"></div><div class="public-field"><label for="question-email">Email</label><input id="question-email" class="form-input" name="email" type="email" required value="{{ old('email') }}"></div></div>@endguest<div class="public-field"><label for="question-body">Pertanyaan Anda</label><textarea id="question-body" class="form-input" name="body" required minlength="10" maxlength="2000" rows="4" placeholder="Tulis pertanyaan Anda...">{{ old('body') }}</textarea>@error('body')<p class="error">{{ $message }}</p>@enderror</div><button class="public-button" type="submit">Kirim pertanyaan →</button></form><div class="article-question-list">@forelse($questions as $question)<article class="public-panel"><strong>{{ $question->name }}</strong><p>{{ $question->body }}</p>@foreach($question->answers as $answer)<div class="article-question-answer"><strong>Jawaban {{ $answer->user?->name ?? 'Tim Besofton' }}</strong><p>{{ $answer->body }}</p></div>@endforeach</article>@empty<div class="public-empty"><p>Belum ada pertanyaan. Jadilah yang pertama bertanya.</p></div>@endforelse</div></div></section>@endif
    @if($related->isNotEmpty())<section class="public-section"><p class="public-kicker">BACA LAGI</p><h2 class="public-section-heading">Artikel terkait</h2><div class="public-card-grid">@foreach($related as $relatedPost)@include('public.partials.card', ['post'=>$relatedPost])@endforeach</div></section>@endif
    @if(!$preview)<section class="article-newsletter public-section"><div><p class="public-kicker">TERUS DAPATKAN INSIGHT</p><h2>Ide baru, langsung ke inbox Anda.</h2><p>Artikel dan panduan Besofton Insights untuk langkah berikutnya.</p></div><a href="#footer-newsletter-title" class="public-button is-gold">Berlangganan →</a></section>@endif
</div>
@endsection
