<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $patient_number
 * @property Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $address
 * @property string|null $emergency_contact
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'patient_number', 'date_of_birth', 'gender', 'address', 'emergency_contact'])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function dentalRecords(): HasMany
    {
        return $this->hasMany(DentalRecord::class);
    }

    public function rfidCard(): HasOne
    {
        return $this->hasOne(RfidCard::class);
    }
}
