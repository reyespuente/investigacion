<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Researcher;
use App\Services\Audit;
use App\Services\ImageUpload;
use App\Services\PublicationReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function index(Request $r)
    {
        $researcher = $r->user()->researcher;
        $r->validate(['status' => 'nullable|in:pending,published,all']);
        $counts = $researcher ? ['pending' => $researcher->publications()->wherePivotIn('state', ['pending', 'confirmed'])->where(fn ($q) => $q->whereIn('status', ['review', 'incomplete'])->orWhere(fn ($q) => $q->where('status', 'published')->where('publication_researcher.state', 'pending')))->count(), 'published' => $researcher->publications()->published()->wherePivot('state', 'confirmed')->count()] : [];
        $query = $researcher ? $researcher->publications()->wherePivotIn('state', ['pending', 'confirmed']) : null;
        if ($query && $r->input('status', 'pending') === 'pending') {
            $query->where(fn ($q) => $q->whereIn('status', ['review', 'incomplete'])->orWhere(fn ($q) => $q->where('status', 'published')->where('publication_researcher.state', 'pending')));
        }
        if ($query && $r->status === 'published') {
            $query->published();
        }
        $publications = $query?->orderByDesc('year')->paginate(15)->withQueryString();

        return view('account.index', compact('researcher', 'publications', 'counts'));
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

    public function review(Request $r, Publication $publication)
    {
        $profile = $r->user()->researcher;
        abort_unless($profile && in_array($publication->status, ['review', 'incomplete', 'published'], true) && $publication->researchers()->whereKey($profile->id)->wherePivotIn('state', ['pending', 'confirmed'])->exists(), 403);

        return view('account.review', compact('publication'));
    }

    public function confirm(Request $r, Publication $publication, PublicationReview $review)
    {
        $review->decide($r, $publication, false);
        $profile = $r->user()->researcher;
        if ($r->input('intent') === 'next') {
            $next = $profile->publications()->whereIn('status', ['review', 'incomplete'])->wherePivotIn('state', ['pending', 'confirmed'])->orderBy('id')->first();
            if ($next) {
                return redirect()->route('account.review', $next)->with('success', 'Publicación confirmada. Continúa con la siguiente.');
            }
        }

        return redirect()->route('account', ['status' => 'pending'])->with('success','Autoría confirmada y publicación disponible.');
    }
}
