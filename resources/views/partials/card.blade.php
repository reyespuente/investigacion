<article
@class(['publication-card','featured'=>$featured??false])>
@if($featured??false)<span class="badge"><span class="badge-dot"></span> Lo más reciente</span>
@endif<div class="publication-meta"><span>{{ $publication->label() }}</span><span class="meta-dot">·</span><time>{{ $publication->year??'Sin fecha' }}</time></div>
<h3><a href="{{ route('publications.show',$publication) }}">{{ $publication->title }}</a></h3>
@if(($featured??false)&&$publication->abstract)<p class="abstract-preview">{{ Str::limit($publication->abstract,250) }}</p>
@endif<p class="venue">{{ $publication->venue }}</p>
<p class="authors-preview">{{ implode(', ',array_column(array_slice($publication->authors,0,3),'nombre')) }}
@if(count($publication->authors)>3), et al.
@endif</p>
<div class="card-actions"><a href="{{ route('publications.show',$publication) }}">Ver ficha <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a class="cite-link" href="{{ route('publications.show',$publication) }}#citar"><i class="bi bi-quote" aria-hidden="true"></i> Citar</a></div></article>
