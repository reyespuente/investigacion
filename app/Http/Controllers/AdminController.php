<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Researcher;
use App\Models\User;
use App\Services\Audit;
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

    public function index(Request $r)
    {
        $this->admin();
        $r->validate(['status' => 'nullable|in:review,incomplete,rejected,published', 'q' => 'nullable|string|max:200']);
        $q = Publication::query()->where('status', $r->query('status', 'review'));
        if ($r->filled('q')) {
            $q->where('search_text', 'like', '%'.mb_strtolower($r->q).'%');
        }

return view('admin.index', ['publications' => $q->orderBy('id')->paginate(20)->withQueryString(), 'counts' => Publication::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'), 'users' => User::orderByDesc('id')->paginate(20, ['*'], 'users_page'), 'researchers' => Researcher::whereNull('user_id')->orderBy('name')->get()]);
    }

    public function review(Request $r, Publication $publication)
    {
        $this->admin();
        $d = $r->validate(['decision' => 'required|in:published,rejected,review', 'reason' => 'required|string|max:2000']);
        if ($d['decision'] === 'published') {
            abort_unless($publication->title && $publication->authors && $publication->year && $publication->external_url && $publication->venue, 422, 'Completa los datos esenciales antes de publicar.');
        }$before = $publication->toArray();
        $publication->update(['status' => $d['decision'], 'user_modified_at' => now(), 'revision' => $publication->revision + 1]);
        Audit::record('publication', $publication->id, 'admin_review', $before, $publication->toArray(), $d['reason']);

        return back()->with('success', 'Decisión guardada.');
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
        $before = $publication->researchers()->whereKey($d['researcher_id'])->first()?->pivot?->toArray();
        $publication->researchers()->syncWithoutDetaching([$d['researcher_id'] => ['state' => $d['state'], 'evidence' => 'admin_review']]);
        Audit::record('publication', $publication->id, 'attribution_review', $before, ['researcher_id' => $d['researcher_id'], 'state' => $d['state']], $d['reason']);

        return back()->with('success','Atribución actualizada con su motivo de revisión.');
    }
}
