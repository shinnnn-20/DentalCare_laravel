<?php

namespace App\Models;

use Database\Factories\DentalRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $appointment_id
 * @property int|null $created_by
 * @property int|null $treating_dentist_id
 * @property string|null $follow_up_time
 */
#[Fillable(['patient_id', 'appointment_id', 'created_by', 'treating_dentist_id', 'diagnosis', 'treatment', 'prescription', 'notes', 'follow_up_date', 'follow_up_time'])]
class DentalRecord extends Model
{
    /** @use HasFactory<DentalRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['follow_up_date' => 'date'];
    }

    public function formattedFollowUpTime(): ?string
    {
        return $this->follow_up_time === null
            ? null
            : Carbon::parse($this->follow_up_time)->format('g:i A');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function treatingDentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treating_dentist_id');
    }
}
