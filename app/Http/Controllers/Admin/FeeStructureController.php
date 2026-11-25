<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeHead;
use App\Models\FeeReceipt;
use App\Models\FeeReceiptItem;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ResultService;
use App\Support\AcademicYear;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeeStructureController extends Controller
{
    public function index(Request $request)
    {
        $years = AcademicYear::options();
        $year = in_array($request->year, $years, true) ? $request->year : AcademicYear::current();

        $classes = SchoolClass::orderBy('name')->get();
        $class = $classes->firstWhere('id', (int) $request->get('class_id', $classes->first()->id ?? 0));

        $heads = FeeHead::orderBy('id')->get();

        $amounts = $class
            ? FeeStructure::where('academic_year', $year)->where('school_class_id', $class->id)->pluck('amount', 'fee_head_id')
            : collect();

        $overview = FeeStructure::where('academic_year', $year)
            ->select('school_class_id', DB::raw('count(*) as heads'), DB::raw('sum(amount) as total'))
            ->groupBy('school_class_id')
            ->get()
            ->keyBy('school_class_id');

        $studentCounts = Student::select('school_class_id', DB::raw('count(*) as n'))
            ->groupBy('school_class_id')
            ->pluck('n', 'school_class_id');

        // Fee heads that are already used anywhere cannot be deleted (only switched off)
        $usedHeadIds = FeeStructure::distinct()->pluck('fee_head_id')
            ->merge(FeeReceiptItem::whereNotNull('fee_head_id')->distinct()->pluck('fee_head_id'))
            ->unique();

        return view('admin.fee-structure.index', compact(
            'years', 'year', 'classes', 'class', 'heads', 'amounts', 'overview', 'studentCounts', 'usedHeadIds'
        ));
    }

    /** Save the price list of one class for one year. An empty or zero amount removes that fee head. */
    public function save(Request $request)
    {
        $data = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'class_id' => ['required', 'exists:school_classes,id'],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $validHeads = FeeHead::pluck('id')->all();

        DB::transaction(function () use ($data, $validHeads) {
            foreach ($data['amounts'] ?? [] as $headId => $amount) {
                if (! in_array((int) $headId, $validHeads, true)) {
                    continue;
                }

                $key = [
                    'academic_year' => $data['academic_year'],
                    'school_class_id' => $data['class_id'],
                    'fee_head_id' => $headId,
                ];

                if ($amount === null || $amount === '' || (float) $amount <= 0) {
                    FeeStructure::where($key)->delete();

                    continue;
                }

                FeeStructure::updateOrCreate($key, ['amount' => round((float) $amount, 2)]);
            }
        });

        return redirect()->route('admin.fee-structure.index', ['year' => $data['academic_year'], 'class_id' => $data['class_id']])
            ->with('success', 'Fee structure saved. Receipts that already exist are not changed.');
    }

    /** Copy one class's price list to other classes and/or another year, with an optional % increase. */
    public function copy(Request $request)
    {
        $data = $request->validate([
            'from_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'from_class_id' => ['required', 'exists:school_classes,id'],
            'to_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'target_ids' => ['required', 'array', 'min:1'],
            'target_ids.*' => ['exists:school_classes,id'],
            'increase_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $source = FeeStructure::where('academic_year', $data['from_year'])
            ->where('school_class_id', $data['from_class_id'])
            ->get();

        if ($source->isEmpty()) {
            return back()->withErrors(['copy' => 'The class you are copying from has no fee amounts yet. Save them first.']);
        }

        $pct = (float) ($data['increase_percent'] ?? 0);
        $overwrite = $request->boolean('overwrite');
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($data, $source, $pct, $overwrite, &$created, &$updated) {
            foreach ($data['target_ids'] as $targetId) {
                if ($data['to_year'] === $data['from_year'] && (int) $targetId === (int) $data['from_class_id']) {
                    continue;
                }

                foreach ($source as $row) {
                    $amount = $pct > 0 ? round($row->amount * (1 + $pct / 100)) : $row->amount;
                    $key = [
                        'academic_year' => $data['to_year'],
                        'school_class_id' => $targetId,
                        'fee_head_id' => $row->fee_head_id,
                    ];

                    $existing = FeeStructure::where($key)->first();
                    if (! $existing) {
                        FeeStructure::create($key + ['amount' => $amount]);
                        $created++;
                    } elseif ($overwrite) {
                        $existing->update(['amount' => $amount]);
                        $updated++;
                    }
                }
            }
        });

        return redirect()->route('admin.fee-structure.index', ['year' => $data['to_year'], 'class_id' => $data['from_class_id']])
            ->with('success', "Copied: {$created} fee amount(s) created, {$updated} updated.");
    }

    /** Create one unpaid receipt per student of a class for a period (e.g. Baisakh). Students already billed are skipped. */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'class_id' => ['required', 'exists:school_classes,id'],
            'period' => ['required', Rule::in(AcademicYear::PERIODS)],
            'head_ids' => ['required', 'array', 'min:1'],
            'head_ids.*' => ['exists:fee_heads,id'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $structure = FeeStructure::with('head')
            ->where('academic_year', $data['academic_year'])
            ->where('school_class_id', $data['class_id'])
            ->whereIn('fee_head_id', $data['head_ids'])
            ->get()
            ->keyBy('fee_head_id');

        if ($structure->isEmpty()) {
            return back()->withErrors(['generate' => 'No amounts are set for the chosen fee heads in this class and year. Set them in the price list first.']);
        }

        $students = ResultService::students((int) $data['class_id']);
        if ($students->isEmpty()) {
            return back()->withErrors(['generate' => 'This class has no students.']);
        }

        $created = 0;
        $skipped = 0;
        $total = 0.0;

        foreach ($students as $student) {
            $items = [];
            foreach ($structure as $row) {
                if (FeeReceipt::alreadyBilled($student->id, $data['academic_year'], $row->fee_head_id, $data['period'])) {
                    continue;
                }
                $items[] = [
                    'fee_head_id' => $row->fee_head_id,
                    'description' => $row->head->name ?? 'Fee',
                    'period' => $data['period'],
                    'amount' => (float) $row->amount,
                ];
            }

            if (! $items) {
                $skipped++;

                continue;
            }

            $subtotal = round(array_sum(array_column($items, 'amount')), 2);

            // The number is assigned, the receipt and its items are created,
            // and the billing identities are claimed in one transaction. A
            // concurrent creator claiming the same number retries with the next
            // number; a concurrent claim on the same bill identity aborts the
            // transaction so no partial bill survives, and this student is
            // skipped (matching the existing "already billed" convention).
            try {
                FeeReceipt::createWithUniqueNumber([
                    'student_id' => $student->id,
                    'academic_year' => $data['academic_year'],
                    'receipt_date' => $data['receipt_date'],
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'fine' => 0,
                    'total_due' => $subtotal,
                    'paid_amount' => 0,
                    'balance' => $subtotal,
                    'status' => 'unpaid',
                    'remarks' => 'Generated bill: '.$data['period'],
                    'collected_by' => auth()->id(),
                ], function (FeeReceipt $receipt) use ($items) {
                    $receipt->items()->createMany($items);
                    $receipt->claimIdentities($receipt->items()->get());
                });
            } catch (QueryException $e) {
                if (FeeReceipt::isBillIdentityCollision($e)) {
                    $skipped++;

                    continue;
                }

                throw $e;
            }

            $created++;
            $total += $subtotal;
        }

        $message = "{$created} bill(s) created for {$data['period']} (total ".Money::format($total).')';
        if ($skipped > 0) {
            $message .= ", {$skipped} student(s) skipped because they were already billed";
        }

        return redirect()->route('admin.fees.index', ['tab' => 'unpaid', 'year' => $data['academic_year'], 'class_id' => $data['class_id']])
            ->with('success', $message.'.');
    }

    // ---------------- Fee heads ----------------

    public function storeHead(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:fee_heads,name'],
            'frequency' => ['required', Rule::in(array_keys(FeeHead::FREQUENCIES))],
        ]);

        FeeHead::create($data);

        return back()->with('success', 'Fee head added.');
    }

    public function updateHead(Request $request, FeeHead $head)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('fee_heads', 'name')->ignore($head->id)],
            'frequency' => ['required', Rule::in(array_keys(FeeHead::FREQUENCIES))],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $head->update($data);

        return back()->with('success', 'Fee head updated.');
    }

    public function destroyHead(FeeHead $head)
    {
        $used = FeeStructure::where('fee_head_id', $head->id)->exists()
            || FeeReceiptItem::where('fee_head_id', $head->id)->exists();

        if ($used) {
            return back()->withErrors(['head' => 'This fee head is already used in a fee structure or a receipt. Switch it off instead of deleting it.']);
        }

        $head->delete();

        return back()->with('success', 'Fee head deleted.');
    }
}
