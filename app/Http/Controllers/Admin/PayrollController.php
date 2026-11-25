<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Models\SalarySlip;
use App\Models\StaffMember;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    private const WORKING_DAYS = 26;

    private const MONTH_RULE = '/^\d{4}-(0[1-9]|1[0-2])$/';

    private function monthOf(Request $request): string
    {
        $m = (string) $request->get('month', now()->format('Y-m'));

        return ($m === 'all' || preg_match(self::MONTH_RULE, $m)) ? $m : now()->format('Y-m');
    }

    private function monthOptions(): array
    {
        $months = collect(range(0, 11))
            ->map(fn ($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'))
            ->merge(SalarySlip::query()->distinct()->pluck('month'))
            ->unique()->sortDesc()->values();

        return $months->mapWithKeys(fn ($m) => [$m => SalarySlip::monthLabel($m)])->all();
    }

    /** Filters shared by the list, the tab counts and the CSV. The status tab is applied separately. */
    private function filtered(Request $request)
    {
        $month = $this->monthOf($request);

        return SalarySlip::with(['staff', 'lines'])
            ->when($month !== 'all', fn ($q) => $q->where('month', $month))
            ->when($request->filled('type') && isset(StaffMember::TYPES[$request->type]),
                fn ($q) => $q->whereHas('staff', fn ($s) => $s->where('type', $request->type)))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('staff_name', 'like', "%{$s}%")
                        ->orWhere('slip_no', 'like', "%{$s}%")
                        ->orWhere('designation', 'like', "%{$s}%");
                });
            });
    }

    public function index(Request $request)
    {
        $month = $this->monthOf($request);
        $tab = in_array($request->tab, SalarySlip::STATUSES, true) ? $request->tab : 'all';
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $slips = $this->filtered($request)
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->orderByDesc('month')->orderBy('staff_name')
            ->paginate($perPage)
            ->withQueryString();

        $raw = $this->filtered($request)->setEagerLoads([])->reorder()
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(net_pay) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $counts = ['all' => (int) $raw->sum('total')];
        foreach (SalarySlip::STATUSES as $s) {
            $counts[$s] = (int) ($raw->get($s)->total ?? 0);
        }

        $stats = [
            'slips' => $counts['all'],
            'paid' => $counts['paid'],
            'pending_count' => $counts['generated'],
            'pending_amount' => (float) ($raw->get('generated')->amount ?? 0),
            'disbursed' => (float) ($raw->get('paid')->amount ?? 0),
        ];

        $activeStaff = StaffMember::with('components')->where('status', 'active')->orderBy('name')->get();

        $staffJs = $activeStaff->mapWithKeys(fn ($s) => [$s->id => [
            'basic' => $s->basic_salary,
            'lines' => $s->defaultLines(),
        ]]);

        return view('admin.payroll.index', [
            'slips' => $slips,
            'counts' => $counts,
            'stats' => $stats,
            'tab' => $tab,
            'month' => $month,
            'monthOptions' => $this->monthOptions(),
            'perPage' => $perPage,
            'activeStaff' => $activeStaff,
            'staffJs' => $staffJs,
            'componentNames' => SalaryComponent::where('is_active', true)->orderBy('name')->pluck('name'),
            'defaultWorkingDays' => self::WORKING_DAYS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge($this->rules(), [
            'staff_member_id' => ['required', 'exists:staff_members,id'],
            'month' => ['required', 'regex:'.self::MONTH_RULE],
        ]));

        $staff = StaffMember::findOrFail($data['staff_member_id']);

        if (SalarySlip::where('staff_member_id', $staff->id)->where('month', $data['month'])->exists()) {
            return back()->withErrors(['slip' => $staff->name.' already has a salary slip for '.SalarySlip::monthLabel($data['month']).'.'])->withInput();
        }

        $calc = $this->calculate($data);
        if (is_string($calc)) {
            return back()->withErrors(['slip' => $calc])->withInput();
        }

        DB::transaction(function () use ($data, $staff, $calc) {
            $this->createSlip($staff, $data['month'], (float) $data['basic'], (int) $data['working_days'], (float) ($data['unpaid_days'] ?? 0), $calc, $data['remarks'] ?? null);
        });

        return back()->with('success', 'Salary slip generated for '.$staff->name.'.');
    }

    public function update(Request $request, SalarySlip $slip)
    {
        if ($slip->status !== 'generated') {
            return back()->withErrors(['slip' => 'A paid slip cannot be edited. Use "Undo payment" first.']);
        }

        $data = $request->validate($this->rules());

        $calc = $this->calculate($data);
        if (is_string($calc)) {
            return back()->withErrors(['slip' => $calc])->withInput();
        }

        DB::transaction(function () use ($slip, $data, $calc) {
            $slip->update([
                'basic' => $data['basic'],
                'working_days' => $data['working_days'],
                'unpaid_days' => $data['unpaid_days'] ?? 0,
                'gross' => $calc['gross'],
                'total_deductions' => $calc['total_deductions'],
                'net_pay' => $calc['net_pay'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            $slip->lines()->delete();
            $slip->lines()->createMany($calc['lines']);
        });

        return back()->with('success', 'Salary slip updated.');
    }

    /** Create a slip for every active staff member who does not have one for the month yet. */
    public function run(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'regex:'.self::MONTH_RULE],
            'working_days' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        $created = 0;
        $skippedNoBasic = 0;
        $total = 0.0;

        foreach (StaffMember::with('components')->where('status', 'active')->orderBy('name')->get() as $staff) {
            if (SalarySlip::where('staff_member_id', $staff->id)->where('month', $data['month'])->exists()) {
                continue;
            }
            if ($staff->basic_salary <= 0) {
                $skippedNoBasic++;

                continue;
            }

            $calc = SalarySlip::compute($staff->basic_salary, $staff->defaultLines(), (int) $data['working_days'], 0);
            if ($calc['net_pay'] < 0) {
                continue;
            }

            DB::transaction(function () use ($staff, $data, $calc) {
                $this->createSlip($staff, $data['month'], $staff->basic_salary, (int) $data['working_days'], 0, $calc, null);
            });

            $created++;
            $total += $calc['net_pay'];
        }

        $label = SalarySlip::monthLabel($data['month']);
        $message = "{$created} slip(s) generated for {$label} (net total ".Money::format($total).').';
        if ($skippedNoBasic > 0) {
            $message .= " {$skippedNoBasic} staff skipped because their basic salary is 0.";
        }

        return redirect()->route('admin.payroll.index', ['month' => $data['month']])->with('success', $message);
    }

    public function pay(Request $request, SalarySlip $slip)
    {
        $data = $request->validate($this->payRules());

        if ($slip->status !== 'generated') {
            return back()->withErrors(['pay' => 'This slip is already paid.']);
        }

        $slip->update($data + ['status' => 'paid', 'paid_by' => auth()->id()]);

        return back()->with('success', 'Marked as paid.')->with('print_slip', $slip->id);
    }

    public function payAll(Request $request)
    {
        $data = $request->validate($this->payRules() + ['month' => ['required', 'regex:'.self::MONTH_RULE]]);

        $query = SalarySlip::where('month', $data['month'])->where('status', 'generated');
        $count = (clone $query)->count();
        $total = (float) (clone $query)->sum('net_pay');

        if ($count === 0) {
            return back()->withErrors(['pay' => 'There are no unpaid slips for that month.']);
        }

        $query->update([
            'status' => 'paid',
            'paid_on' => $data['paid_on'],
            'payment_mode' => $data['payment_mode'],
            'reference_no' => $data['reference_no'] ?? null,
            'paid_by' => auth()->id(),
        ]);

        return back()->with('success', "{$count} slip(s) marked as paid (total ".Money::format($total).').');
    }

    public function unpay(SalarySlip $slip)
    {
        if ($slip->status !== 'paid') {
            return back();
        }

        $slip->update(['status' => 'generated', 'paid_on' => null, 'payment_mode' => null, 'reference_no' => null, 'paid_by' => null]);

        return back()->with('success', 'Payment undone. The slip is back to Generated.');
    }

    public function destroy(SalarySlip $slip)
    {
        if ($slip->status !== 'generated') {
            return back()->withErrors(['slip' => 'A paid slip cannot be deleted. Undo the payment first.']);
        }

        $slip->delete(); // soft delete: the slip number is never reused

        return back()->with('success', 'Salary slip deleted.');
    }

    /** Printable slip (one). */
    public function slip(SalarySlip $slip)
    {
        $slips = collect([$slip->load('lines', 'staff')]);

        return view('admin.payroll.slip', compact('slips'));
    }

    /** Printable slips for a whole month (one page each). */
    public function printMonth(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'regex:'.self::MONTH_RULE]]);

        $slips = SalarySlip::with('lines', 'staff')->where('month', $data['month'])->orderBy('staff_name')->get();

        if ($slips->isEmpty()) {
            return back()->withErrors(['slip' => 'There are no slips for that month.']);
        }

        return view('admin.payroll.slip', compact('slips'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $tab = in_array($request->tab, SalarySlip::STATUSES, true) ? $request->tab : 'all';
        $rows = $this->filtered($request)
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->orderByDesc('month')->orderBy('staff_name')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, ['Slip No', 'Month', 'Staff', 'Designation', 'Type', 'Basic', 'Allowances', 'Deductions', 'Net Pay',
                'Status', 'Paid On', 'Mode', 'Reference', 'Bank', 'Account No', 'PAN']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->slip_no,
                    $r->month,
                    $r->staff_name,
                    $r->designation,
                    StaffMember::TYPES[$r->staff->type ?? ''] ?? '',
                    $r->basic,
                    round($r->gross - $r->basic, 2),
                    $r->total_deductions,
                    $r->net_pay,
                    $r->status,
                    $r->paid_on?->format('Y-m-d'),
                    Money::MODES[$r->payment_mode] ?? '',
                    $r->reference_no,
                    $r->staff->bank_name ?? '',
                    $r->staff->bank_account ?? '',
                    $r->staff->pan_no ?? '',
                ]);
            }

            fclose($out);
        }, 'salary-slips-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------------------------------------------------------------------

    private function createSlip(StaffMember $staff, string $month, float $basic, int $workingDays, float $unpaid, array $calc, ?string $remarks): SalarySlip
    {
        $slip = SalarySlip::create([
            'slip_no' => SalarySlip::nextNumber($month),
            'staff_member_id' => $staff->id,
            'month' => $month,
            'staff_name' => $staff->name,
            'designation' => $staff->designation,
            'basic' => $basic,
            'working_days' => $workingDays,
            'unpaid_days' => $unpaid,
            'gross' => $calc['gross'],
            'total_deductions' => $calc['total_deductions'],
            'net_pay' => $calc['net_pay'],
            'status' => 'generated',
            'remarks' => $remarks,
            'created_by' => auth()->id(),
        ]);

        $slip->lines()->createMany($calc['lines']);

        return $slip;
    }

    /** @return array|string the calculation, or an error message */
    private function calculate(array $data)
    {
        $unpaid = (float) ($data['unpaid_days'] ?? 0);

        if ($unpaid > (int) $data['working_days']) {
            return 'Unpaid leave days cannot be more than the working days.';
        }

        $lines = array_merge(
            $this->lines($data['earnings'] ?? [], 'earning'),
            $this->lines($data['deductions'] ?? [], 'deduction')
        );

        $calc = SalarySlip::compute((float) $data['basic'], $lines, (int) $data['working_days'], $unpaid);

        return $calc['net_pay'] < 0 ? 'Deductions are more than the earnings, so the net pay would be negative.' : $calc;
    }

    private function lines(array $rows, string $type): array
    {
        $out = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $amount = round((float) ($row['amount'] ?? 0), 2);

            if ($name !== '' && $amount > 0) {
                $out[] = ['type' => $type, 'name' => $name, 'amount' => $amount];
            }
        }

        return $out;
    }

    private function rules(): array
    {
        return [
            'basic' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'working_days' => ['required', 'integer', 'min:1', 'max:31'],
            'unpaid_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'earnings' => ['nullable', 'array'],
            'earnings.*.name' => ['nullable', 'string', 'max:100'],
            'earnings.*.amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'deductions' => ['nullable', 'array'],
            'deductions.*.name' => ['nullable', 'string', 'max:100'],
            'deductions.*.amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function payRules(): array
    {
        return [
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_mode' => ['required', Rule::in(array_keys(Money::MODES))],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ];
    }
}
