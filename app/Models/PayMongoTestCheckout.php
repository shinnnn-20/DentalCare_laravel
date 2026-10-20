<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference_number', 'checkout_session_id', 'status', 'paid_payment_id', 'paid_at'])]
class PayMongoTestCheckout extends Model
{
    protected $table = 'paymongo_test_checkouts';

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }
}
