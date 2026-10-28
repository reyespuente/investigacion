
@if($paginator->hasPages())<nav aria-label="Paginación" class="button-row">
@if($paginator->onFirstPage())<span class="muted">Anterior</span>
@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
@endif
@foreach(range(max(1,$paginator->currentPage()-2),min($paginator->lastPage(),$paginator->currentPage()+2)) as $page)
@if($page===$paginator->currentPage())<span aria-current="page" class="badge">{{ $page }}</span>
@else<a href="{{ $paginator->url($page) }}">{{ $page }}</a>
@endif
@endforeach
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
@else<span class="muted">Siguiente</span>
@endif <span class="small muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span></nav>
@endif
