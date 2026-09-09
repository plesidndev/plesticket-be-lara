<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records who did what, to which record.
 *
 * Auditing must never break the action it is auditing: a failure here is swallowed and reported to
 * the log, because losing the audit row is bad but refusing a legitimate refund because the audit
 * table is full is worse.
 */
class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $changes  Before/after pairs, secrets already removed.
     */
    public function record(string $action, string $subjectType, ?string $subjectId = null, ?string $subjectLabel = null, ?array $changes = null): void
    {
        $actor = auth('api')->user();

        if (! $actor instanceof User) {
            return;
        }

        try {
            AuditLog::create([
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_role' => $actor->role->value,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_label' => $subjectLabel,
                'changes' => $this->redact($changes),
                'ip' => $this->request->ip(),
                'user_agent' => substr((string) $this->request->userAgent(), 0, 255) ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Never let a credential reach the audit table. The point of the log is accountability, not
     * a second copy of every secret that passed through the console.
     *
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>|null
     */
    private function redact(?array $changes): ?array
    {
        if ($changes === null) {
            return null;
        }

        foreach (['password', 'password_confirmation', 'token', 'qr_string'] as $secret) {
            if (array_key_exists($secret, $changes)) {
                $changes[$secret] = '[redacted]';
            }
        }

        return $changes;
    }
}
