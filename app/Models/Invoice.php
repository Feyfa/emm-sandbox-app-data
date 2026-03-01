<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'payment_method_id',
        'invoice_number',
        'type',
        'amount',
        'currency',
        'status',
        'provider_transaction_id',
        'description',
        'metadata',
        'paid_at',
        'webhook_received_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
