<?php

namespace App\Models;

use Database\Factories\RfidCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['patient_id', 'uid', 'is_active', 'valid_from', 'valid_until'])]
class RfidCard extends Model
{
    /** @use HasFactory<RfidCardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function validityStatus(?Carbon $now = null): string
    {
        if (! $this->is_active) {
            return 'disabled';
        }

        $now ??= now(config('clinic.timezone'));
        $validFrom = $this->valid_from === null ? null : $this->clinicDateTime($this->valid_from);
        $validUntil = $this->valid_until === null ? null : $this->clinicDateTime($this->valid_until);

        if ($validFrom !== null && $now->lessThan($validFrom)) {
            return 'not_yet_valid';
        }

        if ($validUntil !== null && $now->greaterThanOrEqualTo($validUntil)) {
            return 'expired';
        }

        return 'active';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RfidLog::class);
    }

    private function clinicDateTime(Carbon $dateTime): Carbon
    {
        return Carbon::createFromFormat(
            '!Y-m-d H:i:s',
            $dateTime->format('Y-m-d H:i:s'),
            config('clinic.timezone'),
        );
    }
}
