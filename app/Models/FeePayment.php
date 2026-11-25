<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePayment extends Model
{
    protected $fillable = ['fee_receipt_id', 'amount', 'mode', 'reference_no', 'paid_on', 'received_by'];

    protected $casts = [
        'amount' => 'float',
        'paid_on' => 'date',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class, 'fee_receipt_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
