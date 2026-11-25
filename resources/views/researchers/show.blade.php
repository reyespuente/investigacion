
@extends('layouts.app')
@section('title',$researcher->name)
@section('content')<a class="back-link" href="{{ route('researchers.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Investigadores</a>
<header class="profile-header"><span class="avatar profile-avatar">
@if($researcher->avatar_path)<img src="{{ route('researchers.avatar',$researcher) }}" alt="Foto de {{ $researcher->name }}">
@else{{ $researcher->initials() }}
@endif</span>
<div>
<p class="eyebrow">PERFIL ACADÉMICO</p>
<h1>{{ mb_convert_case($researcher->name,MB_CASE_TITLE,'UTF-8') }}</h1>
@if($researcher->professional_title)<p class="profile-title">{{ $researcher->professional_title }}</p>@endif
@if($researcher->career)<p>{{ $researcher->career }}</p>@endif
@if($researcher->department)<a href="{{ route('researchers.index',['department'=>$researcher->department]) }}">{{ $researcher->department }}</a>
@else<p>{{ config('catalogue.institution') }}</p>@endif
@if($researcher->orcid)<a class="orcid" href="{{ $researcher->orcid }}" rel="noopener noreferrer" target="_blank">ORCID <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
@endif</div>
<div class="profile-claim">
@if(auth()->id() && auth()->id()===$researcher->user_id)
<a class="button" href="{{ route('editor.create') }}">Agregar artículos</a>
<a href="{{ route('account') }}">Mis publicaciones</a>
<a href="{{ route('profile.edit') }}">Editar perfil público</a>
@elseif(!$researcher->user_id && $researcher->claim_email_hash)
@auth<form method="post" action="{{ route('researchers.claim',$researcher) }}">
@csrf<button class="button">Agregar artículos</button></form>
@else<a class="button" href="{{ route('login',['perfil'=>$researcher->id]) }}">Agregar artículos</a>
@endauth<p class="small muted">Vincula este perfil con el correo institucional registrado.</p>
@endif
@if(auth()->user()?->role==='admin')
 <a href="{{ route('admin.researchers.edit',$researcher) }}">Administrar perfil</a>
@endif
</div></header>
<nav class="researcher-tabs" aria-label="Secciones del perfil">
    @foreach(['informacion' => ['Información', 'person-vcard'], 'publicaciones' => ['Publicaciones', 'journal-text'], 'proyectos' => ['Proyectos', 'kanban']] as $key => [$label, $icon])
        <a href="{{ route('researchers.show', ['researcher' => $researcher, 'seccion' => $key]) }}" @if($section === $key) aria-current="page" @endif>
            <i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
<div class="researcher-tab-content">
@if($section === 'informacion')
<div class="researcher-info">
@php
    $emails = $researcher->publicEmails();
    $website = $researcher->contacts['website'] ?? null;
    $phone = $researcher->contacts['phone'] ?? null;
@endphp
@if($researcher->bio)<section class="academic-section"><h2>Acerca de mí</h2><p class="long-text">{{ $researcher->bio }}</p></section>
@endif
@if($emails || $website || $phone)
<section class="academic-section"><h2>Contacto</h2><div class="tags">
@foreach($emails as $email)
<a href="mailto:{{ $email }}">{{ $email }}</a>
@endforeach
@if($website)
<a href="{{ $website }}" rel="noopener noreferrer" target="_blank">Sitio web <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
@endif
@if($phone)
<span>{{ $phone }}</span>
@endif
</div></section>
@endif
@if($researcher->degrees)
<section class="academic-section"><h2>Formación académica</h2>
    <div class="education-list">@foreach($researcher->degrees as $degree)<article class="education-item">
        <i class="bi bi-mortarboard" aria-hidden="true"></i>
        <div><h3>{{ $degree['level'] }}@if(!empty($degree['program'])) · {{ $degree['program'] }}@endif</h3>
            @if(!empty($degree['institution']))<p>{{ $degree['institution'] }}</p>@endif
            @if(!empty($degree['year']))<span class="small muted">{{ $degree['year'] }}</span>@endif
        </div>
    </article>@endforeach</div>
</section>
@endif
@if($researcher->recognitions)
<section class="academic-section"><h2>Reconocimientos</h2><div class="recognition-grid">
    @if($prodep = $researcher->recognitions['prodep'] ?? null)<article class="recognition-card"><i class="bi bi-patch-check" aria-hidden="true"></i><div><h3>PRODEP</h3><p>Vigencia {{ \Carbon\Carbon::parse($prodep['start'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($prodep['until'])->format('d/m/Y') }}</p>@if($prodep['until'] < now()->toDateString())<span class="small muted">Vigencia finalizada</span>@endif</div></article>@endif
    @if($level = $researcher->recognitions['snii'] ?? null)<article class="recognition-card"><i class="bi bi-award" aria-hidden="true"></i><div><h3>SNII</h3><p>{{ $level }}</p></div></article>@endif
</div></section>
@endif
@if($researcher->researchLines->isNotEmpty())
<section class="academic-section"><h2>Líneas de investigación</h2><div class="tags">@foreach($researcher->researchLines as $line)<a href="{{ route('lines.show', $line) }}">{{ $line->label() }}@if($line->code) ({{ $line->code }})@endif</a>@endforeach</div></section>
@endif
@if($documents->isNotEmpty())<section class="academic-section">
<h2>Documentos y certificaciones</h2>
@foreach($documents as $document)<a class="document-link" href="{{ route('documents.show', $document) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>{{ $document->title }}</a>
@endforeach</section>
@endif
@if(!$researcher->bio && !$emails && !$website && !$phone && !$researcher->degrees && !$researcher->recognitions && $researcher->researchLines->isEmpty() && $documents->isEmpty())
<div class="empty"><h2>Información académica</h2><p>Este perfil aún no tiene información adicional.</p></div>
@endif
</div>
@elseif($section === 'proyectos')
<section>
    <h2>Proyectos de investigación</h2>
    @foreach(['Como responsable técnico' => $responsibleProjects, 'Como participante' => $participatingProjects] as $role => $roleProjects)
        @if($roleProjects->isNotEmpty())
            <h3 class="researcher-project-role">{{ $role }}</h3>
            <div class="project-grid">
                @foreach($roleProjects as $project)
                    @include('projects.card', ['heading' => 'h4'])
                @endforeach
            </div>
        @endif
    @endforeach
    @if($responsibleProjects->isEmpty() && $participatingProjects->isEmpty())
        <div class="empty"><i class="bi bi-kanban" aria-hidden="true"></i><h3>Aún no hay proyectos vinculados</h3><p>Los proyectos de este perfil aparecerán aquí.</p></div>
    @endif
</section>
@else
<section>
    <div class="section-heading">
        <h2>Publicaciones <span class="muted">{{ number_format($publications->total()) }}</span></h2>
        @if($publications->total() > 0)<a href="{{ route('publications.index', ['researcher' => $researcher->id]) }}">Explorar y exportar <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>@endif
    </div>
    @forelse($publications as $publication)
        @include('partials.card', ['featured' => false])
    @empty
        <div class="empty"><i class="bi bi-journal-text" aria-hidden="true"></i><h3>No hay publicaciones en esta página</h3><p>Las publicaciones disponibles de este perfil aparecerán aquí.</p></div>
    @endforelse
    <div class="pagination">{{ $publications->links() }}</div>
</section>
@endif
</div>
@endsection
