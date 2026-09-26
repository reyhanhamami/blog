@extends('admin.layout')
@section('title', 'Kalender Editorial')
@section('content')
<h1 class="text-3xl font-bold">Kalender editorial</h1><p class="mt-2 text-sm text-slate-500">Jadwal dan publikasi konten nyata. Klik acara untuk membuka editor.</p><div class="card mt-6"><div id="editorial-calendar"></div><script type="application/json" id="editorial-events">{!! json_encode($events, JSON_HEX_TAG) !!}</script></div>
@endsection