@extends('layouts.admin')

@section('content')
    @php
        $tab = $tab ?? 'all';
        $categories = [
            'stationery' => 'Stationery',
            'housekeeping' => 'Housekeeping',
            'office' => 'Office',
            'general' => 'General',
        ];
        $inputClass = 'w-full text-xs border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-500';
        $readonlyClass = 'w-full text-xs bg-gray-100 border border-gray-300 rounded-lg p-2.5 text-gray-600 font-medium';
        $labelClass = 'block text-xs font-bold text-gray-700 uppercase mb-1';
        $modalBackdrop =
            'hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm p-4';
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Notifications --}}
        @if (session('success'))
            <div
                class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        Stock / Inventory Ledger
                    </h3>
                    <p class="text-xs text-gray-500">
                        Track supplies, restocks, who received what, and a full history for every item.
                    </p>
                </div>
                <button type="button" onclick="toggleModal('addInventoryModal', true)"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Item
                </button>
            </div>

            {{-- Filter tabs --}}
            <div class="flex flex-wrap gap-2 mb-6 border-b pb-4">
                <a href="{{ route('admin.inventory.index', ['tab' => 'all']) }}"
                    class="px-5 py-2 text-xs font-bold rounded-lg transition {{ $tab === 'all' ? 'bg-slate-900 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    ALL ITEMS ({{ $counts['all'] ?? 0 }})
                </a>
                <a href="{{ route('admin.inventory.index', ['tab' => 'low_stock']) }}"
                    class="px-5 py-2 text-xs font-bold rounded-lg transition flex items-center gap-2 {{ $tab === 'low_stock' ? 'bg-rose-600 text-white shadow' : 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200' }}">
                    <span>LOW STOCK ({{ $counts['low_stock'] ?? 0 }})</span>
                    @if (($counts['low_stock'] ?? 0) > 0)
                        <span class="w-2 h-2 rounded-full bg-rose-400 animate-ping"></span>
                    @endif
                </a>
            </div>

            {{-- Search --}}
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
                <form method="GET" action="{{ route('admin.inventory.index') }}"
                    class="w-full md:w-auto flex items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="relative w-full md:w-72">
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Search items or categories..."
                            class="w-full pl-9 pr-4 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </span>
                    </div>
                    <button type="submit"
                        class="px-3 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition">
                        Search
                    </button>
                    @if (request('search'))
                        <a href="{{ route('admin.inventory.index', ['tab' => $tab]) }}"
                            class="px-3 py-2 text-xs font-bold text-gray-500 hover:text-gray-700">Clear</a>
                    @endif
                </form>
            </div>

            {{-- Data table --}}
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Item Name</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">In Stock</th>
                            <th class="px-4 py-3">Reorder Level</th>
                            <th class="px-4 py-3">Stock Health</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($items as $item)
                            @php
                                $isLowStock = $item->quantity <= $item->reorder_level;
                                $itemData = $item->only([
                                    'id',
                                    'name',
                                    'category',
                                    'quantity',
                                    'unit',
                                    'reorder_level',
                                ]);
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition {{ $isLowStock ? 'bg-rose-50/30' : '' }}">
                                <td class="px-4 py-3 font-bold text-gray-900">
                                    <button type="button" onclick="openHistoryModal({{ $item->id }})"
                                        class="text-indigo-600 hover:underline font-bold text-left"
                                        title="View movement history">
                                        {{ $item->name }}
                                    </button>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100 uppercase">
                                        {{ $item->category }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-bold {{ $isLowStock ? 'text-rose-600' : 'text-gray-800' }}">
                                    {{ $item->quantity }} {{ $item->unit }}
                                    @if ($isLowStock)
                                        <span class="ml-1" title="Low stock alert">⚠️</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500 font-medium">
                                    {{ $item->reorder_level }} {{ $item->unit }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($isLowStock)
                                        <span
                                            class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200 uppercase">
                                            Reorder now
                                        </span>
                                    @else
                                        <span
                                            class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase">
                                            OK
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                                    {{-- Restock --}}
                                    <button type="button" onclick="openRestockModal(this)"
                                        data-item="{{ json_encode($itemData) }}"
                                        class="p-1 text-gray-400 hover:text-emerald-600 transition"
                                        title="Receive / restock">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </button>

                                    {{-- Dispense --}}
                                    <button type="button" onclick="openDispenseModal(this)"
                                        data-item="{{ json_encode($itemData) }}"
                                        class="p-1 text-gray-400 hover:text-amber-600 transition"
                                        title="Dispense to recipient">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 12H4"></path>
                                        </svg>
                                    </button>

                                    {{-- Edit --}}
                                    <button type="button" onclick="openEditModal(this)"
                                        data-item="{{ json_encode($itemData) }}"
                                        class="p-1 text-gray-400 hover:text-indigo-600 transition" title="Edit item">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                    </button>

                                    {{-- Delete --}}
                                    <form action="{{ route('admin.inventory.destroy', $item->id) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Delete this item and its history?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-gray-400 hover:text-rose-600 transition"
                                            title="Delete item">
                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    @if (request('search'))
                                        No items match "{{ request('search') }}".
                                    @elseif ($tab === 'low_stock')
                                        No items are below their reorder level.
                                    @else
                                        No inventory items yet. Use Add Item to create the first one.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $items->withQueryString()->links() }}
            </div>

        </div>
    </div>

    {{-- ================= MODALS ================= --}}

    {{-- Add Item --}}
    <div id="addInventoryModal" class="{{ $modalBackdrop }}">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 border border-gray-100">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold text-gray-900">Add Inventory Item</h3>
                <button type="button" onclick="toggleModal('addInventoryModal', false)"
                    class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>
            <form action="{{ route('admin.inventory.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Item Name</label>
                    <input type="text" name="name" required placeholder="e.g. A4 Paper, Chalk Boxes"
                        class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Category</label>
                    <select name="category" required class="{{ $inputClass }} uppercase">
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Opening Quantity</label>
                        <input type="number" name="initial_quantity" min="0" required
                            class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Unit</label>
                        <input type="text" name="unit" required placeholder="pack, box, litre"
                            class="{{ $inputClass }}">
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Reorder Level</label>
                    <input type="number" name="reorder_level" min="0" required
                        placeholder="Alert when quantity reaches..." class="{{ $inputClass }}">
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="toggleModal('addInventoryModal', false)"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                        Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Item (details only; stock changes go through Restock / Dispense) --}}
    <div id="editInventoryModal" class="{{ $modalBackdrop }}">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 border border-gray-100">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold text-gray-900">Edit Item Details</h3>
                <button type="button" onclick="toggleModal('editInventoryModal', false)"
                    class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>
            <form id="editForm" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="{{ $labelClass }}">Item Name</label>
                    <input type="text" id="edit_name" name="name" required class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Category</label>
                    <select id="edit_category" name="category" required class="{{ $inputClass }} uppercase">
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Unit</label>
                        <input type="text" id="edit_unit" name="unit" required class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Reorder Level</label>
                        <input type="number" id="edit_reorder_level" name="reorder_level" min="0" required
                            class="{{ $inputClass }}">
                    </div>
                </div>
                <p class="text-[11px] text-gray-500">
                    To change the quantity in stock, use Restock or Dispense so the history stays accurate.
                </p>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="toggleModal('editInventoryModal', false)"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Restock --}}
    <div id="restockModal" class="{{ $modalBackdrop }}">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 border border-gray-100">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold text-gray-900">Receive / Restock</h3>
                <button type="button" onclick="toggleModal('restockModal', false)"
                    class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>
            <form id="restockForm" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Item</label>
                    <input type="text" id="restock_name" disabled class="{{ $readonlyClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Quantity to Add</label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="restock_qty" name="quantity" min="1" required
                            class="{{ $inputClass }}">
                        <span id="restock_unit" class="text-xs font-bold text-gray-500"></span>
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Supplier</label>
                    <input type="text" name="supplier" placeholder="e.g. Stationers Hub"
                        class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Bill / Invoice Number</label>
                    <input type="text" name="bill_number" class="{{ $inputClass }}">
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="toggleModal('restockModal', false)"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                        Confirm Restock
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Dispense --}}
    <div id="dispenseModal" class="{{ $modalBackdrop }}">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 border border-gray-100">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold text-gray-900">Dispense to Recipient</h3>
                <button type="button" onclick="toggleModal('dispenseModal', false)"
                    class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>
            <form id="dispenseForm" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Item</label>
                        <input type="text" id="dispense_name" disabled class="{{ $readonlyClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Available</label>
                        <input type="text" id="dispense_available" disabled class="{{ $readonlyClass }}">
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Quantity to Dispense</label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="dispense_qty" name="quantity" min="1" required
                            class="{{ $inputClass }}">
                        <span id="dispense_unit" class="text-xs font-bold text-gray-500"></span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Recipient Type</label>
                        <select name="recipient_type" id="recipient_type" onchange="fillRecipients()"
                            class="{{ $inputClass }}">
                            <option value="Teacher">Teacher</option>
                            <option value="Class">Class / Section</option>
                            <option value="Department">Department / Office</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Recipient</label>
                        <select name="recipient_name" id="recipient_select" required
                            class="{{ $inputClass }}"></select>
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Note / Purpose</label>
                    <input type="text" name="note" maxlength="255" placeholder="e.g. For terminal exam papers"
                        class="{{ $inputClass }}">
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="toggleModal('dispenseModal', false)"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow transition">
                        Confirm Dispense
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Movement history --}}
    <div id="historyModal" class="{{ $modalBackdrop }}">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 border border-gray-100 max-h-[85vh] flex flex-col">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 id="history_title" class="text-md font-bold text-gray-900">Movement History</h3>
                <button type="button" onclick="toggleModal('historyModal', false)"
                    class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>

            <div class="overflow-auto flex-1">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-100 font-bold text-gray-700 sticky top-0">
                        <tr>
                            <th class="p-2 text-left">Date</th>
                            <th class="p-2 text-left">Type</th>
                            <th class="p-2 text-left">Qty</th>
                            <th class="p-2 text-left">Balance</th>
                            <th class="p-2 text-left">Recipient / Supplier</th>
                            <th class="p-2 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody id="history_body" class="divide-y divide-gray-200"></tbody>
                </table>
            </div>

            <div class="mt-4 text-right">
                <button type="button" onclick="toggleModal('historyModal', false)"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold rounded-lg transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        const teachersData = @json($teachers ?? []);
        const classesData = @json($classes ?? []);
        const departments = ['Exam Section', 'Administration', 'Library', 'Science Lab', 'Office'];
        const modalIds = ['addInventoryModal', 'editInventoryModal', 'restockModal', 'dispenseModal', 'historyModal'];

        const byId = (id) => document.getElementById(id);

        function toggleModal(id, show) {
            byId(id).classList.toggle('hidden', !show);
        }

        function esc(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        // ---------- Edit ----------
        function openEditModal(button) {
            const item = JSON.parse(button.dataset.item);
            byId('editForm').action = `/admin/inventory/${item.id}`;
            byId('edit_name').value = item.name;
            byId('edit_category').value = item.category;
            byId('edit_unit').value = item.unit;
            byId('edit_reorder_level').value = item.reorder_level;
            toggleModal('editInventoryModal', true);
        }

        // ---------- Restock ----------
        function openRestockModal(button) {
            const item = JSON.parse(button.dataset.item);
            byId('restockForm').action = `/admin/inventory/${item.id}/restock`;
            byId('restock_name').value = item.name;
            byId('restock_unit').textContent = item.unit;
            byId('restock_qty').value = '';
            toggleModal('restockModal', true);
            byId('restock_qty').focus();
        }

        // ---------- Dispense ----------
        function openDispenseModal(button) {
            const item = JSON.parse(button.dataset.item);
            byId('dispenseForm').action = `/admin/inventory/${item.id}/dispense`;
            byId('dispense_name').value = item.name;
            byId('dispense_available').value = `${item.quantity} ${item.unit}`;
            byId('dispense_unit').textContent = item.unit;
            byId('dispense_qty').value = '';
            byId('dispense_qty').max = item.quantity;
            fillRecipients();
            toggleModal('dispenseModal', true);
            byId('dispense_qty').focus();
        }

        function fillRecipients() {
            const type = byId('recipient_type').value;
            let names = [];

            if (type === 'Teacher') {
                names = teachersData.map((t) => `${t.first_name} ${t.last_name}`.trim());
            } else if (type === 'Class') {
                names = classesData.map((c) => c.name || 'Class');
            } else {
                names = departments;
            }

            byId('recipient_select').innerHTML = names.length ?
                names.map((n) => `<option value="${esc(n)}">${esc(n)}</option>`).join('') :
                '<option value="">No options available</option>';
        }

        // ---------- History ----------
        async function openHistoryModal(itemId) {
            byId('history_title').textContent = 'Movement History';
            byId('history_body').innerHTML =
                '<tr><td colspan="6" class="p-4 text-center text-gray-500">Loading...</td></tr>';
            toggleModal('historyModal', true);

            try {
                const res = await fetch(`/admin/inventory/${itemId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!res.ok) throw new Error(res.status);
                const data = await res.json();
                renderHistory(data);
            } catch (e) {
                byId('history_body').innerHTML =
                    '<tr><td colspan="6" class="p-4 text-center text-rose-600">Could not load history. Please try again.</td></tr>';
            }
        }

        function renderHistory(data) {
            byId('history_title').textContent = `Movement History: ${data.name}`;
            const rows = data.movements || [];

            if (!rows.length) {
                byId('history_body').innerHTML =
                    '<tr><td colspan="6" class="p-4 text-center text-gray-500">No movements recorded yet.</td></tr>';
                return;
            }

            byId('history_body').innerHTML = rows.map((m) => {
                const isIn = m.type === 'in';
                const badge = isIn ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800';
                const who = m.recipient_name || m.supplier || '-';
                const date = m.created_at ? new Date(m.created_at).toLocaleString() : '-';

                return `
                    <tr>
                        <td class="p-2 text-gray-500 whitespace-nowrap">${esc(date)}</td>
                        <td class="p-2">
                            <span class="px-2 py-0.5 rounded font-bold uppercase text-[10px] ${badge}">
                                ${isIn ? 'Restock' : 'Dispensed'}
                            </span>
                        </td>
                        <td class="p-2 font-bold">${isIn ? '+' : '-'}${esc(m.quantity)}</td>
                        <td class="p-2 font-bold">${esc(m.balance_after)}</td>
                        <td class="p-2">
                            <span class="font-bold">${esc(who)}</span>
                            ${m.recipient_type ? `<span class="text-[10px] text-gray-400 block">${esc(m.recipient_type)}</span>` : ''}
                            ${m.bill_number ? `<span class="text-[10px] text-gray-400 block">Bill ${esc(m.bill_number)}</span>` : ''}
                        </td>
                        <td class="p-2 text-gray-600">${esc(m.note || '-')}</td>
                    </tr>`;
            }).join('');
        }

        // Close any open modal with Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') modalIds.forEach((id) => toggleModal(id, false));
        });
    </script>
@endsection
