{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
@if($index)
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">@foreach($urls as $entry)<sitemap><loc>{{ $entry['url'] }}</loc></sitemap>@endforeach</sitemapindex>
@else
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">@foreach($urls as $entry)<url><loc>{{ $entry['url'] }}</loc>@if(isset($entry['lastmod']))<lastmod>{{ $entry['lastmod'] }}</lastmod>@endif</url>@endforeach</urlset>
@endif
