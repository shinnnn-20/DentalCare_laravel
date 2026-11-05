<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property int|null $base_service_id
 * @property string $name
 * @property int $duration_minutes
 * @property string $price
 * @property bool $is_active
 */
#[Fillable(['name', 'category', 'description', 'price', 'price_display', 'requires_price_confirmation', 'duration_minutes', 'is_active', 'branch_id', 'base_service_id'])]
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function baseService(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_service_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        $servicesTable = $this->getTable();

        return $query->where(function (Builder $query) use ($branchId, $servicesTable): void {
            $query->where($servicesTable.'.branch_id', $branchId)
                ->orWhere(function (Builder $query) use ($branchId, $servicesTable): void {
                    $query->whereNull($servicesTable.'.branch_id')
                        ->whereNotExists(function ($overridesQuery) use ($branchId, $servicesTable): void {
                            $overridesQuery->selectRaw('1')
                                ->from($servicesTable.' as branch_service_overrides')
                                ->where('branch_service_overrides.branch_id', $branchId)
                                ->where(function ($overridesQuery) use ($servicesTable): void {
                                    $overridesQuery->whereColumn('branch_service_overrides.base_service_id', $servicesTable.'.id')
                                        ->orWhereColumn('branch_service_overrides.name', $servicesTable.'.name');
                                });
                        });
                });
        });
    }

    public function scheduledAppointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_service')->withTimestamps();
    }
}
