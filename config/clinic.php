<?php

return [
    'name' => 'Doc Brigs Dental Care',
    'address' => env('CLINIC_ADDRESS', 'Clinic address not configured'),
    'phone' => env('CLINIC_PHONE', 'Clinic contact not configured'),
    'currency_symbol' => env('CLINIC_CURRENCY_SYMBOL', '₱'),
    'timezone' => env('CLINIC_TIMEZONE', 'Asia/Manila'),
];
