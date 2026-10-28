<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path', 'disk'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function researcher()
    {
        return $this->belongsTo(Researcher::class);
    }

    public function visibleTo(?User $user): bool
    {
        return ($this->is_public && (! $this->publication_id || $this->publication?->status === 'published')) || ($user && $user->active && ($user->role === 'admin' || $this->researcher->user_id === $user->id));
    }
}
