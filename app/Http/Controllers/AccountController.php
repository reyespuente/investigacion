<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Researcher;
use App\Services\Audit;
use App\Services\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function index(Request $r)
    {
        $researcher = $r->user()->researcher;
        $publications = $researcher ? $researcher->publications()->orderByDesc('year')->paginate(15) : null;

        return view('account.index', compact('researcher', 'publications'));
    }

    public function claim(Request $r, Researcher $researcher)
    {
        DB::transaction(function () use ($r, $researcher) {
            $u = $r->user();
            $profile = Researcher::lockForUpdate()->findOrFail($researcher->id);
            abort_unless($u->google_id && $u->email_verified_at && in_array(explode('@', $u->email)[1], config('catalogue.domains'), true) && $profile->claim_email_hash && hash_equals($profile->claim_email_hash, Researcher::emailHash($u->email)), 403, 'Tu cuenta no coincide con el correo institucional de este perfil. Contacta al administrador si necesitas corregir el padrón.');
            abort_if($profile->user_id || $u->researcher()->exists(), 409, 'El perfil ya está reclamado o tu cuenta ya tiene uno asociado.');
            $profile->update(['user_id' => $u->id]);
            Audit::record('researcher', $profile->id, 'claimed', null, ['user_id' => $u->id]);
        });

        return redirect()->route('account')->with('success', 'Perfil reclamado. Ya puedes actualizar tus datos y publicaciones.');
    }

    public function edit(Request $r)
    {
        $researcher = $r->user()->researcher;
        abort_unless($researcher, 403);

        return view('account.profile', compact('researcher'));
    }

    public function update(Request $r)
    {
        $profile = $r->user()->researcher;
        abort_unless($profile, 403);
        $d = $r->validate(['name' => 'required|string|max:500', 'bio' => 'nullable|string|max:4000', 'department' => 'nullable|string|max:250', 'contact_email' => 'nullable|email|max:254', 'website' => 'nullable|url:http,https|max:2048', 'phone' => 'nullable|string|max:50', 'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072|dimensions:max_width=4096,max_height=4096']);
        $before = $profile->toArray();
        $fields = collect($d)->only(['name', 'bio', 'department'])->all();
        $fields['contacts'] = collect($d)->only(['contact_email', 'website', 'phone'])->filter()->all();
        if ($r->hasFile('avatar')) {
            $fields['avatar_path'] = app(ImageUpload::class)->store($r->file('avatar'), 'avatars');
            $fields['avatar_disk'] = config('filesystems.default');
        }
        $profile->update($fields);
        Audit::record('researcher', $profile->id, 'updated', $before, $profile->toArray());

        return back()->with('success', 'Perfil actualizado. Los datos de contacto que escribiste serán públicos.');
    }

    public function avatar(Researcher $researcher)
    {
        abort_unless($researcher->avatar_path, 404);

        return Storage::disk($researcher->avatar_disk ?: config('filesystems.default'))->response($researcher->avatar_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function confirm(Request $r, Publication $publication)
    {
        $profile = $r->user()->researcher;
        abort_unless($profile && $publication->researchers()->whereKey($profile->id)->wherePivot('state', 'pending')->exists(), 403);
        $r->validate(['authorship' => 'accepted']);
        abort_unless($publication->title && $publication->year && $publication->authors && $publication->external_url && $publication->venue, 422, 'La referencia está incompleta. El administrador debe completarla antes de publicarla.');
        DB::transaction(function () use ($profile, $publication) {
            $publication->researchers()->updateExistingPivot($profile->id, ['state' => 'confirmed', 'evidence' => 'researcher_declaration']);
            $publication->update(['status' => 'published', 'user_modified_at' => now()]);
            Audit::record('publication', $publication->id, 'authorship_confirmed', null, ['researcher_id' => $profile->id], 'Declaración del investigador autenticado');
        });

        return back()->with('success','Autoría confirmada por ti. La ficha ya es pública.');
    }
}
