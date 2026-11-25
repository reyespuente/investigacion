<?php

namespace App\Policies;

use App\Models\ResearchProject;
use App\Models\User;

class ResearchProjectPolicy
{
    public function create(User $user): bool
    {
        return $user->active && ! $user->must_change_password && $user->role === 'admin';
    }

    public function update(User $user, ResearchProject $project): bool
    {
        return $this->create($user) || ($user->active && ! $user->must_change_password
            && $project->responsible()->where('user_id', $user->id)->exists());
    }

    public function delete(User $user): bool
    {
        return $this->create($user);
    }
}
