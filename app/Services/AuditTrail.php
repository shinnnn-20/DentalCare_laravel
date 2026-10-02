<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditTrail
{
    public function record(User $user, string $action, string $module, ?Model $record = null, ?string $description = null): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'role' => $user->role,
            'action' => $action,
            'module' => $module,
            'record_type' => $record === null ? null : class_basename($record),
            'record_id' => $record?->getKey(),
            'description' => $description,
        ]);
    }
}
