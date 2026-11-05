<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AuditTrail
{
    public function record(
        User $user,
        string $action,
        string $module,
        ?Model $record,
        string $description,
    ): void {
        $description = trim($description);

        if ($description === '') {
            throw new InvalidArgumentException('Audit log descriptions must explain the recorded activity.');
        }

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
