
@extends('layouts.app')
@section('title','Estadísticas')
@section('content')<div class="page-heading">
<p class="eyebrow">EL CONOCIMIENTO EN PERSPECTIVA</p>
<h1>Una mirada al catálogo.</h1>
<p class="muted">Solo se cuentan publicaciones disponibles y autorías confirmadas. La cobertura no es una evaluación de calidad ni productividad total.</p></div>
<div class="stats-grid">
<section>
<h2>Por tipo de publicación</h2>
@foreach($types as $row)<div class="stat-row"><span>{{ config('catalogue.types')[$row->type]??$row->type }}</span><strong>{{ number_format($row->total) }}</strong><meter min="0" max="{{ max(1,$types->sum('total')) }}" value="{{ $row->total }}">{{ $row->total }}</meter></div>
@endforeach<h2>Por año</h2>
@foreach($years->take(20) as $row)<div class="stat-row"><span>{{ $row->year }}</span><strong>{{ number_format($row->total) }}</strong><meter min="0" max="{{ max(1,$years->max('total')) }}" value="{{ $row->total }}">{{ $row->total }}</meter></div>
@endforeach</section>
<section class="ranking">
<h2>Investigadores por publicaciones</h2>
<p class="small muted">Cada trabajo compartido cuenta una vez para cada autor confirmado.</p><ol>
@foreach($ranking as $person)<li><span class="rank-number">{{ $loop->iteration }}</span><a href="{{ route('researchers.show',$person) }}"><span><strong>{{ $person->name }}</strong><small>{{ $person->publications_count }} publicaciones</small></span></a></li>
@endforeach</ol></section></div>
@endsection
