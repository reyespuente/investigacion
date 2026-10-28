<!doctype html><html lang="es"><head><meta charset="utf-8"><style>@page{margin:36px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#263347;line-height:1.6}h1{font-size:19px;color:#1B396A;line-height:1.4}h2{font-size:13px;color:#1B396A;margin-top:24px}.brand{font-size:12px;border-bottom:2px solid #117533;padding-bottom:12px}.muted{color:#667085}.footer{font-size:9px;margin-top:30px;border-top:1px solid #ddd;padding-top:10px}p{word-wrap:break-word}a{color:#1B396A}</style></head><body>
<div class="brand">{{ config('catalogue.portal_name') }} · {{ config('catalogue.institution') }}</div>
<p class="muted">FICHA BIBLIOGRÁFICA · {{ $publication->label() }} · {{ $publication->year }}</p>
<h1>{{ $publication->title }}</h1>
<p>{{ implode('; ',array_column($publication->authors,'nombre')) }}</p>
<p><strong>{{ $publication->venue }}</strong>
@foreach(['volumen'=>'Vol.','numero'=>'Núm.','paginas'=>'pp.'] as $k=>$label)
@if(!empty($publication->metadata['bibliografia'][$k])) · {{ $label }} {{ $publication->metadata['bibliografia'][$k] }}
@endif
@endforeach</p>
@if($publication->doi)<p>DOI: {{ $publication->doi }}</p>
@endif<h2>Resumen</h2>
<p>{{ $publication->abstract?:'Resumen no disponible en las fuentes recuperadas.' }}</p>
<h2>Acceso al trabajo</h2>
<p><a href="{{ $publication->external_url }}">{{ $publication->external_url }}</a></p>
<h2>Fuente de esta ficha</h2>
<p><a href="{{ route('publications.show',$publication) }}">{{ route('publications.show',$publication) }}</a></p>
<div class="footer">Generada el {{ now()->format('d/m/Y') }}. Esta ficha contiene metadatos y enlaces; no reproduce el texto completo de la publicación. Los datos y licencias corresponden a sus fuentes y titulares.</div></body></html>
