<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoogleIdentity
{
    public function resolve($identity): User
    {
        $email = mb_strtolower(trim($identity->getEmail() ?? ''));
        $raw = $identity->user;
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL) && in_array(explode('@', $email)[1], config('catalogue.domains'), true) && (($raw['email_verified'] ?? $raw['verified_email'] ?? false) === true), 403, 'Utiliza una cuenta verificada de un dominio institucional permitido.');
        $id = (string) $identity->getId();
        abort_unless($id !== '', 403);
        $byId = User::where('google_id', $id)->first();
        if ($byId) {
            abort_unless($byId->email === $email && $byId->active, 403, 'La cuenta cambió o está desactivada. Contacta al administrador.');

            return $byId;
        }
        $u = User::firstOrNew(['email' => $email]);
        abort_unless(! $u->exists || (! $u->google_id && $u->active), 403);
        if (! $u->exists) {
            $u->fill(['name' => $identity->getName() ?: $email, 'password' => Hash::make(Str::random(64)), 'role' => 'member']);
        }
        $u->fill(['google_id' => $id, 'email_verified_at' => now()]);
        $u->save();

        return $u;
    }
}
