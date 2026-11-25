<div>
    <!-- Simplicity is the essence of happiness. - Cedric Bledsoe -->
</div>
@extends('layouts.admin')

@section('title', 'Hall Tickets')

@section('content')
    <div class="max-w-7xl mx-auto">

        {{-- ================= Header ================= --}}
        <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <h3 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                <i class="bi bi-ticket-detailed"></i> Hall Tickets
            </h3>
            @if ($exam && $tickets->isNotEmpty())
                <button type="button" id="printBtn"
                    class="px-5 py-2.5 bg-[#0f1b4c] hover:bg-slate-800 text-white text-xs font-bold rounded-lg shadow flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="bi bi-printer"></i> Print Selected (<span id="printCount">0</span>)
                </button>
            @endif
        </div>

        {{-- ================= Filters ================= --}}
        <form method="GET" action="{{ route('admin.hall-tickets.index') }}"
            class="no-print bg-white rounded-xl shadow-sm border border-gray-100 p-4 grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-[11px] font-bold uppercase text-gray-600 mb-1">Exam <span
                        class="text-red-500">*</span></label>
                <select name="exam_id" onchange="this.form.submit()"
                    class="w-full text-sm border border-gray-300 rounded-lg p-2.5 bg-white">
                    <option value="">Select exam...</option>
                    @foreach ($exams as $e)
                        <option value="{{ $e->id }}" {{ $exam && $exam->id === $e->id ? 'selected' : '' }}>
                            {{ $e->name }}{{ !empty($e->academic_year) ? ' (' . $e->academic_year . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase text-gray-600 mb-1">Class</label>
                <select name="class_id" onchange="this.form.submit()"
                    class="w-full text-sm border border-gray-300 rounded-lg p-2.5 bg-white">
                    <option value="">All classes</option>
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}"
                            {{ (string) $selectedClassId === (string) $c->id ? 'selected' : '' }}>
                            {{ $classLabels[$c->id] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        @if (!$exam)
            {{-- ================= Empty state ================= --}}
            <div class="no-print bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="text-4xl mb-2"><i class="bi bi-ticket-perforated"></i></div>
                <p class="text-sm font-semibold text-slate-800">Select an exam to generate hall tickets</p>
                <p class="text-xs text-gray-500 mt-1 max-w-md mx-auto">
                    Each ticket shows the student's details, the subject-wise exam schedule, instructions and
                    signature lines, and can be printed one, two or four to a page.
                </p>
            </div>
        @elseif ($tickets->isEmpty())
            <div class="no-print bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-6 text-sm">
                No active students found for this selection. Check that students are linked to a class.
            </div>
        @else
            {{-- Warning: classes without exam subjects --}}
            @if ($classesWithoutSetup->isNotEmpty())
                <div class="no-print mb-4 bg-amber-50 border border-amber-300 text-amber-800 rounded-xl px-4 py-3 text-xs">
                    <b>Exam schedule missing for:</b> {{ $classesWithoutSetup->implode(', ') }}.
                    Their tickets will print with an empty schedule.
                    <a href="{{ route('admin.exams.setup', $exam->id) }}" class="underline font-bold">Set up exam
                        subjects</a>
                </div>
            @endif

            <div class="flex flex-col lg:flex-row gap-4 items-start">

                {{-- ================= Controls ================= --}}
                <aside
                    class="no-print w-full lg:w-80 shrink-0 bg-white rounded-xl shadow-sm border border-gray-100 p-4 lg:sticky lg:top-4">
                    <p class="text-[11px] font-bold uppercase text-gray-600 mb-2">Print settings</p>

                    <label class="block text-xs text-gray-700 mb-1">Tickets per page</label>
                    <select id="perPage" class="w-full text-sm border border-gray-300 rounded-lg p-2 mb-3">
                        <option value="1">1 per page (A4)</option>
                        <option value="2" selected>2 per page</option>
                        <option value="4">4 per page</option>
                    </select>

                    <div class="space-y-1.5 text-xs text-gray-700 mb-4">
                        <label class="flex items-center gap-2"><input type="checkbox" id="optPhoto" checked> Photo
                            box</label>
                        <label class="flex items-center gap-2"><input type="checkbox" id="optQr" checked> QR code
                            (verification)</label>
                        <label class="flex items-center gap-2"><input type="checkbox" id="optInstr" checked> Instructions
                            <span class="text-gray-400">(hidden at 4 per page)</span></label>
                    </div>

                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] font-bold uppercase text-gray-600">Students (<span
                                id="selCount">0</span>/{{ $tickets->count() }})</p>
                        <div class="flex gap-2 text-[11px] font-bold">
                            <button type="button" id="selAll" class="text-indigo-600 hover:underline">All</button>
                            <button type="button" id="selNone" class="text-gray-500 hover:underline">None</button>
                        </div>
                    </div>

                    <button type="button" id="selLow"
                        class="w-full mb-2 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">
                        Deselect attendance below {{ $minAttendance }}%
                    </button>

                    <input type="text" id="stuSearch" placeholder="Search name, roll or ADM #..."
                        class="w-full text-xs border border-gray-300 rounded-lg p-2 mb-2">

                    <div class="max-h-[50vh] overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                        @foreach ($tickets as $t)
                            @php $low = $t['attendance'] !== null && $t['attendance'] < $minAttendance; @endphp
                            <label class="stu-row flex items-center gap-2 px-3 py-2 text-xs hover:bg-gray-50 cursor-pointer"
                                data-search="{{ strtolower($t['name'] . ' ' . $t['roll'] . ' ' . $t['adm']) }}">
                                <input type="checkbox" class="stu-check" value="{{ $t['id'] }}"
                                    data-low="{{ $low ? 1 : 0 }}" checked>
                                <span class="font-mono text-gray-500 w-6 text-right">{{ $t['roll'] }}</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-semibold text-slate-800 truncate">{{ $t['name'] }}</span>
                                    @if (!$selectedClassId)
                                        <span class="block text-[10px] text-gray-400 truncate">{{ $t['class'] }}</span>
                                    @endif
                                </span>
                                @if ($t['attendance'] !== null)
                                    <span
                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $low ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}"
                                        title="Attendance">{{ $t['attendance'] }}%</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </aside>

                {{-- ================= Live preview ================= --}}
                <div class="no-print flex-1 min-w-0 w-full">
                    <div id="previewArea" class="grid gap-4">
                        @foreach ($tickets as $t)
                            @include('admin.hall-tickets._ticket')
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Print copy is built from the selected tickets just before printing --}}
            <div id="printArea"></div>
        @endif
    </div>

    <style>
        /* ---------- Ticket design (screen + print) ---------- */
        .ticket {
            background: #fff;
            color: #111;
            border: 1.5px solid #0f1b4c;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 11px;
            line-height: 1.35;
            box-sizing: border-box;
            max-width: 820px;
            width: 100%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .15);
        }

        .ticket .t-head {
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #0f1b4c;
            padding-bottom: 6px;
        }

        .ticket .logo {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #0f1b4c;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            overflow: hidden;
            flex: none;
        }

        .ticket .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ticket .school {
            flex: 1;
            text-align: center;
        }

        .ticket .school h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #0f1b4c;
            text-transform: uppercase;
        }

        .ticket .school p {
            margin: 0;
            font-size: 9.5px;
            color: #444;
        }

        .ticket .t-title {
            margin: 6px 0;
            text-align: center;
            background: #0f1b4c;
            color: #fff;
            font-weight: 700;
            letter-spacing: 1.5px;
            padding: 3px 0;
            font-size: 11px;
            text-transform: uppercase;
        }

        .ticket .exam-line {
            text-align: center;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .ticket .exam-line span {
            font-weight: 400;
            color: #444;
        }

        .ticket .t-body {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .ticket .photo {
            width: 78px;
            height: 92px;
            border: 1px dashed #888;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 8px;
            color: #888;
            flex: none;
            overflow: hidden;
        }

        .ticket .photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ticket .info {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 12px;
            align-content: start;
        }

        .ticket .info b {
            color: #0f1b4c;
        }

        .ticket .info .full {
            grid-column: 1 / -1;
        }

        .ticket .qr {
            flex: none;
            width: 64px;
            height: 64px;
        }

        .ticket .qr img,
        .ticket .qr canvas {
            width: 64px !important;
            height: 64px !important;
        }

        .ticket table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 10.5px;
        }

        .ticket th,
        .ticket td {
            border: 1px solid #888;
            padding: 2px 5px;
            text-align: left;
        }

        .ticket th {
            background: #e8ecf7;
        }

        .ticket .empty-sched {
            margin-top: 6px;
            padding: 6px;
            text-align: center;
            border: 1px dashed #c00;
            color: #c00;
            font-size: 10px;
        }

        .ticket .instructions {
            margin-top: 6px;
            font-size: 9px;
        }

        .ticket .instructions ol {
            margin: 2px 0 0 14px;
            padding: 0;
        }

        .ticket .sign {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 24px;
            font-size: 9.5px;
        }

        .ticket .sign div {
            flex: 1;
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 2px;
        }

        .ticket .foot {
            margin-top: 4px;
            font-size: 8px;
            color: #777;
            text-align: right;
        }

        .hide-instr .instructions {
            display: none;
        }

        .hide-photo .photo {
            display: none;
        }

        .hide-qr .qr {
            display: none;
        }

        #printArea {
            display: none;
        }

        /* ---------- Print ---------- */
        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }

            html,
            body {
                background: #fff !important;
            }

            .no-print {
                display: none !important;
            }

            main {
                overflow: visible !important;
                padding: 0 !important;
            }

            .min-h-screen {
                min-height: 0 !important;
            }

            #printArea {
                display: block;
            }

            #printArea .ticket {
                box-shadow: none;
                max-width: none;
                break-inside: avoid;
                page-break-inside: avoid;
                margin: 0 0 4mm;
            }

            #printArea .ticket:last-child {
                break-after: auto;
            }

            #printArea.pp-1 .ticket {
                break-after: page;
                margin: 0;
            }

            #printArea.pp-1 .ticket .sign {
                margin-top: 40px;
            }

            #printArea.pp-2 .ticket {
                height: 136mm;
                overflow: hidden;
            }

            #printArea.pp-2 .ticket:nth-child(2n) {
                break-after: page;
            }

            #printArea.pp-4 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 4mm;
            }

            #printArea.pp-4 .ticket {
                height: 134mm;
                overflow: hidden;
                margin: 0;
                font-size: 8.5px;
                padding: 7px 8px;
            }

            #printArea.pp-4 .ticket .school h2 {
                font-size: 12px;
            }

            #printArea.pp-4 .ticket .photo {
                width: 58px;
                height: 70px;
            }

            #printArea.pp-4 .ticket table {
                font-size: 8.5px;
            }

            #printArea.pp-4 .ticket .sign {
                margin-top: 14px;
                font-size: 8px;
            }

            #printArea.pp-4 .ticket .instructions {
                display: none;
            }

            #printArea.pp-4 .ticket:nth-child(4n) {
                break-after: page;
            }
        }
    </style>

    @if ($exam && $tickets->isNotEmpty())
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            (function() {
                var checks = Array.from(document.querySelectorAll('.stu-check'));
                var previews = Array.from(document.querySelectorAll('#previewArea .ticket'));
                var preview = document.getElementById('previewArea');
                var printArea = document.getElementById('printArea');
                var printBtn = document.getElementById('printBtn');

                var perPage = document.getElementById('perPage');
                var optPhoto = document.getElementById('optPhoto');
                var optQr = document.getElementById('optQr');
                var optInstr = document.getElementById('optInstr');

                // ----- QR codes -----
                if (typeof QRCode !== 'undefined') {
                    document.querySelectorAll('.qr').forEach(function(el) {
                        new QRCode(el, {
                            text: el.dataset.qr,
                            width: 64,
                            height: 64,
                            correctLevel: QRCode.CorrectLevel.L
                        });
                    });
                }

                function selectedIds() {
                    return checks.filter(function(c) {
                        return c.checked;
                    }).map(function(c) {
                        return c.value;
                    });
                }

                function optionClasses() {
                    var cls = '';
                    if (!optPhoto.checked) cls += ' hide-photo';
                    if (!optQr.checked) cls += ' hide-qr';
                    if (!optInstr.checked) cls += ' hide-instr';
                    return cls;
                }

                function refresh() {
                    var ids = selectedIds();
                    previews.forEach(function(t) {
                        t.style.display = ids.indexOf(t.dataset.id) !== -1 ? '' : 'none';
                    });
                    document.getElementById('selCount').textContent = ids.length;
                    document.getElementById('printCount').textContent = ids.length;
                    printBtn.disabled = ids.length === 0;
                    preview.className = 'grid gap-4' + optionClasses();
                }

                // Build the print copy from the selected tickets (so page breaks count only these)
                function buildPrintArea() {
                    var ids = selectedIds();
                    printArea.innerHTML = '';
                    printArea.className = 'pp-' + perPage.value + optionClasses();
                    previews.forEach(function(t) {
                        if (ids.indexOf(t.dataset.id) !== -1) {
                            var copy = t.cloneNode(true);
                            copy.style.display = '';
                            printArea.appendChild(copy);
                        }
                    });
                }

                checks.forEach(function(c) {
                    c.addEventListener('change', refresh);
                });
                [optPhoto, optQr, optInstr].forEach(function(o) {
                    o.addEventListener('change', refresh);
                });

                document.getElementById('selAll').addEventListener('click', function() {
                    checks.forEach(function(c) {
                        c.checked = true;
                    });
                    refresh();
                });
                document.getElementById('selNone').addEventListener('click', function() {
                    checks.forEach(function(c) {
                        c.checked = false;
                    });
                    refresh();
                });
                document.getElementById('selLow').addEventListener('click', function() {
                    checks.forEach(function(c) {
                        if (c.dataset.low === '1') c.checked = false;
                    });
                    refresh();
                });

                document.getElementById('stuSearch').addEventListener('input', function(e) {
                    var q = e.target.value.toLowerCase();
                    document.querySelectorAll('.stu-row').forEach(function(r) {
                        r.style.display = r.dataset.search.indexOf(q) !== -1 ? '' : 'none';
                    });
                });

                printBtn.addEventListener('click', function() {
                    window.print();
                });
                window.addEventListener('beforeprint', buildPrintArea);
                window.addEventListener('afterprint', function() {
                    printArea.innerHTML = '';
                });

                refresh();
            })();
        </script>
    @endif
@endsection
