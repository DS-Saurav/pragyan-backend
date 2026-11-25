@extends('layouts.admin')

@section('title', 'Conduct Register')

@section('content')
    @php
        $activeType = request('type');
        $perPage = (int) request('per_page', 10);

        $conductData = $entries
            ->getCollection()
            ->keyBy('id')
            ->map(function ($e) {
                return [
                    'id' => $e->id,
                    'entry_date' => \Carbon\Carbon::parse($e->entry_date)->format('Y-m-d'),
                    'student_id' => $e->student_id,
                    'type' => $e->type,
                    'points' => $e->points,
                    'note' => $e->note,
                ];
            });

        $inputCls =
            'w-full text-sm border border-gray-300 rounded-lg p-2.5 bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none';
        $labelCls = 'block text-[11px] font-bold uppercase text-gray-600 mb-1';
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Flash messages --}}
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

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 no-print">
            <h3 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                <i class="bi bi-patch-check"></i> Conduct Register
            </h3>
            <button type="button" onclick="openModal('addModal')"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow flex items-center gap-1.5">
                <span class="text-base leading-none">+</span> Add Entry
            </button>
        </div>

        {{-- Filter cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5 no-print">
            <a href="{{ route('admin.conduct.index') }}"
                class="p-4 rounded-xl border flex justify-between items-center transition
                    {{ !$activeType ? 'bg-[#0f1b4c] text-white border-[#0f1b4c] shadow' : 'bg-white text-slate-700 border-gray-200 hover:border-gray-400' }}">
                <span class="text-xs font-bold uppercase tracking-wider">All</span>
                <span
                    class="px-2.5 py-1 rounded-md text-xs font-bold {{ !$activeType ? 'bg-white/20' : 'bg-gray-100' }}">{{ $totalCount }}</span>
            </a>
            <a href="{{ route('admin.conduct.index', ['type' => 'merit']) }}"
                class="p-4 rounded-xl border flex justify-between items-center transition
                    {{ $activeType === 'merit' ? 'bg-emerald-600 text-white border-emerald-600 shadow' : 'bg-white text-emerald-700 border-emerald-200 hover:border-emerald-400' }}">
                <span class="text-xs font-bold uppercase tracking-wider">Merits</span>
                <span
                    class="px-2.5 py-1 rounded-md text-xs font-bold {{ $activeType === 'merit' ? 'bg-white/20' : 'bg-emerald-100' }}">{{ $meritCount }}</span>
            </a>
            <a href="{{ route('admin.conduct.index', ['type' => 'demerit']) }}"
                class="p-4 rounded-xl border flex justify-between items-center transition
                    {{ $activeType === 'demerit' ? 'bg-rose-600 text-white border-rose-600 shadow' : 'bg-white text-rose-700 border-rose-200 hover:border-rose-400' }}">
                <span class="text-xs font-bold uppercase tracking-wider">Demerits</span>
                <span
                    class="px-2.5 py-1 rounded-md text-xs font-bold {{ $activeType === 'demerit' ? 'bg-white/20' : 'bg-rose-100' }}">{{ $demeritCount }}</span>
            </a>
        </div>

        {{-- Table card --}}
        <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">

            <form id="filterForm" method="GET" action="{{ route('admin.conduct.index') }}"
                class="flex flex-wrap items-center justify-between gap-3 mb-4 no-print">
                @if ($activeType)
                    <input type="hidden" name="type" value="{{ $activeType }}">
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="exportCsv()"
                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-md"><i class="bi bi-filetype-pdf"></i>
                        CSV</button>
                    <button type="button" onclick="exportPdf()"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-md"><i class="bi bi-book"></i>
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md"><i class="bi bi-printer"></i>
                        PRINT</button>
                    <span class="text-xs text-gray-500 ml-2">Show</span>
                    <select name="per_page" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-md py-1.5 pl-2 pr-6">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" {{ $perPage === $n ? 'selected' : '' }}>
                                {{ $n }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-gray-500">entries</span>
                </div>

                <div class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
                        class="text-xs border border-gray-300 rounded-md px-3 py-2 w-full sm:w-60 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-md">Search</button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table id="conductTable" class="min-w-full text-left text-xs">
                    <thead class="bg-[#0f1b4c] text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3 rounded-l-lg">Date</th>
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Points</th>
                            <th class="px-4 py-3">Note</th>
                            <th class="px-4 py-3">Recorded By</th>
                            <th class="px-4 py-3 text-right rounded-r-lg no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($entries as $entry)
                            @php $isMerit = $entry->type === 'merit'; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-700">
                                    {{ \Carbon\Carbon::parse($entry->entry_date)->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    {{ trim(($entry->student->first_name ?? '') . ' ' . ($entry->student->last_name ?? '')) ?: '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase
                                        {{ $isMerit ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ $entry->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-extrabold {{ $isMerit ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $isMerit ? '+' : '-' }}{{ $entry->points }}
                                </td>
                                <td class="px-4 py-3 text-gray-700 max-w-xs">{{ $entry->note }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $entry->recorder->name ?? 'System' }}</td>
                                <td class="px-4 py-3 text-right no-print">
                                    <div class="flex items-center justify-end gap-3 text-base">
                                        <button type="button" title="Edit" onclick="openEditModal({{ $entry->id }})"
                                            class="text-amber-500 hover:text-amber-700"><i class="bi bi-pencil"></i></button>
                                        <form action="{{ route('admin.conduct.destroy', $entry->id) }}" method="POST"
                                            onsubmit="return confirm('Delete this conduct record?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete"
                                                class="text-rose-500 hover:text-rose-700"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No conduct entries found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 no-print">
                <span>
                    Showing {{ $entries->firstItem() ?? 0 }} to {{ $entries->lastItem() ?? 0 }}
                    of {{ $entries->total() }} entries
                </span>
                <div>{{ $entries->withQueryString()->links() }}</div>
            </div>
        </div>
    </div>

    {{-- ================= Add modal ================= --}}
    <div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="bg-[#0f1b4c] text-white px-5 py-3 flex justify-between items-center">
                <h3 class="text-sm font-bold">Add — Conduct Register</h3>
                <button type="button" onclick="closeModal('addModal')" class="text-xl leading-none">&times;</button>
            </div>
            <form action="{{ route('admin.conduct.store') }}" method="POST" class="p-5 space-y-3">
                @csrf
                <div>
                    <label class="{{ $labelCls }}">Date</label>
                    <input type="date" name="entry_date" value="{{ date('Y-m-d') }}" required
                        class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Student <span class="text-red-500">*</span></label>
                    <select name="student_id" required class="{{ $inputCls }}">
                        <option value="">Select...</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelCls }}">Type</label>
                        <select name="type" required class="{{ $inputCls }}">
                            <option value="merit">Merit</option>
                            <option value="demerit">Demerit</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Points</label>
                        <input type="number" name="points" value="5" min="1" required
                            class="{{ $inputCls }}">
                    </div>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Note</label>
                    <textarea name="note" rows="3" required placeholder="Enter reason or achievement description..."
                        class="{{ $inputCls }}"></textarea>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Recorded By</label>
                    <input type="text" disabled value="{{ Auth::user()->name ?? 'Admin User' }}"
                        class="w-full text-sm border border-gray-200 rounded-lg p-2.5 bg-gray-50 text-gray-500">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" onclick="closeModal('addModal')"
                        class="px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================= Edit modal ================= --}}
    <div id="editModal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="bg-[#0f1b4c] text-white px-5 py-3 flex justify-between items-center">
                <h3 class="text-sm font-bold">Edit — Conduct Register</h3>
                <button type="button" onclick="closeModal('editModal')" class="text-xl leading-none">&times;</button>
            </div>
            <form id="editForm" method="POST" class="p-5 space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="{{ $labelCls }}">Date</label>
                    <input type="date" id="edit_entry_date" name="entry_date" required class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Student <span class="text-red-500">*</span></label>
                    <select id="edit_student_id" name="student_id" required class="{{ $inputCls }}">
                        <option value="">Select...</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelCls }}">Type</label>
                        <select id="edit_type" name="type" required class="{{ $inputCls }}">
                            <option value="merit">Merit</option>
                            <option value="demerit">Demerit</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Points</label>
                        <input type="number" id="edit_points" name="points" min="1" required
                            class="{{ $inputCls }}">
                    </div>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Note</label>
                    <textarea id="edit_note" name="note" rows="3" required class="{{ $inputCls }}"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" onclick="closeModal('editModal')"
                        class="px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg">Update</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        @media print {

            aside,
            header,
            nav,
            .no-print {
                display: none !important;
            }
        }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>
        var conductEntries = @json($conductData);

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function openEditModal(id) {
            var e = conductEntries[id];
            if (!e) return;
            document.getElementById('editForm').action = "{{ url('admin/conduct') }}/" + id;
            document.getElementById('edit_entry_date').value = e.entry_date;
            document.getElementById('edit_student_id').value = e.student_id;
            document.getElementById('edit_type').value = e.type;
            document.getElementById('edit_points').value = e.points;
            document.getElementById('edit_note').value = e.note || '';
            openModal('editModal');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('addModal');
                closeModal('editModal');
            }
        });

        // ---------- Export (current page of results) ----------
        function tableData() {
            var head = ['Date', 'Student', 'Type', 'Points', 'Note', 'Recorded By'];
            var rows = Array.from(document.querySelectorAll('#conductTable tbody tr'))
                .filter(function(r) {
                    return r.cells.length >= 6;
                });
            var body = rows.map(function(r) {
                return Array.from(r.cells).slice(0, 6).map(function(c) {
                    return c.innerText.trim().replace(/\s+/g, ' ');
                });
            });
            return {
                head: head,
                body: body
            };
        }

        function exportCsv() {
            var d = tableData();
            var csv = [d.head].concat(d.body).map(function(r) {
                return r.map(function(v) {
                    return '"' + v.replace(/"/g, '""') + '"';
                }).join(',');
            }).join('\n');
            var a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([csv], {
                type: 'text/csv'
            }));
            a.download = 'conduct-register.csv';
            a.click();
        }

        function exportPdf() {
            var d = tableData();
            var doc = new window.jspdf.jsPDF({
                orientation: 'landscape'
            });
            doc.text('Conduct Register', 14, 15);
            doc.autoTable({
                head: [d.head],
                body: d.body,
                startY: 22,
                headStyles: {
                    fillColor: [15, 27, 76]
                }
            });
            doc.save('conduct-register.pdf');
        }
    </script>
@endsection
