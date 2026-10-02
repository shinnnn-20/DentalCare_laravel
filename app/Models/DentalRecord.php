<?php

namespace App\Models;

use Database\Factories\DentalRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'appointment_id', 'created_by', 'diagnosis', 'treatment', 'prescription', 'notes', 'follow_up_date'])]
class DentalRecord extends Model
{
    /** @use HasFactory<DentalRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['follow_up_date' => 'date'];
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
}
