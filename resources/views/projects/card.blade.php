<article class="project-card">
    <{{ $heading ?? 'h2' }} class="project-card-title"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></{{ $heading ?? 'h2' }}>
    @if($project->responsibleDisplayName())
        <p><span class="muted">Responsable:</span>
            @if($project->responsible)<a href="{{ route('researchers.show', $project->responsible) }}">{{ $project->responsibleDisplayName() }}</a>
            @else{{ $project->responsibleDisplayName() }}@endif
        </p>
    @endif
    @if($project->funding_institution || $project->funding_call)<p class="muted">{{ implode(' · ', array_filter([$project->funding_institution, $project->funding_call])) }}</p>@endif
    @if($project->description)<p class="muted">{{ Str::limit($project->description, 220) }}</p>@endif
    <a class="text-link" href="{{ route('projects.show', $project) }}">Ver proyecto <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
</article>
