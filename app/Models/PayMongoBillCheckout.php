<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bill_id',
    'reference_number',
    'checkout_session_id',
    'checkout_url',
    'amount_centavos',
    'status',
    'paid_payment_id',
    'paid_at',
])]
class PayMongoBillCheckout extends Model
{
    protected $table = 'paymongo_bill_checkouts';

    protected function casts(): array
    {
        return [
            'amount_centavos' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
