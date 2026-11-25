<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeBillIdentity extends Model
{
    protected $fillable = [
        'student_id', 'academic_year', 'fee_head_id', 'period',
        'fee_receipt_id', 'fee_receipt_item_id',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class, 'fee_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FeeReceiptItem::class, 'fee_receipt_item_id');
    }
}
