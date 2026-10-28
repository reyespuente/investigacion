<?php

namespace App\Http\Controllers;

use App\Services\GoogleIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        return view('auth.login');
    }

    public function authenticate(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $d['email'] = mb_strtolower(trim($d['email']));
        $d['active'] = true;
        if (! Auth::attempt($d, $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'No fue posible iniciar sesión con esos datos.'])->onlyInput('email');
        }$r->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function google()
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'El acceso institucional todavía no está disponible.');

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->with(['hd' => (count(config('catalogue.domains')) === 1 ? config('catalogue.domains')[0] : '*'), 'prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $r, GoogleIdentity $resolver)
    {
        try {
            $identity = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['google' => 'No se completó el acceso de Google. Intenta nuevamente.']);
        }$u = $resolver->resolve($identity);
        Auth::login($u);
        $r->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function change()
    {
        return view('auth.password', ['mode' => 'change']);
    }

    public function update(Request $r)
    {
        $d = $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()]]);
        $r->user()->update(['password' => Hash::make($d['password']), 'must_change_password' => false, 'remember_token' => Str::random(60)]);
        $r->session()->regenerate();

        return redirect()->route('account')->with('success', 'Contraseña actualizada.');
    }

    public function forgot()
    {
        return view('auth.password', ['mode' => 'forgot']);
    }

    public function sendReset(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink(['email' => mb_strtolower(trim($r->email)), 'active' => true]);

        return back()->with('success', 'Si hay una cuenta habilitada con ese correo, recibirás un enlace para restablecer la contraseña.');
    }

    public function reset(Request $r, string $token)
    {
        return view('auth.password', ['mode' => 'reset', 'token' => $token, 'email' => $r->query('email')]);
    }

    public function storeReset(Request $r)
    {
        $d = $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()]]);
        $status = Password::reset($d, function ($u, $password) {
            $u->forceFill(['password' => Hash::make($password), 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PASSWORD_RESET ? redirect()->route('login')->with('success', 'Contraseña restablecida.') : back()->withErrors(['email' => 'El enlace no es válido o ya venció.']);
    }
}
