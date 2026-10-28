<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Researcher extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['claim_email_hash', 'avatar_path', 'avatar_disk'];

    protected function casts(): array
    {
        return ['contacts' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function publications()
    {
        return $this->belongsToMany(Publication::class)->withPivot('state', 'evidence');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/u', $this->name))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('');
    }

    public static function emailHash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), config('app.key'));
    }
}
