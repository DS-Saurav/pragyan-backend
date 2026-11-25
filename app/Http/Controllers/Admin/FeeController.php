<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FeeReceipt;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ResultService;
use App\Support\AcademicYear;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeController extends Controller
{
    private function currentYear(): string
    {
        return AcademicYear::current();
    }

    private function yearOf(Request $request): string
    {
        return $request->get('year', $this->currentYear());
    }

    /** Filters shared by the list, tab counts and CSV. The status tab is applied separately. */
    private function filtered(Request $request)
    {
        $year = $this->yearOf($request);

        return FeeReceipt::with(['student', 'payments', 'items'])
            ->when($year !== 'all', fn ($q) => $q->where('academic_year', $year))
            ->when($request->filled('class_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('school_class_id', $request->class_id)))
            ->when(
                $request->filled('mode') && isset(FeeReceipt::MODES[$request->mode]),
                fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('mode', $request->mode))
            )
            ->when($request->filled('from'), fn ($q) => $q->whereDate('receipt_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('receipt_date', '<=', $request->to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('receipt_no', 'like', "%{$s}%")
                        ->orWhereHas('student', fn ($st) => $st
                            ->where('first_name', 'like', "%{$s}%")
                            ->orWhere('last_name', 'like', "%{$s}%")
                            ->orWhere(ResultService::ADMISSION, 'like', "%{$s}%"));
                });
            });
    }

    private function applyTab($query, string $tab)
    {
        return in_array($tab, FeeReceipt::STATUSES, true) ? $query->where('status', $tab) : $query;
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $year = $this->yearOf($request);
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $receipts = $this->applyTab($this->filtered($request), $tab)
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $raw = $this->filtered($request)->reorder()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts = ['all' => (int) $raw->sum()];
        foreach (FeeReceipt::STATUSES as $s) {
            $counts[$s] = (int) ($raw[$s] ?? 0);
        }

        // Stat cards (for the selected academic year; refunded receipts never count as money collected)
        $yearScope = fn ($q) => $year === 'all' ? $q : $q->where('academic_year', $year);
        $notRefunded = fn ($q) => $q->where('status', '!=', 'refunded');

        $stats = [
            'collected' => (float) FeePayment::whereHas('receipt', fn ($q) => $yearScope($notRefunded($q)))->sum('amount'),
            'outstanding' => (float) $yearScope(FeeReceipt::query())->whereIn('status', ['partial', 'unpaid'])->sum('balance'),
            'today' => (float) FeePayment::whereDate('paid_on', now()->toDateString())->whereHas('receipt', $notRefunded)->sum('amount'),
            'month' => (float) FeePayment::whereBetween('paid_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->whereHas('receipt', $notRefunded)->sum('amount'),
            'defaulters' => (int) $yearScope(FeeReceipt::query())->whereIn('status', ['partial', 'unpaid'])->distinct()->count('student_id'),
        ];

        // Outstanding per student, used to warn the cashier in the payment form
        $dues = FeeReceipt::whereIn('status', ['partial', 'unpaid'])
            ->select('student_id', DB::raw('sum(balance) as due'), DB::raw('count(*) as n'))
            ->groupBy('student_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->student_id => ['due' => (float) $r->due, 'n' => (int) $r->n]]);

        $classes = SchoolClass::orderBy('name')->get();

        $studentsJs = Student::all()->map(fn ($s) => [
            'id' => $s->id,
            'name' => ResultService::studentName($s),
            'roll' => ResultService::roll($s),
            'adm' => ResultService::admission($s),
            'class_id' => $s->school_class_id,
        ])->sortBy('name')->values();

        $structures = FeeStructure::with('head')->get()->map(fn ($s) => [
            'year' => $s->academic_year,
            'class_id' => $s->school_class_id,
            'head_id' => $s->fee_head_id,
            'head' => $s->head->name ?? '',
            'amount' => (float) $s->amount,
        ])->values();

        $years = collect(AcademicYear::options());

        return view('admin.fees.index', [
            'receipts' => $receipts,
            'counts' => $counts,
            'stats' => $stats,
            'dues' => $dues,
            'tab' => $tab,
            'year' => $year,
            'years' => $years,
            'perPage' => $perPage,
            'classes' => $classes,
            'classNames' => $classes->pluck('name', 'id'),
            'studentsJs' => $studentsJs,
            'structures' => $structures,
            'heads' => FeeHead::where('is_active', true)->orderBy('id')->get(['id', 'name']),
            'defaultYear' => $this->currentYear(),
        ]);
    }

    /** Create a new receipt, optionally with a payment taken right now. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'academic_year' => ['required', 'string', 'max:20'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_head_id' => ['nullable', 'exists:fee_heads,id'],
            'items.*.description' => ['required', 'string', 'max:150'],
            'items.*.period' => ['nullable', 'string', 'max:40'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'fine' => ['nullable', 'numeric', 'min:0'],
            'paying_now' => ['nullable', 'numeric', 'min:0'],
            'mode' => [Rule::requiredIf(fn () => (float) $request->input('paying_now') > 0), 'nullable', Rule::in(array_keys(FeeReceipt::MODES))],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $items = collect($data['items'])->map(fn ($i) => [
            'fee_head_id' => $i['fee_head_id'] ?? null,
            'description' => $i['description'],
            'period' => $i['period'] ?? null,
            'amount' => round((float) $i['amount'], 2),
        ])->values()->all();

        // Refuse to bill the same fee head and period twice for the same student and year
        foreach ($items as $it) {
            if ($it['fee_head_id'] && $it['period']
                && FeeReceipt::alreadyBilled((int) $data['student_id'], $data['academic_year'], (int) $it['fee_head_id'], $it['period'])) {
                return back()->withErrors([
                    'items' => "{$it['description']} for {$it['period']} is already billed to this student for {$data['academic_year']}.",
                ])->withInput();
            }
        }

        // Refuse two identified lines for the same fee head and period within
        // this single receipt; only one billing identity can be claimed for a
        // (fee head, period) pair.
        $seen = [];
        foreach ($items as $it) {
            if (! $it['fee_head_id'] || ! $it['period']) {
                continue;
            }

            $key = $it['fee_head_id'].'|'.$it['period'];
            if (isset($seen[$key])) {
                return back()->withErrors([
                    'items' => "{$it['description']} for {$it['period']} is listed twice in this receipt.",
                ])->withInput();
            }
            $seen[$key] = true;
        }

        $subtotal = round(array_sum(array_column($items, 'amount')), 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $fine = round((float) ($data['fine'] ?? 0), 2);
        $paying = round((float) ($data['paying_now'] ?? 0), 2);

        if ($discount > $subtotal) {
            return back()->withErrors(['discount' => 'Discount cannot be more than the fee total.'])->withInput();
        }

        $total = round($subtotal - $discount + $fine, 2);
        if ($paying > $total) {
            return back()->withErrors(['paying_now' => 'Amount received cannot be more than the total due (Rs. '.number_format($total, 2).').'])->withInput();
        }

        // The number is assigned, the receipt and its items are created, and
        // the billing identities are claimed in one transaction. A concurrent
        // creator that claims the same number retries with the next number; a
        // concurrent (or duplicate) claim on the same bill identity aborts the
        // whole transaction so no partial receipt survives.
        try {
            $receipt = FeeReceipt::createWithUniqueNumber([
                'student_id' => $data['student_id'],
                'academic_year' => $data['academic_year'],
                'receipt_date' => $data['receipt_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'fine' => $fine,
                'total_due' => $total,
                'paid_amount' => 0,
                'balance' => $total,
                'status' => 'unpaid',
                'remarks' => $data['remarks'] ?? null,
                'collected_by' => auth()->id(),
            ], function (FeeReceipt $receipt) use ($items, $data, $paying) {
                $receipt->items()->createMany($items);
                $receipt->claimIdentities($receipt->items()->get());

                if ($paying > 0) {
                    $receipt->payments()->create([
                        'amount' => $paying,
                        'mode' => $data['mode'],
                        'reference_no' => $data['reference_no'] ?? null,
                        'paid_on' => $data['receipt_date'],
                        'received_by' => auth()->id(),
                    ]);
                }

                $this->recalculate($receipt);
            });
        } catch (QueryException $e) {
            if (FeeReceipt::isBillIdentityCollision($e)) {
                return back()->withErrors([
                    'items' => "One of these fees is already billed to this student for {$data['academic_year']}.",
                ])->withInput();
            }

            throw $e;
        }

        return redirect()->back()
            ->with('success', "Receipt {$receipt->receipt_no} created.")
            ->with('print_receipt', $receipt->id);
    }

    /** Collect (part of) the remaining balance of an existing receipt. */
    public function pay(Request $request, FeeReceipt $receipt)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'mode' => ['required', Rule::in(array_keys(FeeReceipt::MODES))],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $error = DB::transaction(function () use ($receipt, $data) {
            $r = FeeReceipt::lockForUpdate()->findOrFail($receipt->id);

            if ($r->status === 'refunded') {
                return 'This receipt was refunded.';
            }
            if ((float) $data['amount'] > $r->balance + 0.005) {
                return 'Amount is more than the balance (Rs. '.number_format($r->balance, 2).').';
            }

            $r->payments()->create([
                'amount' => round((float) $data['amount'], 2),
                'mode' => $data['mode'],
                'reference_no' => $data['reference_no'] ?? null,
                'paid_on' => $data['paid_on'],
                'received_by' => auth()->id(),
            ]);

            $this->recalculate($r);

            return null;
        });

        if ($error) {
            return back()->withErrors(['payment' => $error]);
        }

        return back()->with('success', 'Payment recorded.')->with('print_receipt', $receipt->id);
    }

    public function refund(Request $request, FeeReceipt $receipt)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        // Refund atomically against the locked, freshly fetched receipt so a
        // repeated refund or a payment racing with the refund cannot act on a
        // stale status or paid amount.
        $error = DB::transaction(function () use ($receipt, $data) {
            $r = FeeReceipt::lockForUpdate()->findOrFail($receipt->id);

            if ($r->status === 'refunded') {
                return 'This receipt is already refunded.';
            }
            if ($r->paid_amount <= 0) {
                return 'Nothing has been paid on this receipt, so there is nothing to refund. Delete it instead.';
            }

            $r->update([
                'status' => 'refunded',
                'balance' => 0,
                'refunded_at' => now(),
                'refund_reason' => $data['reason'],
            ]);

            // A refunded bill may be issued again: release its identity claims.
            // Items and payments are untouched (historical records preserved).
            $r->releaseIdentities();

            return null;
        });

        if ($error) {
            return back()->withErrors(['refund' => $error]);
        }

        return back()->with('success', 'Receipt refunded.');
    }

    public function destroy(FeeReceipt $receipt)
    {
        if (! in_array($receipt->status, ['unpaid', 'refunded'], true)) {
            return back()->withErrors(['delete' => 'Only unpaid or refunded receipts can be deleted. Refund this receipt first.']);
        }

        // Release billing-identity claims first (must happen before or during
        // soft-delete so the bill becomes re-issuable). The receipt number is
        // never reused and items/payments stay for the audit trail.
        \Illuminate\Support\Facades\DB::transaction(function () use ($receipt) {
            $id = $receipt->id;
            \Illuminate\Support\Facades\DB::table('fee_bill_identities')->where('fee_receipt_id', $id)->delete();
            $receipt->delete();
        });

        return back()->with('success', 'Receipt deleted.');

        return back()->with('success', 'Receipt deleted.');
    }

    /** Printable receipt (student copy + office copy). */
    public function receipt(FeeReceipt $receipt)
    {
        $receipt->load('student', 'items', 'payments.receiver', 'collector');
        $class = SchoolClass::find($receipt->student->school_class_id ?? 0);

        return view('admin.fees.receipt', compact('receipt', 'class'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->applyTab($this->filtered($request), $request->get('tab', 'all'))
            ->orderByDesc('receipt_date')
            ->get();
        $classNames = SchoolClass::pluck('name', 'id');

        return response()->streamDownload(function () use ($rows, $classNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, ['Receipt No', 'Date', 'Student', 'Admission No', 'Class', 'Academic Year', 'Subtotal',
                'Discount', 'Fine', 'Total Due', 'Paid', 'Balance', 'Payment Mode', 'Status']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->receipt_no,
                    $r->receipt_date->format('Y-m-d'),
                    ResultService::studentName($r->student),
                    ResultService::admission($r->student),
                    $classNames[$r->student->school_class_id ?? 0] ?? '',
                    $r->academic_year,
                    $r->subtotal,
                    $r->discount,
                    $r->fine,
                    $r->total_due,
                    $r->paid_amount,
                    $r->balance,
                    $r->payments->pluck('mode')->unique()->map(fn ($m) => FeeReceipt::MODES[$m] ?? $m)->implode(' + '),
                    $r->status,
                ]);
            }

            fclose($out);
        }, 'fee-receipts-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function recalculate(FeeReceipt $r): void
    {
        $r->refreshTotals();
    }
}
