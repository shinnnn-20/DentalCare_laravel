<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $duration_minutes
 * @property string $price
 * @property bool $is_active
 */
#[Fillable(['name', 'category', 'description', 'price', 'price_display', 'requires_price_confirmation', 'duration_minutes', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'requires_price_confirmation' => 'boolean',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scheduledAppointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_service')->withTimestamps();
    }
}
