<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function edit()
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        return view('admin.settings');
    }

    public function update(Request $r, ImageUpload $images)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $rules = ['institution' => 'required|string|max:250', 'short_name' => 'required|string|max:80', 'portal_name' => 'required|string|max:150', 'network_name' => 'nullable|string|max:250', 'primary_color' => ['required', 'regex:/^#[a-f0-9]{6}$/i'], 'accent_color' => ['required', 'regex:/^#[a-f0-9]{6}$/i']];
        foreach (['institution_logo', 'network_logo'] as $k) {
            $rules[$k] = 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072|dimensions:max_width=4096,max_height=4096';
        }$data = $r->validate($rules);
        foreach (['primary_color', 'accent_color'] as $key) {
            $rgb = array_map(fn ($c) => hexdec($c) / 255, str_split(substr($data[$key], 1), 2));
            $linear = array_map(fn ($c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $rgb);
            $contrast = 1.05 / (0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2] + 0.05);
            if ($contrast < 4.5) {
                throw ValidationException::withMessages([$key => 'Elige un color más oscuro que mantenga contraste legible sobre blanco.']);
            }
        }$before = DB::table('site_settings')->where('key', 'not like', 'runtime.%')->pluck('value', 'key')->all();
        $values = collect($data)->except(['institution_logo', 'network_logo'])->all();
        foreach (['institution_logo', 'network_logo'] as $k) {
            if ($r->hasFile($k)) {
                $values[$k.'_path'] = $images->store($r->file($k), 'branding', 1200);
                $values[$k.'_disk'] = config('filesystems.default');
            }
        }$values['logo_version'] = (string) time();
        DB::transaction(function () use ($values, $before) {
            foreach ($values as $key => $value) {
                DB::table('site_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }Audit::record('site_settings', 0, 'branding_updated', $before, $values);
        });
        Cache::forget('institution_branding');

        return back()->with('success', 'Identidad actualizada. Los cambios ya se aplican al portal.');
    }

    public function logo(string $kind)
    {
        abort_unless(in_array($kind, ['institution', 'network'], true), 404);
        $path = config('catalogue.'.$kind.'_logo_path');
        abort_unless($path, 404);

        return Storage::disk(config('catalogue.'.$kind.'_logo_disk') ?: config('filesystems.default'))->response($path,null,['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=3600']);
    }
}
