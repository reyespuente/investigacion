<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;

class PublicationPolicy
{
    public function update(User $u, Publication $p): bool
    {
        return $u->active && ($u->role === 'admin' || ($u->researcher && ($p->submitted_by === $u->id || $p->researchers()->whereKey($u->researcher->id)->wherePivot('state', 'confirmed')->exists())));
    }
}
