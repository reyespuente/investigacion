<article class="project-card">
    <h2><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></h2>
    <p class="muted">{{ Str::limit($project->description, 220) }}</p>
    @if($project->relationLoaded('researchLines'))
    <div class="tags">@foreach($project->researchLines as $line)<a href="{{ route('lines.show', $line) }}">{{ $line->label() }}</a>@endforeach</div>
    @endif
    <a class="text-link" href="{{ route('projects.show', $project) }}">Ver proyecto <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
</article>
