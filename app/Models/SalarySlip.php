<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalarySlip extends Model
{
    use SoftDeletes;

    public const STATUSES = ['generated', 'paid'];

    protected $fillable = [
        'slip_no', 'staff_member_id', 'month', 'staff_name', 'designation', 'basic', 'working_days',
        'unpaid_days', 'gross', 'total_deductions', 'net_pay', 'status', 'paid_on', 'payment_mode',
        'reference_no', 'remarks', 'created_by', 'paid_by',
    ];

    protected $casts = [
        'basic' => 'float',
        'unpaid_days' => 'float',
        'gross' => 'float',
        'total_deductions' => 'float',
        'net_pay' => 'float',
        'paid_on' => 'date',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'staff_member_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalarySlipLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public static function monthLabel(string $month): string
    {
        return Carbon::createFromFormat('!Y-m', $month)->format('F Y');
    }

    /** SLP-202607-0004 : the running number never restarts and is never reused. */
    public static function nextNumber(string $month): string
    {
        $last = static::withTrashed()->lockForUpdate()->orderByDesc('id')->value('slip_no');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

        return 'SLP-'.str_replace('-', '', $month).'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * $lines = [['type' => 'earning'|'deduction', 'name' => ..., 'amount' => ...], ...]
     * Adds the automatic "unpaid leave" deduction and returns the totals plus the final line list.
     */
    public static function compute(float $basic, array $lines, int $workingDays, float $unpaidDays): array
    {
        $collection = collect($lines);
        $gross = round($basic + $collection->where('type', 'earning')->sum('amount'), 2);
        $deductions = $collection->where('type', 'deduction')->sum('amount');

        $unpaid = 0.0;
        if ($unpaidDays > 0 && $workingDays > 0) {
            $unpaid = round($gross / $workingDays * $unpaidDays, 2);
            $lines[] = [
                'type' => 'deduction',
                'name' => 'Unpaid leave ('.rtrim(rtrim(number_format($unpaidDays, 1, '.', ''), '0'), '.').' day(s))',
                'amount' => $unpaid,
                'is_system' => true,
            ];
        }

        $totalDeductions = round($deductions + $unpaid, 2);

        return [
            'gross' => $gross,
            'total_deductions' => $totalDeductions,
            'net_pay' => round($gross - $totalDeductions, 2),
            'lines' => $lines,
        ];
    }
}
