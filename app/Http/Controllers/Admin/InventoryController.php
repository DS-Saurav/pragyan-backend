<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\SchoolClass; // Adjust to your actual class model name if different
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private const CATEGORIES = ['stationery', 'housekeeping', 'office', 'general'];

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $query = InventoryItem::query();

        if ($tab === 'low_stock') {
            $query->whereRaw('quantity <= reorder_level');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $items = $query->latest()->paginate(10)->withQueryString();

        $counts = [
            'all' => InventoryItem::count(),
            'low_stock' => InventoryItem::whereRaw('quantity <= reorder_level')->count(),
        ];

        // Query active teachers and classes
        $teachers = Teacher::where('status', 'active')->orderBy('first_name')->get();
        $classes = class_exists(SchoolClass::class) ? SchoolClass::all() : collect();

        return view('admin.inventory.index', compact('items', 'counts', 'tab', 'teachers', 'classes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'initial_quantity' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'supplier' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $item = InventoryItem::create([
                'name' => $validated['name'],
                'category' => $validated['category'],
                'quantity' => $validated['initial_quantity'],
                'reorder_level' => $validated['reorder_level'],
                'unit' => $validated['unit'],
            ]);

            if ($validated['initial_quantity'] > 0) {
                InventoryMovement::create([
                    'inventory_item_id' => $item->id,
                    'type' => 'in',
                    'quantity' => $validated['initial_quantity'],
                    'balance_after' => $validated['initial_quantity'],
                    'supplier' => $validated['supplier'] ?? 'Initial Stock',
                    'note' => 'Initial stock setup',
                    'user_id' => auth()->id(),
                ]);
            }
        });

        return back()->with('success', 'Inventory item created with movement ledger initialized!');
    }

    public function restock(Request $request, InventoryItem $inventoryItem)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'supplier' => 'nullable|string|max:255',
            'bill_number' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $inventoryItem) {
            $inventoryItem->lockForUpdate();
            $newBalance = $inventoryItem->quantity + $validated['quantity'];

            $inventoryItem->update(['quantity' => $newBalance]);

            InventoryMovement::create([
                'inventory_item_id' => $inventoryItem->id,
                'type' => 'in',
                'quantity' => $validated['quantity'],
                'balance_after' => $newBalance,
                'supplier' => $validated['supplier'],
                'bill_number' => $validated['bill_number'],
                'note' => $validated['note'] ?? 'Restocked item',
                'user_id' => auth()->id(),
            ]);
        });

        return back()->with('success', "Successfully restocked {$validated['quantity']} {$inventoryItem->unit}(s) of {$inventoryItem->name}.");
    }

    public function dispense(Request $request, InventoryItem $inventoryItem)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'recipient_type' => 'required|string', // Teacher, Class, Department
            'recipient_name' => 'required|string',
            'note' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $inventoryItem) {
            $inventoryItem->lockForUpdate();

            if ($validated['quantity'] > $inventoryItem->quantity) {
                abort(422, 'Cannot dispense more than available stock!');
            }

            $newBalance = $inventoryItem->quantity - $validated['quantity'];
            $inventoryItem->update(['quantity' => $newBalance]);

            InventoryMovement::create([
                'inventory_item_id' => $inventoryItem->id,
                'type' => 'out',
                'quantity' => $validated['quantity'],
                'balance_after' => $newBalance,
                'recipient_type' => $validated['recipient_type'],
                'recipient_name' => $validated['recipient_name'],
                'note' => $validated['note'],
                'user_id' => auth()->id(),
            ]);
        });

        return back()->with('success', "Dispensed {$validated['quantity']} {$inventoryItem->unit}(s) to {$validated['recipient_name']}.");
    }

    public function show(InventoryItem $inventoryItem)
    {
        $inventoryItem->load('movements.user');

        return response()->json($inventoryItem);
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'reorder_level' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
        ]);

        $inventoryItem->update($validated);

        return back()->with('success', 'Inventory item details updated successfully!');
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return back()->with('success', 'Inventory item removed successfully!');
    }
}
