<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Audit
{
    public static function record(string $entity, int $id, string $action, ?array $before = null, ?array $after = null, ?string $reason = null): void
    {
        DB::table('audit_events')->insert(['user_id' => auth()->id(), 'entity' => $entity, 'entity_id' => $id, 'action' => $action, 'before' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null, 'after' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
    }
}
