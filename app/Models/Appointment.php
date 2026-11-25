<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $patient_id
 * @property int $service_id
 * @property int|null $branch_id
 * @property int|null $created_by
 * @property string|null $payment_method
 * @property string|null $deposit_amount
 * @property string|null $payment_proof_path
 * @property string|null $payment_proof_status
 * @property int|null $payment_proof_reviewed_by
 * @property Carbon|null $payment_proof_reviewed_at
 * @property int|null $deposit_payment_id
 * @property string $type
 * @property Carbon $starts_at
 * @property int $duration_minutes
 * @property string $status
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'patient_id',
    'service_id',
    'branch_id',
    'created_by',
    'payment_method',
    'deposit_amount',
    'payment_proof_path',
    'payment_proof_status',
    'payment_proof_reviewed_by',
    'payment_proof_reviewed_at',
    'deposit_payment_id',
    'type',
    'starts_at',
    'duration_minutes',
    'status',
    'notes',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'duration_minutes' => 'integer',
            'deposit_amount' => 'decimal:2',
            'payment_proof_reviewed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'appointment_service')->withTimestamps();
    }

    public function treatmentNames(): string
    {
        $services = $this->treatmentServices();

        return $services->isNotEmpty() ? $services->pluck('name')->join(', ') : ($this->service?->name ?? 'Dental services');
    }

    public function estimatedTotal(): float
    {
        return (float) $this->treatmentServices()->sum('price');
    }

    private function treatmentServices(): Collection
    {
        $services = $this->services;

        if ($services->isNotEmpty()) {
            return $services;
        }

        return $this->service ? collect([$this->service]) : $services;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dentalRecord(): HasOne
    {
        return $this->hasOne(DentalRecord::class);
    }

    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class);
    }

    public function queueEntry(): HasOne
    {
        return $this->hasOne(QueueEntry::class);
    }

    public function paymentProofReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_proof_reviewed_by');
    }

    public function depositPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'deposit_payment_id');
    }
}
