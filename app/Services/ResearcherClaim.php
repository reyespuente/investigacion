<?php

namespace App\Services;

use App\Models\Researcher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearcherClaim
{
    public function rosterHash(User $user): ?string
    {
        $domain = Str::afterLast($user->email, '@');

        if (! $user->active || ! $user->google_id || ! $user->email_verified_at || ! in_array($domain, config('catalogue.domains'), true)) {
            return null;
        }

        return Researcher::emailHash($user->email);
    }

    public function link(User $user, Researcher $researcher): void
    {
        DB::transaction(function () use ($user, $researcher) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $profile = Researcher::lockForUpdate()->findOrFail($researcher->id);
            $hash = $this->rosterHash($user);

            if (! $hash || ! $profile->claim_email_hash || ! hash_equals($profile->claim_email_hash, $hash)) {
                throw ValidationException::withMessages([
                    'profile' => 'Tu cuenta de Google no coincide con el correo institucional de este perfil. Si usaste otra cuenta, cierra sesión e inténtalo con la correcta.',
                ])->redirectTo(route('researchers.show', $profile));
            }

            if ($profile->user_id === $user->id) {
                return;
            }

            if ($profile->user_id || $user->researcher()->exists()) {
                throw ValidationException::withMessages([
                    'profile' => 'Este perfil ya está vinculado o tu cuenta ya tiene un perfil asociado.',
                ])->redirectTo(route('researchers.show', $profile));
            }

            $profile->update(['user_id' => $user->id]);
            Audit::record('researcher', $profile->id, 'claimed', null, ['user_id' => $user->id]);
        }, 3);
    }
}
