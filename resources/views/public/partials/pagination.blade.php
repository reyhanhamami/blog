@if($paginator->hasPages())
<nav class="public-pager" role="navigation" aria-label="Navigasi halaman">
    @if($paginator->onFirstPage())<span class="is-disabled" aria-disabled="true">← Sebelumnya</span>@else<a wire:navigate href="{{ $paginator->previousPageUrl() }}" rel="prev">← Sebelumnya</a>@endif
    @foreach($elements as $element)
        @if(is_string($element))<span class="public-pager-ellipsis" aria-hidden="true">{{ $element }}</span>@endif
        @if(is_array($element))@foreach($element as $page => $url)
            @if($page == $paginator->currentPage())<span class="is-current" aria-current="page">{{ $page }}</span>@else<a wire:navigate href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>@endif
        @endforeach @endif
    @endforeach
    @if($paginator->hasMorePages())<a wire:navigate href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya →</a>@else<span class="is-disabled" aria-disabled="true">Berikutnya →</span>@endif
</nav>
@endif
