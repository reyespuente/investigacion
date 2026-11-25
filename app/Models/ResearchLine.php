<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ResearchLine extends Model
{
    protected $guarded = ['id'];

    public function label(): string
    {
        return $this->program ? "{$this->name} · {$this->program}" : $this->name;
    }

    public function researchers(): BelongsToMany
    {
        return $this->belongsToMany(Researcher::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(ResearchProject::class);
    }
}
