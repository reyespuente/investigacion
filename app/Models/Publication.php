<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (Publication $publication) {
            $publication->author_search = mb_strtolower(implode(' ', array_column($publication->authors ?? [], 'nombre')));
            $publication->tag_search = mb_strtolower(implode(' ', $publication->metadata['bibliografia']['palabras_clave'] ?? []));
        });
    }

    protected function casts(): array
    {
        return ['authors' => 'array', 'metadata' => 'array', 'user_modified_at' => 'datetime'];
    }

    public function researchers()
    {
        return $this->belongsToMany(Researcher::class)->withPivot('state', 'evidence');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    public function label(): string
    {
        return config('catalogue.types')[$this->type] ?? $this->type;
    }

    public function reviewGaps(): array
    {
        return array_keys(array_filter(['título' => ! $this->title, 'año' => ! $this->year, 'autores' => ! $this->authors, 'revista' => ! $this->venue, 'enlace' => ! $this->external_url]));
    }

    public function batchReviewReady(): bool
    {
        return in_array($this->status, ['review', 'incomplete'], true) && ! $this->reviewGaps() && $this->researchers->contains(fn ($person) => $person->pivot->state === 'confirmed') && ! $this->researchers->contains(fn ($person) => $person->pivot->state === 'pending');
    }

    public function csl(): array
    {
        $c = $this->metadata['csl'] ?? [];
        $parts = $c['issued']['date-parts'] ?? ($this->year ? [[(int) $this->year]] : null);

        return array_filter(array_replace($c, ['id' => (string) $this->id, 'title' => $this->title, 'author' => array_map(fn ($a) => ! empty($a['nombre_estructurado']) ? $a['nombre_estructurado'] : ['literal' => $a['nombre']], $this->authors), 'issued' => $parts ? ['date-parts' => $parts] : null, 'container-title' => $this->venue, 'DOI' => $this->doi, 'URL' => $this->external_url, 'type' => ['article' => 'article-journal', 'review' => 'article-journal', 'conference-paper' => 'paper-conference', 'book-chapter' => 'chapter', 'book' => 'book', 'dissertation' => 'thesis', 'report' => 'report'][$this->type] ?? 'article']), fn ($v) => $v !== null);
    }
}
