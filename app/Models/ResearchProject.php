<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResearchProject extends Model
{
    protected $guarded = ['id'];

    public function researchLines(): BelongsToMany
    {
        return $this->belongsToMany(ResearchLine::class);
    }

    public function participants(): Collection
    {
        return DB::table('project_participants')->leftJoin('researchers', 'researchers.id', '=', 'project_participants.researcher_id')
            ->where('research_project_id', $this->id)->orderBy('position')
            ->get(['project_participants.*', 'researchers.name as profile_name', 'researchers.department']);
    }
}
