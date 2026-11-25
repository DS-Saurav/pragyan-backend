<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeReceiptItem extends Model
{
    protected $fillable = ['fee_receipt_id', 'fee_head_id', 'description', 'period', 'amount'];

    protected $casts = ['amount' => 'float'];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class, 'fee_receipt_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }
}
