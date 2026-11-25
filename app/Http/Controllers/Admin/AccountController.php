<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountEntry;
use App\Models\FeePayment;
use App\Models\FeeReceipt;
use App\Models\SalarySlip;
use App\Services\ResultService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    private const AUTO_CATEGORIES = ['Fees', 'Fee refund', 'Salary'];

    public function index(Request $request)
    {
        [$from, $to, $all, $scoped] = $this->scopedLedger($request);

        $tab = in_array($request->tab, ['income', 'expense'], true) ? $request->tab : 'all';
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $counts = [
            'all' => $scoped->count(),
            'income' => $scoped->where('type', 'income')->count(),
            'expense' => $scoped->where('type', 'expense')->count(),
        ];

        $list = $this->sorted($tab === 'all' ? $scoped : $scoped->where('type', $tab), true);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $entries = new LengthAwarePaginator(
            $list->slice(($page - 1) * $perPage, $perPage)->values(),
            $list->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $income = (float) $scoped->where('type', 'income')->sum('amount');
        $expense = (float) $scoped->where('type', 'expense')->sum('amount');

        // Opening and closing balance only make sense when nothing narrows the ledger
        $narrowed = $request->filled('category') || $request->filled('mode') || $request->filled('search');
        $open = $this->balanceBefore($from);
        $periodCash = $this->signed($all->where('mode', 'cash'));
        $periodDigital = $this->signed($all->where('mode', '!=', 'cash'));

        $stats = [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'narrowed' => $narrowed,
            'opening' => $open['cash'] + $open['digital'],
            'closing' => $open['cash'] + $open['digital'] + $periodCash + $periodDigital,
            'cash' => $open['cash'] + $periodCash,
            'digital' => $open['digital'] + $periodDigital,
        ];

        return view('admin.accounts.index', [
            'entries' => $entries,
            'counts' => $counts,
            'stats' => $stats,
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'perPage' => $perPage,
            'categories' => collect(self::AUTO_CATEGORIES)
                ->merge(AccountEntry::INCOME_CATEGORIES)->merge(AccountEntry::EXPENSE_CATEGORIES)->unique()->values(),
        ]);
    }

    public function store(Request $request)
    {
        AccountEntry::create($this->validated($request) + ['recorded_by' => auth()->id()]);

        return back()->with('success', 'Entry recorded.');
    }

    public function update(Request $request, AccountEntry $entry)
    {
        $entry->update($this->validated($request));

        return back()->with('success', 'Entry updated.');
    }

    public function destroy(AccountEntry $entry)
    {
        $entry->delete(); // soft delete

        return back()->with('success', 'Entry deleted.');
    }

    /** Printable day book with a running balance. */
    public function book(Request $request)
    {
        [$from, $to] = $this->range($request);
        $all = $this->ledger($from, $to);

        $open = $this->balanceBefore($from);
        $opening = $open['cash'] + $open['digital'];

        $balance = $opening;
        $receipts = 0.0;
        $payments = 0.0;
        $rows = [];

        foreach ($this->sorted($all, false) as $r) {
            if ($r['type'] === 'income') {
                $balance += $r['amount'];
                $receipts += $r['amount'];
            } else {
                $balance -= $r['amount'];
                $payments += $r['amount'];
            }
            $rows[] = $r + ['balance' => round($balance, 2)];
        }

        return view('admin.accounts.book', compact('from', 'to', 'opening', 'rows', 'receipts', 'payments', 'balance'));
    }

    public function csv(Request $request): StreamedResponse
    {
        [, , , $scoped] = $this->scopedLedger($request);
        $rows = $this->sorted($scoped, false);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Date', 'Type', 'Category', 'Description', 'Income', 'Expense', 'Mode', 'Reference', 'Recorded By', 'Source']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['date']->format('Y-m-d'),
                    $r['type'],
                    $r['category'],
                    $r['description'],
                    $r['type'] === 'income' ? $r['amount'] : '',
                    $r['type'] === 'expense' ? $r['amount'] : '',
                    Money::MODES[$r['mode']] ?? $r['mode'],
                    $r['ref'],
                    $r['by'],
                    $r['source'],
                ]);
            }

            fclose($out);
        }, 'daily-accounts-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------------------------------------------------------------------

    private function validated(Request $request): array
    {
        $type = $request->input('type');

        return $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in($type === 'income' ? AccountEntry::INCOME_CATEGORIES : AccountEntry::EXPENSE_CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'mode' => ['required', Rule::in(array_keys(Money::MODES))],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ]);
    }

    private function range(Request $request): array
    {
        $from = $this->dateOr($request->from, now()->startOfMonth()->toDateString());
        $to = $this->dateOr($request->to, now()->toDateString());

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    private function dateOr($value, string $default): string
    {
        try {
            return $value ? Carbon::parse($value)->toDateString() : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /** @return array [$from, $to, $everythingInRange, $afterCategoryModeSearchFilters] */
    private function scopedLedger(Request $request): array
    {
        [$from, $to] = $this->range($request);
        $all = $this->ledger($from, $to);

        $scoped = $all
            ->when($request->filled('category'), fn ($c) => $c->where('category', $request->category))
            ->when($request->filled('mode') && isset(Money::MODES[$request->mode]), fn ($c) => $c->where('mode', $request->mode))
            ->when($request->filled('search'), fn ($c) => $c->filter(
                fn ($r) => stripos($r['description'].' '.$r['category'].' '.($r['ref'] ?? ''), $request->search) !== false
            ));

        return [$from, $to, $all, $scoped];
    }

    private function sorted(Collection $rows, bool $newestFirst): Collection
    {
        return $rows->sort(function ($a, $b) use ($newestFirst) {
            $x = [$a['date']->timestamp, $a['ts']];
            $y = [$b['date']->timestamp, $b['ts']];

            return $newestFirst ? $y <=> $x : $x <=> $y;
        })->values();
    }

    private function signed(Collection $rows): float
    {
        return (float) $rows->sum(fn ($r) => $r['type'] === 'income' ? $r['amount'] : -$r['amount']);
    }

    /** One list built from fees, refunds, salaries and manual entries. Nothing is stored twice. */
    private function ledger(string $from, string $to): Collection
    {
        $rows = collect();

        FeePayment::with(['receipt.student', 'receiver'])
            ->whereHas('receipt')
            ->whereBetween('paid_on', [$from, $to])
            ->get()
            ->each(function ($p) use ($rows) {
                $rows->push($this->row(
                    $p->paid_on, $p->created_at, 'income', 'Fees',
                    'Fee receipt '.$p->receipt->receipt_no.' — '.ResultService::studentName($p->receipt->student),
                    $p->amount, $p->mode, $p->reference_no, $p->receiver?->name, 'fees',
                    route('admin.fees.print', $p->fee_receipt_id)
                ));
            });

        FeeReceipt::with('student')
            ->where('status', 'refunded')
            ->whereDate('refunded_at', '>=', $from)
            ->whereDate('refunded_at', '<=', $to)
            ->get()
            ->each(function ($r) use ($rows) {
                $rows->push($this->row(
                    $r->refunded_at, $r->refunded_at, 'expense', 'Fee refund',
                    'Refund '.$r->receipt_no.' — '.ResultService::studentName($r->student).($r->refund_reason ? ' ('.$r->refund_reason.')' : ''),
                    $r->paid_amount, 'cash', null, null, 'refund',
                    route('admin.fees.print', $r->id)
                ));
            });

        SalarySlip::with('payer')
            ->where('status', 'paid')
            ->whereBetween('paid_on', [$from, $to])
            ->get()
            ->each(function ($s) use ($rows) {
                $rows->push($this->row(
                    $s->paid_on, $s->updated_at, 'expense', 'Salary',
                    'Salary '.$s->slip_no.' — '.$s->staff_name.' ('.SalarySlip::monthLabel($s->month).')',
                    $s->net_pay, $s->payment_mode, $s->reference_no, $s->payer?->name, 'payroll',
                    route('admin.payroll.slip', $s->id)
                ));
            });

        AccountEntry::with('recorder')
            ->whereBetween('entry_date', [$from, $to])
            ->get()
            ->each(function ($e) use ($rows) {
                $rows->push($this->row(
                    $e->entry_date, $e->created_at, $e->type, $e->category, $e->description,
                    $e->amount, $e->mode, $e->reference_no, $e->recorder?->name, 'manual', null, $e
                ));
            });

        return $rows;
    }

    private function row($date, $createdAt, string $type, string $category, string $description, $amount, ?string $mode, ?string $ref, ?string $by, string $source, ?string $link = null, ?AccountEntry $entry = null): array
    {
        return [
            'date' => Carbon::parse($date),
            'ts' => Carbon::parse($createdAt ?? $date)->timestamp,
            'type' => $type,
            'category' => $category,
            'description' => $description,
            'amount' => round((float) $amount, 2),
            'mode' => $mode ?: 'cash',
            'ref' => $ref,
            'by' => $by,
            'source' => $source,
            'link' => $link,
            'entry' => $entry ? [
                'id' => $entry->id,
                'type' => $entry->type,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'category' => $entry->category,
                'description' => $entry->description,
                'amount' => $entry->amount,
                'mode' => $entry->mode,
                'reference_no' => $entry->reference_no,
            ] : null,
        ];
    }

    /** Money in hand before a date, split into cash and bank / digital. */
    private function balanceBefore(string $date): array
    {
        $cash = 0.0;
        $digital = 0.0;

        $fees = FeePayment::whereHas('receipt')->whereDate('paid_on', '<', $date);
        $cash += (float) (clone $fees)->where('mode', 'cash')->sum('amount');
        $digital += (float) (clone $fees)->where('mode', '!=', 'cash')->sum('amount');

        foreach (['income' => 1, 'expense' => -1] as $type => $sign) {
            $q = AccountEntry::where('type', $type)->whereDate('entry_date', '<', $date);
            $cash += $sign * (float) (clone $q)->where('mode', 'cash')->sum('amount');
            $digital += $sign * (float) (clone $q)->where('mode', '!=', 'cash')->sum('amount');
        }

        $salary = SalarySlip::where('status', 'paid')->whereDate('paid_on', '<', $date);
        $cash -= (float) (clone $salary)->where('payment_mode', 'cash')->sum('net_pay');
        $digital -= (float) (clone $salary)->where('payment_mode', '!=', 'cash')->sum('net_pay');

        $cash -= (float) FeeReceipt::where('status', 'refunded')->whereDate('refunded_at', '<', $date)->sum('paid_amount');

        return ['cash' => $cash, 'digital' => $digital];
    }
}
