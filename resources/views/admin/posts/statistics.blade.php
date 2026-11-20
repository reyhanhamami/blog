<section class="card mb-6" data-article-statistics>
    <h2 class="text-xl font-semibold">Statistik Artikel <span class="text-xs text-slate-500">Superadmin · read-only</span></h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-3 xl:grid-cols-6">
        @foreach(['Total all-time'=>'total','Unique Visitors 30 hari (estimasi)'=>'unique','Views hari ini'=>'today','Views 7 hari'=>'seven','Views 30 hari'=>'thirty','Average Daily Views 30 hari'=>'average'] as $label=>$key)
        <div><h3 class="text-xs text-slate-500">{{ $label }}</h3><p class="mt-1 text-2xl font-bold">{{ number_format($stats[$key], $key === 'average' ? 1 : 0, ',', '.') }}</p></div>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-slate-500">Counter historis: {{ number_format($stats['legacy'],0,',','.') }}. Analytics baru tersedia sejak {{ $stats['started'] }}; tidak ada unique atau distribusi historis buatan. Average memakai hari tercatat termasuk nol.</p>
    <div class="mt-4 min-h-[180px] min-w-0 w-full overflow-hidden" data-analytics-chart data-mini="1" data-series="{{ json_encode($stats['series']) }}" role="img" aria-label="Grafik tayangan artikel 30 hari"></div>
</section>
