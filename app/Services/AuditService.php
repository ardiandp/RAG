<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * Catat aksi penting ke audit log.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?User $user = null, array $context = [], array $metadata = [], ?Request $request = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'context' => $context === [] ? null : $context,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    public function contextFrom(array|object $source): array
    {
        $attributes = is_array($source) ? $source : $source->getAttributes();

        return collect($attributes)
            ->only(['id', 'conversation_id', 'agent_id', 'run_id', 'status', 'steps', 'tool_name', 'document_id'])
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => is_scalar($value) ? $value : (string) json_encode($value))
            ->all();
    }
}
