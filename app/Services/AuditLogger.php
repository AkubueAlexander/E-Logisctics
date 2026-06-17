<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Log structural security events to a dedicated channel.
     */
    public static function log(string $event, ?User $user = null, array $context = []): void
    {
        $payload = array_merge([
            'event' => $event,
            'user_id' => $user?->id ?? 'anonymous',
            'role' => $user?->system_role?->value ?? 'unauthenticated',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ], $context);

        // Routes to your production logging stack via a dedicated channel
        Log::channel('security')->info(json_encode($payload));
    }
}
