<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Researcher extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['claim_email_hash', 'avatar_path', 'avatar_disk'];

    protected function casts(): array
    {
        return ['contacts' => 'array', 'degrees' => 'array', 'recognitions' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function publications()
    {
        return $this->belongsToMany(Publication::class)->withPivot('state', 'evidence');
    }

    public function inboxPublications(): BelongsToMany
    {
        return $this->publications()->wherePivotNull('dismissed_at')->wherePivotIn('state', ['pending', 'confirmed']);
    }

    public function pendingPublications(): BelongsToMany
    {
        return $this->inboxPublications()->where(fn ($query) => $query
            ->whereIn('status', ['review', 'incomplete'])
            ->orWhere(fn ($query) => $query->where('status', 'published')->where('publication_researcher.state', 'pending')));
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function researchLines(): BelongsToMany
    {
        return $this->belongsToMany(ResearchLine::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(ResearchProject::class, 'project_participants')->withPivot('name', 'position');
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/u', $this->name))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('');
    }

    /** @return list<string> */
    public function publicEmails(): array
    {
        $institutionalEmail = $this->user?->email;
        if (! $institutionalEmail || ! in_array(explode('@', $institutionalEmail)[1] ?? '', config('catalogue.domains'), true)) {
            $institutionalEmail = null;
        }

        return collect([$this->contacts['contact_email'] ?? $institutionalEmail, ...($this->contacts['email_aliases'] ?? [])])
            ->filter()
            ->unique(fn (string $email) => mb_strtolower($email))
            ->values()
            ->all();
    }

    public static function emailHash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), config('app.key'));
    }
}
