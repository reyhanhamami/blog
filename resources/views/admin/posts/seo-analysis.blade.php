<section class="card admin-post-analysis space-y-4" data-seo-analysis data-site-url="{{ url('/') }}" data-post-exists="{{ $post->exists ? '1' : '0' }}" data-author-fallback="Tim Besofton" data-saved-date="{{ $post->updated_at?->toAtomString() }}" data-published-date="{{ $post->published_at?->toAtomString() }}" aria-label="SEO dan AEO Analysis">
    <div><h2 class="text-lg font-semibold">SEO &amp; AEO Analysis</h2><p class="mt-1 text-xs text-slate-500">Semua indikator tidak harus hijau. Prioritaskan kualitas dan relevansi konten.</p></div>
    <div class="grid grid-cols-3 gap-2 text-center text-xs" data-analysis-summary></div>
    <p class="text-xs text-slate-600" data-analysis-stats aria-live="polite"></p>
    <p class="rounded-lg bg-slate-100 p-3 text-xs text-slate-700" data-analysis-notice hidden></p>
    <div class="rounded-lg border border-slate-200 p-3" aria-label="Pratinjau hasil pencarian">
        <h3 class="text-xs font-semibold text-slate-700">Pratinjau hasil pencarian</h3>
        <p class="mt-2 break-all text-xs text-slate-500" data-analysis-preview-url></p>
        <p class="mt-1 text-sm font-semibold text-indigo-700" data-analysis-preview-title></p>
        <p class="mt-1 text-xs text-slate-600" data-analysis-preview-description></p>
    </div>
    <div class="flex gap-1 overflow-x-auto border-b border-slate-200" role="tablist" aria-label="Kelompok analisis">
        @foreach(['seo' => 'SEO', 'readability' => 'Readability', 'aeo' => 'AEO'] as $group => $label)
            <button type="button" role="tab" id="analysis-tab-{{ $group }}" aria-controls="analysis-tab-panel" class="rounded-t px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" data-analysis-tab="{{ $group }}" aria-selected="{{ $group === 'seo' ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>
    <div id="analysis-tab-panel" role="tabpanel" aria-labelledby="analysis-tab-seo" data-analysis-results class="space-y-3" aria-live="polite"></div>
</section>
