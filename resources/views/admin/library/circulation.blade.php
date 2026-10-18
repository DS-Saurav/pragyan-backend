@extends('layouts.admin')

@section('title', 'Library — Issue / Return')

@section('content')
    @php
        $today = \Carbon\Carbon::today();
        $inputCls = 'w-full text-sm border border-gray-300 rounded-lg p-2.5';
        $labelCls = 'block text-[11px] font-bold uppercase text-gray-600 mb-1';
        $tabs = [
            ['issued', 'Issued Out', 'bg-amber-500', 'text-white'],
            ['overdue', 'Overdue', 'bg-red-500', 'text-white'],
            ['returned', 'Returned', 'bg-green-600', 'text-white'],
            ['all', 'All', 'bg-[#0f1b4c]', 'text-white'],
        ];
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

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
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                <span class="text-xl">📕</span> Library — Issue / Return
            </h3>
            <button type="button" onclick="openModal('issueBookModal')"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow flex items-center gap-1.5">
                <span class="text-base leading-none">+</span> Issue Book
            </button>
        </div>

        {{-- Chevron tabs --}}
        <div class="flex overflow-x-auto mb-4 rounded-md">
            @foreach ($tabs as $i => [$key, $label, $bg, $txt])
                <a href="{{ route('admin.library.circulation.index', ['tab' => $key]) }}"
                    class="flex-1 min-w-[150px] text-center text-xs font-extrabold py-2.5 {{ $bg }} {{ $txt }} transition
                           {{ $statusTab === $key ? 'brightness-110 shadow-inner underline underline-offset-4' : 'opacity-80 hover:opacity-100' }}"
                    style="clip-path: polygon(0 0, calc(100% - 14px) 0, 100% 50%, calc(100% - 14px) 100%, 0 100%, 14px 50%); {{ $i === 0 ? 'clip-path: polygon(0 0, calc(100% - 14px) 0, 100% 50%, calc(100% - 14px) 100%, 0 100%);' : '' }} {{ $i > 0 ? 'margin-left:-6px;' : '' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        {{-- Table card --}}
        <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 no-print">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="exportCsv()"
                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-md">📄
                        CSV</button>
                    <button type="button" onclick="exportPdf()"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-md">📕
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md">🖨
                        PRINT</button>
                    <span class="text-xs text-gray-500 ml-2">Show</span>
                    <select id="pageLen" class="text-xs border border-gray-300 rounded-md py-1.5 pl-2 pr-6">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                        <option>100</option>
                    </select>
                    <span class="text-xs text-gray-500">entries</span>
                </div>
                <input type="text" id="tableSearch" placeholder="Search..."
                    class="text-xs border border-gray-300 rounded-md px-3 py-2 w-full sm:w-60 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="overflow-x-auto">
                <table id="issuesTable" class="min-w-full text-left text-xs">
                    <thead class="bg-[#0f1b4c] text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3 rounded-l-lg">Acc No</th>
                            <th class="px-4 py-3">Book</th>
                            <th class="px-4 py-3">Member</th>
                            <th class="px-4 py-3">Issued</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Returned</th>
                            <th class="px-4 py-3">Fine</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 rounded-r-lg no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($issues as $issue)
                            @php
                                $due = \Carbon\Carbon::parse($issue->due_date);
                                $isReturned = $issue->status === 'returned';
                                $isOverdue = !$isReturned && $due->lt($today);
                                $daysLate = $isOverdue ? (int) $due->diffInDays($today) : 0;
                                $accruing = $daysLate * $finePerDay;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-700">{{ $issue->book->acc_no ?? '—' }}</td>
                                <td class="px-4 py-3 font-bold text-slate-900">{{ $issue->book->title ?? 'Deleted book' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $issue->member_name }}
                                    <span
                                        class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-blue-100 text-blue-700">{{ $issue->member_type }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ \Carbon\Carbon::parse($issue->issue_date)->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-700' }}">
                                    {{ $due->format('Y-m-d') }}@if ($isOverdue)
                                        ({{ $daysLate }}d late)
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $issue->returned_date ? \Carbon\Carbon::parse($issue->returned_date)->format('Y-m-d') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($isOverdue)
                                        <span class="text-red-600 font-bold">₹{{ $accruing }} <span
                                                class="font-normal">accruing</span></span>
                                    @elseif ($isReturned && $issue->fine > 0)
                                        <span class="text-red-600 font-bold">₹{{ $issue->fine }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($isReturned)
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-emerald-100 text-emerald-700">Returned</span>
                                    @elseif ($isOverdue)
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-rose-100 text-rose-700">Overdue</span>
                                    @else
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-amber-100 text-amber-700">Issued</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 no-print">
                                    @unless ($isReturned)
                                        <form action="{{ route('admin.library.circulation.return', $issue->id) }}"
                                            method="POST" onsubmit="return confirm('Mark this book as returned?');">
                                            @csrf
                                            <button type="submit"
                                                class="px-3 py-1.5 bg-[#0f1b4c] hover:bg-slate-800 text-white text-xs font-bold rounded-md">↩
                                                Return</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div id="emptyMsg" class="hidden text-center text-gray-500 text-xs py-6">No records found.</div>

            <div class="flex flex-wrap items-center justify-between gap-2 mt-4 text-xs text-gray-500 no-print">
                <span id="tableInfo"></span>
                <div id="tablePager" class="flex items-center gap-1"></div>
            </div>
        </div>
    </div>

    {{-- ============ ISSUE BOOK MODAL ============ --}}
    <div id="issueBookModal"
        class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/60 backdrop-blur-sm p-4 pt-16">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden">
            <div class="bg-[#0f1b4c] text-white px-5 py-3 flex justify-between items-center">
                <h3 class="text-sm font-bold flex items-center gap-2">➤ Issue Book</h3>
                <button type="button" onclick="closeModal('issueBookModal')" class="text-xl leading-none">&times;</button>
            </div>
            <form action="{{ route('admin.library.circulation.issue') }}" method="POST" class="p-5 space-y-3">
                @csrf
                <div>
                    <label class="{{ $labelCls }}">Book <span class="text-red-500">*</span></label>
                    <select name="book_id" id="issue_book" required class="{{ $inputCls }}">
                        <option value="">— Select available book —</option>
                        @foreach ($issueBooks as $b)
                            <option value="{{ $b->id }}">{{ $b->title }} ({{ $b->acc_no }}) —
                                {{ $b->available_copies }} left</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Member Type</label>
                    <select name="member_type" id="issue_member_type" onchange="switchMemberType()"
                        class="{{ $inputCls }}">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher / Staff</option>
                    </select>
                </div>
                <div>
                    <label id="memberLabel" class="{{ $labelCls }}">Student <span
                            class="text-red-500">*</span></label>
                    <select name="student_id" id="issue_student" class="{{ $inputCls }}">
                        <option value="">— Select member —</option>
                        @foreach ($students as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <select name="teacher_id" id="issue_teacher" class="hidden {{ $inputCls }}">
                        <option value="">— Select member —</option>
                        @foreach ($teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Issue Period (Days)</label>
                    <input type="number" name="days" value="14" min="1" max="90" required
                        class="{{ $inputCls }}">
                    <p class="text-[11px] text-gray-500 mt-1">Fine of ₹{{ $finePerDay }}/day applies after the due date
                    </p>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" onclick="closeModal('issueBookModal')"
                        class="px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg">Issue
                        Book</button>
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

            #issuesTable tbody tr {
                display: table-row !important;
            }
        }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function switchMemberType() {
            var isTeacher = document.getElementById('issue_member_type').value === 'teacher';
            document.getElementById('issue_student').classList.toggle('hidden', isTeacher);
            document.getElementById('issue_teacher').classList.toggle('hidden', !isTeacher);
            document.getElementById('issue_student').value = '';
            document.getElementById('issue_teacher').value = '';
            document.getElementById('memberLabel').innerHTML =
                (isTeacher ? 'Staff Member' : 'Student') + ' <span class="text-red-500">*</span>';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal('issueBookModal');
        });

        // ---------- Table: search / page length / pagination ----------
        var rows = Array.from(document.querySelectorAll('#issuesTable tbody tr'));
        var page = 1,
            filtered = rows;

        function renderTable() {
            var len = parseInt(document.getElementById('pageLen').value);
            var pages = Math.max(1, Math.ceil(filtered.length / len));
            page = Math.min(page, pages);
            rows.forEach(function(r) {
                r.style.display = 'none';
            });
            filtered.slice((page - 1) * len, page * len).forEach(function(r) {
                r.style.display = '';
            });

            document.getElementById('emptyMsg').classList.toggle('hidden', filtered.length > 0);

            var start = filtered.length ? (page - 1) * len + 1 : 0;
            var end = Math.min(page * len, filtered.length);
            document.getElementById('tableInfo').textContent =
                'Showing ' + start + ' to ' + end + ' of ' + filtered.length + ' entries';

            var pager = document.getElementById('tablePager');
            pager.innerHTML = '';

            function mk(label, p, active, disabled) {
                var b = document.createElement('button');
                b.textContent = label;
                b.disabled = !!disabled;
                b.className = 'px-3 py-1 rounded-md text-xs ' +
                    (active ? 'bg-[#0f1b4c] text-white font-bold' : 'text-gray-500 hover:bg-gray-100') +
                    (disabled ? ' opacity-40 cursor-not-allowed' : '');
                b.onclick = function() {
                    page = p;
                    renderTable();
                };
                pager.appendChild(b);
            }
            mk('Previous', page - 1, false, page === 1);
            for (var i = 1; i <= pages; i++) mk(i, i, i === page, false);
            mk('Next', page + 1, false, page === pages);
        }

        document.getElementById('tableSearch').addEventListener('input', function(e) {
            var q = e.target.value.toLowerCase();
            filtered = rows.filter(function(r) {
                return r.innerText.toLowerCase().includes(q);
            });
            page = 1;
            renderTable();
        });
        document.getElementById('pageLen').addEventListener('change', function() {
            page = 1;
            renderTable();
        });
        renderTable();

        // ---------- Export ----------
        function tableData() {
            var head = ['Acc No', 'Book', 'Member', 'Issued', 'Due', 'Returned', 'Fine', 'Status'];
            var body = filtered.map(function(r) {
                return Array.from(r.cells).slice(0, 8).map(function(c) {
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
            a.download = 'library-issues.csv';
            a.click();
        }

        function exportPdf() {
            var d = tableData();
            var doc = new window.jspdf.jsPDF({
                orientation: 'landscape'
            });
            doc.text('Library - Issue / Return', 14, 15);
            doc.autoTable({
                head: [d.head],
                body: d.body,
                startY: 22,
                headStyles: {
                    fillColor: [15, 27, 76]
                }
            });
            doc.save('library-issues.pdf');
        }
    </script>
@endsection
