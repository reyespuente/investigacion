<div class="section-heading">
<p role="status"><strong>{{ number_format($publications->total()) }}</strong> {{ $publications->total() === 1 ? 'resultado' : 'resultados' }}</p></div>
@forelse($publications as $publication)
@include('partials.card',['featured'=>false])
@empty<div class="empty"><i class="bi bi-search" aria-hidden="true"></i>
<h2>No encontramos coincidencias</h2>
<p>Prueba con un apellido, menos palabras o quitando un filtro.</p><a href="{{ route('publications.index') }}">Ver todas las publicaciones</a></div>
@endforelse<div class="pagination">{{ $publications->links() }}</div>
