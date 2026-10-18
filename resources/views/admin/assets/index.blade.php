@extends('layouts.admin')

@section('title', 'Inventory & Assets')

@section('content')
    <div class="max-w-7xl mx-auto">

        <!-- Success/Error Notifications -->
        @if (session('success'))
            <div
                class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded relative text-sm print:hidden">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div
                class="mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded relative text-sm print:hidden">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">

            <!-- Top Header & Add Button -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">School Inventory Records</h3>
                    <p class="text-xs text-gray-500">Manage library books, robotics gear, lab instruments, and
                        institutional equipment.</p>
                </div>
                <button type="button" onclick="document.getElementById('addAssetModal').classList.remove('hidden')"
                    class="print:hidden inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Item
                </button>
            </div>

            <!-- Barcode Quick Scan Return Bar -->
            <div
                class="mb-6 bg-slate-900 text-white p-4 rounded-xl shadow-lg border border-slate-800 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-indigo-600/30 text-indigo-400 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v1M12 20v-1M4 12h1M20 12h-1M6.343 6.343l.707.707M16.95 16.95l.707.707M6.343 17.657l.707-.707M16.95 7.05l.707-.707">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm">Quick Barcode Return</h4>
                        <p class="text-xs text-slate-400">Scan any book/item barcode to mark it returned instantly.</p>
                    </div>
                </div>

                <!-- Scanner Form -->
                <form action="{{ route('admin.assets.barcode-return') }}" method="POST"
                    class="w-full md:w-auto flex items-center gap-2">
                    @csrf
                    <div class="relative w-full md:w-80">
                        <input type="text" id="barcodeScannerInput" name="asset_tag"
                            placeholder="Click or scan barcode here..." autofocus required
                            class="w-full pl-9 pr-4 py-2 text-xs bg-slate-800 border border-slate-700 text-white rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 placeholder-slate-500 font-mono">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 10h18M3 14h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z">
                                </path>
                            </svg>
                        </span>
                    </div>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg transition shadow-md">
                        Return
                    </button>
                </form>
            </div>
            <script>
                // Keep focus on the barcode input box for hands-free scanning
                document.addEventListener("DOMContentLoaded", function() {
                    const barcodeInput = document.getElementById('barcodeScannerInput');

                    // Auto refocus if user clicks outside unless typing in another field
                    document.addEventListener('click', function(e) {
                        // Don't steal focus while a modal is open
                        if (document.querySelector('#issueModal:not(.hidden), #addAssetModal:not(.hidden), #multiLoanModal')) {
                            return;
                        }
                        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT' && e.target.tagName !==
                            'TEXTAREA') {
                            barcodeInput.focus();
                        }
                    });
                });
            </script>
            <!-- Category Tabs -->
            @php
                $currentTab = $tab ?? 'library';
                $tabs = [
                    'library' => 'Library Books',
                    'robotics' => 'Robotics',
                    'electronics' => 'Electronics',
                    'chemistry' => 'Chemistry Lab',
                    'physics' => 'Physics Lab',
                    'general' => 'General Assets',
                ];
            @endphp

            <div class="flex flex-wrap gap-2 mb-6 border-b pb-4 print:hidden">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('admin.assets.index', ['tab' => $key]) }}"
                        class="px-4 py-2 text-xs font-bold rounded-md transition {{ $currentTab === $key ? 'bg-indigo-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ strtoupper($label) }} ({{ $counts[$key] ?? 0 }})
                    </a>
                @endforeach
            </div>

            <!-- Toolbar: Export, Entries & Search -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-4 print:hidden">

                <!-- Left Side -->
                <div class="flex flex-wrap items-center space-x-2 w-full md:w-auto">
                    <a href="{{ route('admin.assets.csv', ['tab' => $currentTab]) }}"
                        class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded shadow transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        CSV
                    </a>

                    <!-- Print / Save as PDF (browser print dialog) -->
                    <button type="button" onclick="window.print()"
                        class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded shadow transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                            </path>
                        </svg>
                        PRINT / PDF
                    </button>

                    <!-- Per Page Dropdown (keeps search) -->
                    <form method="GET" action="{{ route('admin.assets.index') }}"
                        class="inline-flex items-center space-x-1 ml-4 text-xs text-gray-600">
                        <input type="hidden" name="tab" value="{{ $currentTab }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <span>Show</span>
                        <select name="per_page" onchange="this.form.submit()"
                            class="form-select text-xs border-gray-300 rounded-md py-1 px-2">
                            @foreach ([10, 25, 50] as $n)
                                <option value="{{ $n }}"
                                    {{ (int) request('per_page', 10) === $n ? 'selected' : '' }}>{{ $n }}
                                </option>
                            @endforeach
                        </select>
                        <span>entries</span>
                    </form>
                </div>

                <!-- Right Side: Search (keeps per_page) -->
                <div class="w-full md:w-auto">
                    <form method="GET" action="{{ route('admin.assets.index') }}" class="flex items-center">
                        <input type="hidden" name="tab" value="{{ $currentTab }}">
                        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                        <div class="relative w-full">
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Search items..."
                                class="w-full md:w-64 pl-9 pr-4 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-indigo-500">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Data Table -->
            <div class="overflow-x-auto border rounded-lg">
                <table id="export-table" class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white">
                        <tr>
                            <th class="px-4 py-3 font-semibold uppercase tracking-wider">Tag ID</th>
                            <th class="px-4 py-3 font-semibold uppercase tracking-wider">Item Name</th>
                            <th class="px-4 py-3 font-semibold uppercase tracking-wider">Location</th>
                            <th class="px-4 py-3 font-semibold uppercase tracking-wider">Qty / Avail</th>
                            <th colspan="2" class="px-4 py-3 font-semibold uppercase tracking-wider">Issued To / Due
                                Date</th>
                            <th class="px-4 py-3 font-semibold uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 font-semibold uppercase tracking-wider text-right print:hidden">Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($assets as $item)
                            @php
                                $loans = $item->loans ?? collect();
                                $hasOverdue = $loans->contains(
                                    fn($l) => $l->due_date && \Carbon\Carbon::parse($l->due_date)->isPast(),
                                );
                                // Item shows units out, but has no loan rows (older records)
                                $legacy = $loans->isEmpty() && $item->available_quantity < $item->quantity;
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition align-top">
                                <!-- Tag & Item Details -->
                                <td class="px-4 py-3 font-mono text-gray-700 font-semibold">{{ $item->asset_tag }}</td>
                                <td class="px-4 py-3 font-bold text-gray-900">{{ $item->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $item->location }}</td>

                                <!-- Quantity -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-bold text-gray-900">{{ $item->quantity }}</span>
                                    <span class="text-gray-400">/</span>
                                    <span class="font-bold text-emerald-600">{{ $item->available_quantity }}
                                        available</span>
                                </td>

                                <!-- Active borrowers, due dates and Return buttons side by side -->
                                <td colspan="2" class="px-4 py-2">
                                    @if ($loans->isNotEmpty())
                                        <div class="space-y-1.5">
                                            @foreach ($loans as $loan)
                                                @php $late = $loan->due_date && \Carbon\Carbon::parse($loan->due_date)->isPast(); @endphp
                                                <div
                                                    class="flex items-center justify-between gap-3 bg-slate-50 p-1.5 rounded border border-slate-100">
                                                    <div>
                                                        <span class="font-bold text-indigo-900 block">
                                                            {{ $loan->student?->first_name }}
                                                            {{ $loan->student?->last_name }}
                                                        </span>
                                                        <span class="text-[10px] text-gray-400">
                                                            Issued: {{ $loan->issue_date }} &middot; Due:
                                                            <strong
                                                                class="{{ $late ? 'text-rose-600' : 'text-gray-600' }}">{{ $loan->due_date }}</strong>
                                                            @if ($late)
                                                                <span
                                                                    class="ml-1 px-1 bg-rose-100 text-rose-800 text-[9px] rounded font-bold">OVERDUE</span>
                                                            @endif
                                                        </span>
                                                    </div>

                                                    <form action="{{ route('admin.assets.return', $item->id) }}"
                                                        method="POST" class="inline print:hidden"
                                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from('Return item from ' . ($loan->student?->first_name ?? 'this student') . '?') }});">
                                                        @csrf
                                                        <input type="hidden" name="loan_id"
                                                            value="{{ $loan->id }}">
                                                        <button type="submit"
                                                            class="text-emerald-700 hover:bg-emerald-100 bg-emerald-50 border border-emerald-200 font-bold text-[10px] px-2 py-0.5 rounded shadow-sm transition">
                                                            Return
                                                        </button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif ($legacy)
                                        <div
                                            class="flex items-center justify-between gap-3 bg-amber-50 p-1.5 rounded border border-amber-100">
                                            <span class="text-amber-700 font-semibold">Issued (Legacy Record)</span>
                                            <form action="{{ route('admin.assets.return', $item->id) }}" method="POST"
                                                class="inline print:hidden"
                                                onsubmit="return confirm('Restore item back to inventory?');">
                                                @csrf
                                                <button type="submit"
                                                    class="text-emerald-700 hover:bg-emerald-100 bg-emerald-50 border border-emerald-200 font-bold text-[10px] px-2 py-0.5 rounded shadow-sm transition">
                                                    Return Item
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-gray-400 italic">No active borrowers</span>
                                    @endif
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3">
                                    @if ($hasOverdue)
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">OVERDUE</span>
                                    @elseif ($item->available_quantity <= 0)
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800">FULLY
                                            ISSUED</span>
                                    @elseif ($loans->isNotEmpty() || $legacy)
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">PARTLY
                                            ISSUED</span>
                                    @else
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">AVAILABLE</span>
                                    @endif
                                </td>

                                <!-- Primary Actions -->
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 print:hidden">
                                    @if ($item->available_quantity > 0)
                                        <button type="button"
                                            onclick="openIssueModal({{ $item->id }}, @js($item->name))"
                                            class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded shadow transition">
                                            + Issue
                                        </button>
                                    @endif

                                    <form action="{{ route('admin.assets.destroy', $item->id) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Delete asset?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-1 text-gray-400 hover:text-rose-600 font-bold transition"
                                            title="Delete Asset" aria-label="Delete asset">
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
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                    No inventory records found in this category.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination (keeps tab, search, per_page) -->
            <div class="mt-4 print:hidden">
                {{ $assets->withQueryString()->links() }}
            </div>

        </div>
    </div>

    <!-- Multiple Loans Return Resolution Modal (opens after a barcode scan matches several borrowers) -->
    @if (session('active_loans_prompt'))
        <div id="multiLoanModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm print:hidden">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 border border-gray-100">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <div>
                        <h3 class="text-md font-bold text-gray-900">Select Returning Student</h3>
                        <p class="text-xs text-gray-500">Item barcode: <span
                                class="font-mono font-bold text-indigo-600">{{ session('multiple_loans_tag') }}</span>
                            has multiple active borrowers.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('multiLoanModal').remove()"
                        class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
                </div>

                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 p-2.5 rounded-lg mb-4">
                    ⚠️ Choose the exact student handing back the book to prevent queue mismatches:
                </p>

                <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                    @foreach (session('active_loans_prompt') as $loan)
                        @php
                            // Works whether the controller flashed models or plain arrays
                            $fmt = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('Y-m-d') : '—';
                            $loanId = data_get($loan, 'id');
                            $assetId = data_get($loan, 'asset_id');
                        @endphp
                        <div
                            class="flex items-center justify-between gap-3 bg-gray-50 hover:bg-indigo-50/50 p-3 rounded-lg border border-gray-200 transition">
                            <div>
                                <span class="font-bold text-gray-900 block text-xs">
                                    {{ data_get($loan, 'student.first_name') }} {{ data_get($loan, 'student.last_name') }}
                                    <span class="text-gray-500 font-normal">(Roll:
                                        {{ data_get($loan, 'student.roll_number') ?? 'N/A' }})</span>
                                </span>
                                <span class="text-[10px] text-gray-400">Issued: {{ $fmt(data_get($loan, 'issue_date')) }} |
                                    Due: {{ $fmt(data_get($loan, 'due_date')) }}</span>
                            </div>

                            <form action="{{ route('admin.assets.return', $assetId) }}" method="POST">
                                @csrf
                                <input type="hidden" name="loan_id" value="{{ $loanId }}">
                                <button type="submit"
                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-md shadow transition">
                                    Confirm Return
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 text-right">
                    <button type="button" onclick="document.getElementById('multiLoanModal').remove()"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold rounded-lg transition">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Add Item Modal -->
    <div id="addAssetModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden print:hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-lg font-bold text-gray-900">Add New Inventory Item</h3>
                <button type="button" onclick="document.getElementById('addAssetModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>

            <form action="{{ route('admin.assets.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="category" value="{{ $currentTab }}">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Item Name / Title</label>
                    <input type="text" name="name" required
                        class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Location / Shelf</label>
                        <input type="text" name="location" required placeholder="e.g. Shelf A-3, Room 101"
                            class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Quantity</label>
                        <input type="number" name="quantity" min="1" value="1" required
                            class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Purchase Date</label>
                        <input type="date" name="purchase_date"
                            class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Cost (NPR)</label>
                        <input type="number" step="0.01" name="cost" min="0"
                            class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t">
                    <button type="button" onclick="document.getElementById('addAssetModal').classList.add('hidden')"
                        class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-bold rounded-md">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow">Save
                        Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Issue Item Modal (searchable student picker, Alpine.js) -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    <div id="issueModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden print:hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold text-gray-900">Issue Item to Student</h3>
                <button type="button" onclick="closeIssueModal()"
                    class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>

            <form id="issueForm" method="POST" class="space-y-4"
                x-data="studentPicker(@js($students->map(fn($s) => ['id' => (string) $s->id, 'name' => trim($s->first_name . ' ' . $s->last_name), 'roll' => (string) ($s->roll_number ?? 'N/A')])->values()))"
                @reset-student.window="clear()"
                @submit="if (!selectedId) { $event.preventDefault(); open = true; $refs.search.focus(); }">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Item Title</label>
                    <input type="text" id="issueItemTitle" disabled
                        class="w-full text-xs bg-gray-100 border-gray-300 rounded-md">
                </div>

                <!-- Searchable student picker -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Search &amp; Select Student</label>
                    <input type="hidden" name="student_id" :value="selectedId">

                    <div class="relative" @click.outside="open = false">
                        <input type="text" x-ref="search" x-model="search" x-show="!selectedId"
                            @focus="open = true" @input="open = true" @keydown.escape="open = false"
                            @keydown.enter.prevent="filtered[0] && pick(filtered[0])" autocomplete="off"
                            placeholder="Type student name or roll number..."
                            class="w-full text-xs border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">

                        <!-- Selected student -->
                        <div x-cloak x-show="selectedId"
                            class="flex items-center justify-between bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold px-3 py-2 rounded-md">
                            <span x-text="selectedLabel"></span>
                            <button type="button" @click="clear(); $nextTick(() => $refs.search.focus())"
                                class="text-rose-600 hover:underline font-semibold">&times; Change</button>
                        </div>

                        <!-- Results -->
                        <div x-cloak x-show="open && !selectedId"
                            class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-48 overflow-y-auto text-xs">
                            <template x-for="student in filtered" :key="student.id">
                                <button type="button" @click="pick(student)"
                                    class="w-full text-left p-2.5 hover:bg-indigo-50 border-b border-gray-100 flex justify-between items-center transition">
                                    <span class="font-bold text-gray-800" x-text="student.name"></span>
                                    <span class="text-gray-400 font-mono text-[10px]"
                                        x-text="'Roll: ' + student.roll"></span>
                                </button>
                            </template>
                            <div x-show="filtered.length === 0" class="p-3 text-gray-400 text-center">
                                No matching students.
                            </div>
                        </div>
                    </div>
                    <p x-cloak x-show="!selectedId && open && filtered.length === 50"
                        class="text-[10px] text-gray-400 mt-1">Showing the first 50 matches. Keep typing to narrow down.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Return Due Date</label>
                    <input type="date" name="due_date" value="{{ now()->addDays(14)->format('Y-m-d') }}" required
                        class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                    <span class="text-[10px] text-gray-400">Default loan period is set to 2 weeks (14 days).</span>
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t">
                    <button type="button" onclick="closeIssueModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-bold rounded-md">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-md shadow">Confirm
                        Issue</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Alpine component: filter students by name or roll number
        function studentPicker(students) {
            return {
                students: students,
                search: '',
                selectedId: '',
                selectedLabel: '',
                open: false,
                get filtered() {
                    const q = this.search.trim().toLowerCase();
                    const list = q === '' ? this.students : this.students.filter(s =>
                        s.name.toLowerCase().includes(q) || s.roll.toLowerCase().includes(q));
                    return list.slice(0, 50);
                },
                pick(s) {
                    this.selectedId = s.id;
                    this.selectedLabel = s.name + ' (Roll: ' + s.roll + ')';
                    this.search = '';
                    this.open = false;
                },
                clear() {
                    this.selectedId = '';
                    this.selectedLabel = '';
                    this.search = '';
                }
            };
        }

        function openIssueModal(assetId, assetTitle) {
            document.getElementById('issueItemTitle').value = assetTitle;
            document.getElementById('issueForm').action = @js(url('/admin/assets')) + '/' + assetId + '/issue';
            window.dispatchEvent(new CustomEvent('reset-student')); // clear any earlier selection
            document.getElementById('issueModal').classList.remove('hidden');
        }

        function closeIssueModal() {
            document.getElementById('issueModal').classList.add('hidden');
        }
    </script>
@endsection