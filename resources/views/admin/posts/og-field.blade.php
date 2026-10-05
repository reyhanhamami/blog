<div data-og-control>
    <span class="form-label">OG image</span>
    <div class="flex flex-wrap gap-4 text-sm"><label><input type="radio" name="og_image_mode" value="featured" @checked(! old('og_image', $post->og_image))> Gunakan gambar artikel</label><label><input type="radio" name="og_image_mode" value="custom" @checked((bool) old('og_image', $post->og_image))> Pilih gambar lain</label></div>
    <p class="mt-2 text-xs text-slate-500">Ukuran sekitar 1200×630 px disarankan untuk tampilan saat dibagikan.</p>
    <div class="mt-3" data-og-custom @if(! old('og_image', $post->og_image)) hidden @endif>@include('admin.posts.image-field', ['kind' => 'og'])</div>
    <div class="article-image-card article-image-card--og mt-3" data-og-featured-preview @if(old('og_image', $post->og_image)) hidden @endif><img @if(old('featured_image', $post->featured_image)) src="{{ old('featured_image', $post->featured_image) }}" @endif alt="Pratinjau OG dari gambar artikel" data-og-featured-image @if(! old('featured_image', $post->featured_image)) hidden @endif><span data-og-featured-empty @if(old('featured_image', $post->featured_image)) hidden @endif>Gambar artikel belum dipilih</span></div>
</div>
