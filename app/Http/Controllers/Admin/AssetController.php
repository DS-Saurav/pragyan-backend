<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    private const CATEGORIES = ['robotics', 'electronics', 'chemistry', 'physics', 'general'];

    private const DEFAULT_CATEGORY = 'robotics';

    private const PER_PAGE_OPTIONS = [10, 25, 50];

    public function index(Request $request)
    {
        $tab = $request->get('tab', self::DEFAULT_CATEGORY);

        $query = Asset::query();

        // Filter by category tab
        if (in_array($tab, self::CATEGORIES, true)) {
            $query->where('category', $tab);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Only allow the page sizes offered in the dropdown
        $perPage = (int) $request->get('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        // Eager-load the loans and their students
        $assets = $query->with('loans.student')->latest()->paginate($perPage)->withQueryString();

        // Counts for tabs
        $counts = [];
        foreach (self::CATEGORIES as $category) {
            $counts[$category] = Asset::where('category', $category)->count();
        }

        $students = Student::where('status', 'active')->orderBy('first_name')->get();

        return response()->json(compact('assets', 'counts', 'tab', 'students'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'location' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'purchase_date' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $prefix = strtoupper(substr($validated['category'], 0, 3));
        $validated['asset_tag'] = $this->nextAssetTag($prefix);
        $validated['available_quantity'] = $validated['quantity'];
        $validated['status'] = 'in_use';

        Asset::create($validated);

        return response()->json(["message" => 'Item added to inventory successfully!']);
    }

    // Issue one unit of an item to a student (creates a loan record)
    public function issueItem(Request $request, Asset $asset)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'due_date' => 'required|date|after_or_equal:today',
        ]);

        $issued = DB::transaction(function () use ($request, $asset) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->first();

            if ($asset->available_quantity < 1) {
                return false;
            }

            AssetLoan::create([
                'asset_id' => $asset->id,
                'student_id' => $request->student_id,
                'issue_date' => now(),
                'due_date' => $request->due_date,
                'status' => 'issued',
            ]);

            $remaining = $asset->available_quantity - 1;

            $asset->update([
                'available_quantity' => $remaining,
                'status' => $remaining === 0 ? 'issued' : 'in_use',
            ]);

            return true;
        });

        if (! $issued) {
            return response()->json(["message" => 'No available copies left to issue!']);
        }

        return response()->json(["message" => 'Item issued successfully!']);
    }

    // Return one unit. Works per loan (loan_id) or as a fallback for legacy records.
    public function returnItem(Request $request, Asset $asset)
    {
        $request->validate([
            'loan_id' => 'nullable|integer',
        ]);

        $result = DB::transaction(function () use ($request, $asset) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->first();

            if ($request->filled('loan_id')) {
                $loan = AssetLoan::where('id', $request->loan_id)
                    ->where('asset_id', $asset->id)
                    ->where('status', 'issued')
                    ->first();

                // Already returned (e.g. double click): don't add stock twice
                if (! $loan) {
                    return 'missing';
                }

                $loan->update(['status' => 'returned']);
            } elseif (AssetLoan::where('asset_id', $asset->id)->where('status', 'issued')->exists()) {
                // Has active loans: the return must be done per loan
                return 'needs_loan';
            }

            if ($asset->available_quantity >= $asset->quantity) {
                return 'full';
            }

            $newAvailable = min($asset->quantity, $asset->available_quantity + 1);

            $data = [
                'available_quantity' => $newAvailable,
                'status' => 'in_use',
            ];

            // Clear the old single-borrower fields once nothing is out on loan
            $stillOnLoan = AssetLoan::where('asset_id', $asset->id)->where('status', 'issued')->exists();
            if (! $stillOnLoan) {
                $data['borrowed_by_student_id'] = null;
                $data['issue_date'] = null;
                $data['due_date'] = null;
            }

            $asset->update($data);

            return 'ok';
        });

        return match ($result) {
            'missing' => back()->with('error', 'That loan was already returned.'),
            'needs_loan' => back()->with('error', 'This item has active loans. Return it from the borrower\'s Return button.'),
            'full' => back()->with('error', 'All copies are already in inventory.'),
            default => back()->with('success', 'Item returned to inventory successfully!'),
        };
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return response()->json(["message" => 'Item removed successfully!']);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $tab = $request->get('tab', self::DEFAULT_CATEGORY);
        $assets = Asset::where('category', $tab)->with('loans.student')->latest()->get();
        $filename = "inventory-{$tab}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($assets) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Asset Tag', 'Item Name', 'Category', 'Location', 'Total Qty', 'Available Qty', 'Status', 'Current Borrowers']);
            foreach ($assets as $a) {
                $borrowers = $a->loans
                    ->map(fn ($l) => trim(($l->student?->first_name ?? '').' '.($l->student?->last_name ?? '')))
                    ->filter()
                    ->implode('; ');

                fputcsv($out, [
                    $a->asset_tag,
                    $a->name,
                    strtoupper($a->category),
                    $a->location,
                    $a->quantity,
                    $a->available_quantity,
                    strtoupper($a->status),
                    $borrowers !== '' ? $borrowers : 'N/A',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function nextAssetTag(string $prefix): string
    {
        $last = Asset::where('asset_tag', 'like', "{$prefix}-%")->orderByDesc('id')->value('asset_tag');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return "{$prefix}-".str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
