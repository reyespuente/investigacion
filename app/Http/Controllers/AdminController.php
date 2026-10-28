<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Researcher;
use App\Models\User;
use App\Services\Audit;
use App\Services\PublicationReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    private function admin()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    private function queue(Request $r)
    {
        $r->validate(['status' => 'nullable|in:review,incomplete,rejected,published', 'q' => 'nullable|string|max:200', 'researcher' => 'nullable|integer|exists:researchers,id', 'ready' => 'nullable|in:1,0,2', 'paper' => 'nullable|integer']);
        $q = Publication::query()->where('status', $r->input('status', 'review'));
        if ($r->filled('q')) {
            $q->where('search_text', 'like', '%'.mb_strtolower($r->q).'%');
        }
        if ($r->filled('researcher')) {
            $q->whereHas('researchers', fn ($query) => $query->where('researchers.id', $r->researcher)->whereIn('publication_researcher.state', ['confirmed', 'pending']));
        }
        if ($r->filled('ready')) {
            $condition = function ($query) {
                $query->whereNotNull('year')->where('title', '<>', '')->whereNotNull('venue')->where('venue', '<>', '')->whereNotNull('external_url')->where('external_url', '<>', '')->where('authors', '<>', '[]');
            };
            if (in_array($r->ready, ['1', '2'], true)) {
                $q->where($condition);
            } else {
                $q->whereNot(fn ($query) => $condition($query));
            }
            if ($r->ready === '2') {
                $q->whereHas('researchers', fn ($query) => $query->where('publication_researcher.state', 'confirmed'))->whereDoesntHave('researchers', fn ($query) => $query->where('publication_researcher.state', 'pending'));
            }
        }

        return $q;
    }

    public function index(Request $r)
    {
        $this->admin();
        $q = $this->queue($r);
        $publications = (clone $q)->with('researchers')->orderBy('id')->paginate(25)->withQueryString();
        $selected = $r->filled('paper') ? (clone $q)->with('researchers')->findOrFail($r->paper) : $publications->first();

        return view('admin.index', ['publications' => $publications, 'selected' => $selected, 'counts' => Publication::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'), 'researchers' => Researcher::orderBy('name')->get()]);
    }

    public function users()
    {
        $this->admin();

        return view('admin.users', ['users' => User::orderByDesc('id')->paginate(20), 'researchers' => Researcher::whereNull('user_id')->orderBy('name')->get()]);
    }

    public function review(Request $r, Publication $publication, PublicationReview $review)
    {
        $this->admin();
        $review->decide($r, $publication, true);
        $filters = $r->only(['status', 'q', 'researcher', 'ready']);
        if ($r->input('intent') === 'next') {
            $q = $this->queue(new Request($filters));
            $next = (clone $q)->where('id', '>', $publication->id)->orderBy('id')->value('id') ?? $q->orderBy('id')->value('id');
            if ($next) {
                $filters['paper'] = $next;
            }
        } else {
            $filters['status'] = $r->decision;
            $filters['paper'] = $publication->id;
        }

        return redirect()->route('admin', $filters)->with('success', 'Decisión guardada con sus autorías.');
    }

    public function batch(Request $r, PublicationReview $review)
    {
        $this->admin();
        $data = $r->validate(['items' => 'required|array|min:1|max:25', 'items.*' => 'required|integer|min:1', 'checked' => 'accepted']);
        DB::transaction(function () use ($data, $r, $review) {
            $items = $data['items'];
            ksort($items, SORT_NUMERIC);
            foreach ($items as $id => $version) {
                abort_unless(ctype_digit((string) $id), 422);
                $paper = Publication::with('researchers')->lockForUpdate()->findOrFail($id);
                abort_unless($paper->batchReviewReady(), 422, 'Una referencia tiene datos incompletos o autorías pendientes. Revísala individualmente.');
                $single = new Request(['version' => $version, 'decision' => 'published', 'reason' => 'Revisión por lote: metadatos y autorías comprobados']);
                $single->setUserResolver(fn () => $r->user());
                $review->decide($single, $paper, true);
            }
        });

        return redirect()->route('admin', $r->only(['status', 'q', 'researcher', 'ready']))->with('success', (count($data['items']) === 1 ? 'Una referencia publicada' : count($data['items']).' referencias publicadas').' con historial individual.');
    }

    public function createUser(Request $r)
    {
        $this->admin();
        $r->merge(['email' => mb_strtolower(trim($r->input('email', '')))]);
        $d = $r->validate(['name' => 'required|string|max:250', 'email' => 'required|email|max:254|unique:users', 'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()], 'role' => 'required|in:admin,member', 'researcher_id' => 'nullable|integer|exists:researchers,id']);
        DB::transaction(function () use ($d) {
            $u = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => Hash::make($d['password']), 'role' => $d['role'], 'must_change_password' => true]);
            if (! empty($d['researcher_id'])) {
                $p = Researcher::lockForUpdate()->findOrFail($d['researcher_id']);
                abort_if($p->user_id, 409);
                $p->update(['user_id' => $u->id]);
                Audit::record('researcher', $p->id, 'admin_assignment', null, ['user_id' => $u->id]);
            }Audit::record('user', $u->id, 'created', null, ['email' => $u->email, 'role' => $u->role]);
        });

        return back()->with('success', 'Cuenta creada. Deberá cambiar su contraseña al entrar.');
    }

    public function toggleUser(Request $r, User $user)
    {
        $this->admin();
        abort_if($user->id === $r->user()->id || $user->email === config('catalogue.admin_email'), 422, 'No puedes desactivar al administrador principal ni tu propia cuenta.');
        $user->update(['active' => ! $user->active, 'remember_token' => Str::random(60)]);
        if (! $user->active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }Audit::record('user', $user->id, 'active_changed', null, ['active' => $user->active]);

        return back()->with('success', 'Acceso actualizado.');
    }

    public function history()
    {
        $this->admin();

        return view('admin.history', ['events' => DB::table('audit_events')->leftJoin('users', 'users.id', '=', 'audit_events.user_id')->select('audit_events.*', 'users.name as actor')->orderByDesc('audit_events.id')->paginate(40)]);
    }

    public function attribution(Request $r, Publication $publication)
    {
        $this->admin();
        $d = $r->validate(['researcher_id' => 'required|integer|exists:researchers,id', 'state' => 'required|in:confirmed,pending,rejected', 'reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($publication, $d) {
            $paper = Publication::lockForUpdate()->findOrFail($publication->id);
            $before = $paper->researchers()->whereKey($d['researcher_id'])->first()?->pivot?->toArray();
            $paper->researchers()->syncWithoutDetaching([$d['researcher_id'] => ['state' => $d['state'], 'evidence' => 'admin_review']]);
            $paper->increment('revision');
            Audit::record('publication', $paper->id, 'attribution_review', $before, ['researcher_id' => $d['researcher_id'], 'state' => $d['state']], $d['reason']);
        });

        return back()->with('success', 'Atribución actualizada con su motivo de revisión.');
    }
}
