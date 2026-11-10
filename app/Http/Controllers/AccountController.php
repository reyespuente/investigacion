<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Researcher;
use App\Services\Audit;
use App\Services\ImageUpload;
use App\Services\PublicationReview;
use App\Services\ResearcherClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    public function index(Request $r, ResearcherClaim $claim)
    {
        $researcher = $r->user()->researcher;
        $r->validate(['status' => 'nullable|in:pending,published,all']);
        $suggestedProfile = null;
        if (! $researcher && ($hash = $claim->rosterHash($r->user()))) {
            $suggestedProfile = Researcher::whereNull('user_id')->where('claim_email_hash', $hash)->first();
        }
        $counts = $researcher ? ['pending' => $researcher->publications()->wherePivotIn('state', ['pending', 'confirmed'])->where(fn ($q) => $q->whereIn('status', ['review', 'incomplete'])->orWhere(fn ($q) => $q->where('status', 'published')->where('publication_researcher.state', 'pending')))->count(), 'published' => $researcher->publications()->published()->wherePivot('state', 'confirmed')->count()] : [];
        $query = $researcher ? $researcher->publications()->wherePivotIn('state', ['pending', 'confirmed']) : null;
        $status = $r->input('status') ?: (! empty($counts['pending']) ? 'pending' : 'published');
        if ($query && $status === 'pending') {
            $query->where(fn ($q) => $q->whereIn('status', ['review', 'incomplete'])->orWhere(fn ($q) => $q->where('status', 'published')->where('publication_researcher.state', 'pending')));
        }
        if ($query && $status === 'published') {
            $query->published()->wherePivot('state', 'confirmed');
        }
        $publications = $query?->orderByDesc('year')->paginate(15)->withQueryString();

        return view('account.index', compact('researcher', 'publications', 'counts', 'status', 'suggestedProfile'));
    }

    public function claim(Request $r, Researcher $researcher, ResearcherClaim $claim)
    {
        $claim->link($r->user(), $researcher);

        return redirect()->route('editor.create')->with('success', 'Perfil vinculado. Ya puedes agregar artículos.');
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
        $d = $r->validate(['name' => 'required|string|max:500', 'bio' => 'nullable|string|max:4000', 'department' => 'nullable|string|max:250', 'website' => 'nullable|url:http,https|max:2048', 'phone' => 'nullable|string|max:50', 'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072|dimensions:max_width=4096,max_height=4096']);
        $emails = $this->validatePublicEmails($r);
        $fields = collect($d)->only(['name', 'bio', 'department'])->all();
        if ($r->hasFile('avatar')) {
            $fields['avatar_path'] = app(ImageUpload::class)->store($r->file('avatar'), 'avatars');
            $fields['avatar_disk'] = config('filesystems.default');
        }
        DB::transaction(function () use ($r, $profile, $fields, $emails, $d) {
            $profile = Researcher::lockForUpdate()->findOrFail($profile->id);
            abort_unless($profile->user_id === $r->user()->id, 403);
            $before = $profile->toArray();
            $fields['contacts'] = array_filter(array_replace($profile->contacts ?? [], $emails, [
                'website' => $d['website'] ?? null,
                'phone' => $d['phone'] ?? null,
            ]));
            $profile->update($fields);
            Audit::record('researcher', $profile->id, 'updated', $before, $profile->toArray());
        });

        return back()->with('success', 'Perfil actualizado. Los datos de contacto que escribiste serán públicos.');
    }

    public function editPublicEmails(Request $r, Researcher $researcher)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return view('admin.researcher-emails', compact('researcher'));
    }

    public function updatePublicEmails(Request $r, Researcher $researcher)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $emails = $this->validatePublicEmails($r);

        DB::transaction(function () use ($researcher, $emails) {
            $profile = Researcher::lockForUpdate()->findOrFail($researcher->id);
            $before = $profile->contacts ?? [];
            $profile->update(['contacts' => array_filter(array_replace($before, $emails))]);
            Audit::record('researcher', $profile->id, 'public_emails_updated', $before, $profile->contacts);
        });

        return redirect()->route('researchers.show', $researcher)->with('success', 'Correos públicos actualizados.');
    }

    private function validatePublicEmails(Request $request): array
    {
        $data = $request->validate([
            'contact_email' => 'nullable|email|max:254',
            'email_aliases' => 'nullable|string|max:6000',
        ]);
        $emails = [];
        if (array_key_exists('contact_email', $data)) {
            $emails['contact_email'] = $data['contact_email'];
        }
        if (array_key_exists('email_aliases', $data)) {
            $aliases = collect(preg_split('/[\r\n,]+/', $data['email_aliases'] ?? ''))
                ->map(fn (string $email) => trim($email))
                ->filter()
                ->unique(fn (string $email) => mb_strtolower($email))
                ->values()
                ->all();
            Validator::make(
                ['email_aliases' => $aliases],
                ['email_aliases' => 'array|max:20', 'email_aliases.*' => 'email|max:254'],
                ['email_aliases.max' => 'Puedes guardar hasta 20 alias de correo.', 'email_aliases.*.email' => 'Cada alias debe ser un correo válido.']
            )->validate();
            $emails['email_aliases'] = $aliases;
        }

        return $emails;
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

        return redirect()->route('account', ['status' => 'pending'])->with('success', 'Autoría confirmada y publicación disponible.');
    }
}
