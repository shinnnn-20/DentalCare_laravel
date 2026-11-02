<?php

namespace App\Models;

use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $appointment_id
 * @property string $bill_number
 * @property string $discount_type
 * @property string $discount_value
 * @property string $discount
 * @property string $total
 * @property string $payment_status
 */
#[Fillable(['bill_number', 'patient_id', 'appointment_id', 'created_by', 'subtotal', 'discount_type', 'discount_value', 'discount', 'total', 'payment_status'])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_type' => 'string',
            'discount_value' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function discountLabel(): string
    {
        if ($this->discount_type !== 'percentage') {
            return 'Discount';
        }

        $percentage = rtrim(rtrim(number_format((float) $this->discount_value, 2, '.', ''), '0'), '.');

        return "Discount ({$percentage}%)";
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payMongoBillCheckouts(): HasMany
    {
        return $this->hasMany(PayMongoBillCheckout::class);
    }
}
