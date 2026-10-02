<?php

namespace App\Models;

use Database\Factories\RfidLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rfid_card_id', 'patient_id', 'scanned_by', 'uid', 'result', 'scanned_at'])]
class RfidLog extends Model
{
    /** @use HasFactory<RfidLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(RfidCard::class, 'rfid_card_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
