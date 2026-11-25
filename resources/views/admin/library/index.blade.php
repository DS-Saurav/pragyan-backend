@extends('layouts.admin')

@section('title', 'Library — Books Catalog')

@section('content')
    @php
        $finePerDay = $finePerDay ?? 5;
        $viewMode = $viewMode ?? 'shelf';
        $gradients = [
            'from-sky-500 to-indigo-700',
            'from-amber-400 to-rose-500',
            'from-emerald-400 to-teal-700',
            'from-fuchsia-500 to-purple-700',
            'from-orange-400 to-red-600',
        ];
        $inputCls = 'w-full text-xs border border-gray-300 rounded-lg p-2.5';
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
        @if (session('error'))
            <div class="mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('error') }}
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

        {{-- Page header --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-5">
            <h3 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                <span class="text-xl"><i class="bi bi-book"></i></span> Library — Books Catalog
            </h3>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.library.books.index', ['view' => $viewMode === 'shelf' ? 'table' : 'shelf']) }}"
                    class="px-4 py-2 bg-white hover:bg-gray-100 text-slate-800 text-xs font-bold rounded-lg shadow-sm border border-gray-200 transition">
                    {{ $viewMode === 'shelf' ? 'Table View' : 'Shelf View' }}
                </a>
                <button type="button" onclick="openModal('addBookModal')"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition flex items-center gap-1.5">
                    <span class="text-base leading-none">+</span> Add Book
                </button>
            </div>
        </div>

        {{-- Metric cards --}}
        @php
            $cards = [
                ['titles', 'Titles', 'from-slate-900 to-[#0b1230]', 'bi-book'],
                ['total_copies', 'Total Copies', 'from-sky-500 to-sky-700', 'bi-bookmark'],
                ['available', 'Available', 'from-emerald-500 to-green-700', 'bi-check2-circle'],
                ['issued', 'Issued Out', 'from-amber-500 to-orange-600', 'bi-box-arrow-up'],
                ['overdue', 'Overdue', 'from-rose-500 to-red-700', 'bi-exclamation-triangle'],
                ['fines', 'Fines Collected', 'from-violet-500 to-purple-700', 'bi-coin'],
            ];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            @foreach ($cards as [$key, $label, $grad, $icon])
                <div
                    class="bg-gradient-to-br {{ $grad }} text-white p-4 rounded-xl shadow-sm flex items-center gap-3">
                    <div class="w-11 h-11 rounded-lg bg-white/15 flex items-center justify-center text-xl shrink-0">
                        <i class="bi {{ $icon }}"></i></div>
                    <div>
                        <p class="text-2xl font-extrabold leading-none">
                            {{ $key === 'fines' ? '₹' : '' }}{{ $stats[$key] ?? 0 }}</p>
                        <p class="text-[10px] uppercase tracking-wider text-white/80 font-semibold mt-1">{{ $label }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Overdue alert --}}
        @if (($stats['overdue'] ?? 0) > 0)
            <div
                class="mb-5 bg-white border-l-4 border-red-500 rounded-xl shadow-sm px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="text-sm text-slate-800 flex items-center gap-2">
                    <span class="text-red-500"><i class="bi bi-exclamation-triangle"></i></span>
                    <span><b>{{ $stats['overdue'] }} book(s) overdue</b> — fine accruing so far:
                        <b class="text-red-600">₹{{ $overdueFine ?? 0 }}</b> (at ₹{{ $finePerDay }}/day).</span>
                </p>
                @if (Route::has('admin.library.circulation.index'))
                    <a href="{{ route('admin.library.circulation.index', ['tab' => 'overdue']) }}"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg text-center">
                        View Overdue Books
                    </a>
                @endif
            </div>
        @endif

        @if ($viewMode === 'table')
            {{-- ============ TABLE VIEW ============ --}}
            <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4 no-print">
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
                    <table id="booksTable" class="min-w-full text-left text-xs">
                        <thead class="bg-[#0f1b4c] text-white uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-4 py-3 rounded-l-lg">Acc No</th>
                                <th class="px-4 py-3">Title</th>
                                <th class="px-4 py-3">Author</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Shelf</th>
                                <th class="px-4 py-3">Copies</th>
                                <th class="px-4 py-3">Available</th>
                                <th class="px-4 py-3 rounded-r-lg no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($books as $book)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-700">{{ $book->acc_no }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($book->cover_image)
                                                <img src="{{ asset('storage/' . $book->cover_image) }}"
                                                    class="w-7 h-10 rounded object-cover shadow-sm" alt="">
                                            @else
                                                <div
                                                    class="w-7 h-10 rounded bg-gradient-to-br {{ $gradients[$book->id % count($gradients)] }} flex items-center justify-center text-[10px]">
                                                    <i class="bi bi-book"></i></div>
                                            @endif
                                            <span class="font-bold text-slate-900">{{ $book->title }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $book->author }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-violet-100 text-violet-700 uppercase">{{ $book->category }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $book->shelf }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $book->total_copies }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $book->available_copies > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                            {{ $book->available_copies }} Available
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 no-print">
                                        <div class="flex items-center gap-3 text-base">
                                            @if ($book->available_copies > 0)
                                                <button type="button" title="Issue"
                                                    class="text-emerald-600 hover:text-emerald-800"
                                                    onclick="openIssueModal({{ $book->id }})"><i class="bi bi-arrow-right"></i></button>
                                            @endif
                                            <button type="button" title="Edit"
                                                class="text-amber-500 hover:text-amber-700"
                                                data-book="{{ json_encode($book) }}"
                                                onclick="openEditBookModal(this)"><i class="bi bi-pencil"></i></button>
                                            <form action="{{ route('admin.library.books.destroy', $book->id) }}"
                                                method="POST" onsubmit="return confirm('Delete this book?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" title="Delete"
                                                    class="text-rose-500 hover:text-rose-700"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 mt-4 text-xs text-gray-500 no-print">
                    <span id="tableInfo"></span>
                    <div id="tablePager" class="flex items-center gap-1"></div>
                </div>
            </div>
        @else
            {{-- ============ SHELF VIEW ============ --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @forelse ($books as $book)
                    <div
                        class="bg-white rounded-xl shadow-sm hover:shadow-lg transition overflow-hidden flex flex-col border border-gray-100">
                        <div class="relative h-44 bg-gradient-to-br {{ $gradients[$book->id % count($gradients)] }}">
                            @if ($book->cover_image)
                                <img src="{{ asset('storage/' . $book->cover_image) }}" alt="{{ $book->title }}"
                                    class="absolute inset-0 w-full h-full object-cover">
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-5xl opacity-60"><i class="bi bi-book"></i></div>
                            @endif
                            <div
                                class="absolute inset-x-0 bottom-0 p-3 pt-10 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                                <h4 class="text-white font-extrabold text-[13px] leading-tight line-clamp-2">
                                    {{ $book->title }}</h4>
                                <p class="text-white/80 text-[11px] mt-0.5">{{ $book->author }}</p>
                            </div>
                        </div>
                        <div class="p-3 flex-1 flex flex-col gap-1.5">
                            <p class="text-[11px] text-gray-500"><span class="font-semibold">{{ $book->acc_no }}</span> ·
                                Shelf {{ $book->shelf }}</p>
                            <p class="text-[11px] text-gray-600">{{ $book->category }}</p>
                            <span
                                class="text-[10px] font-extrabold uppercase text-center rounded-full px-2 py-1 {{ $book->available_copies > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ $book->available_copies }} of {{ $book->total_copies }} available
                            </span>
                            <div class="flex gap-1.5 mt-auto pt-1">
                                @if ($book->available_copies > 0)
                                    <button type="button" onclick="openIssueModal({{ $book->id }})"
                                        class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-md"><i class="bi bi-arrow-right"></i>
                                        Issue</button>
                                @endif
                                <button type="button" data-book="{{ json_encode($book) }}"
                                    onclick="openEditBookModal(this)"
                                    class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-[11px] font-bold rounded-md"><i class="bi bi-pencil"></i>
                                    Edit</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-10 text-gray-500">No books found in catalog.</div>
                @endforelse
            </div>
        @endif
    </div>

    {{-- ============ ADD BOOK MODAL ============ --}}
    <div id="addBookModal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="bg-[#0f1b4c] text-white px-5 py-3 flex justify-between items-center">
                <h3 class="text-sm font-bold">Add Book to Catalog</h3>
                <button type="button" onclick="closeModal('addBookModal')" class="text-xl leading-none">&times;</button>
            </div>
            <form action="{{ route('admin.library.books.store') }}" method="POST" enctype="multipart/form-data"
                class="p-5 space-y-3">
                @csrf
                <input type="hidden" name="_mode" value="add">
                <div>
                    <label class="{{ $labelCls }}">Book Title</label>
                    <input type="text" name="title" required maxlength="255" value="{{ old('_mode') === 'add' ? old('title') : '' }}"
                        class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Author Name</label>
                    <input type="text" name="author" required maxlength="255" value="{{ old('_mode') === 'add' ? old('author') : '' }}"
                        class="{{ $inputCls }}">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelCls }}">Category</label>
                        <input type="text" name="category" required maxlength="100" placeholder="e.g. Mathematics"
                            value="{{ old('_mode') === 'add' ? old('category') : '' }}" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Shelf Location</label>
                        <input type="text" name="shelf" required maxlength="50" placeholder="e.g. R-2"
                            value="{{ old('_mode') === 'add' ? old('shelf') : '' }}" class="{{ $inputCls }}">
                    </div>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Total Copies</label>
                    <input type="number" name="total_copies" value="{{ old('_mode') === 'add' ? old('total_copies', 5) : 5 }}"
                        min="1" required class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Cover Image <span
                            class="text-gray-400 normal-case font-normal">(optional)</span></label>
                    <input type="file" name="cover_image" accept="image/*"
                        class="w-full text-xs border border-gray-300 rounded-lg p-2 file:mr-3 file:px-3 file:py-1 file:rounded-md file:border-0 file:bg-gray-100 file:text-xs file:font-bold">
                    @error('cover_image')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('addBookModal')"
                        class="px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save
                        Book</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ============ EDIT BOOK MODAL ============ --}}
    <div id="editBookModal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="bg-[#0f1b4c] text-white px-5 py-3 flex justify-between items-center">
                <h3 class="text-sm font-bold">Edit Book Details</h3>
                <button type="button" onclick="closeModal('editBookModal')"
                    class="text-xl leading-none">&times;</button>
            </div>
            <form id="editBookForm" method="POST" enctype="multipart/form-data" class="p-5 space-y-3">
                @csrf @method('PUT')
                <input type="hidden" name="_mode" id="edit_mode" value="">
                <input type="hidden" name="_edit_id" id="edit_edit_id" value="">
                <div>
                    <label class="{{ $labelCls }}">Book Title</label>
                    <input type="text" name="title" id="edit_title" required maxlength="255" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Author Name</label>
                    <input type="text" name="author" id="edit_author" required maxlength="255" class="{{ $inputCls }}">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelCls }}">Category</label>
                        <input type="text" name="category" id="edit_category" required maxlength="100" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Shelf Location</label>
                        <input type="text" name="shelf" id="edit_shelf" required maxlength="50" class="{{ $inputCls }}">
                    </div>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Total Copies</label>
                    <input type="number" name="total_copies" id="edit_total_copies" min="1" required
                        class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Replace Cover Image <span
                            class="text-gray-400 normal-case font-normal">(optional)</span></label>
                    <input type="file" name="cover_image" accept="image/*"
                        class="w-full text-xs border border-gray-300 rounded-lg p-2 file:mr-3 file:px-3 file:py-1 file:rounded-md file:border-0 file:bg-gray-100 file:text-xs file:font-bold">
                    @error('cover_image')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('editBookModal')"
                        class="px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg">Update
                        Book</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ============ ISSUE BOOK MODAL ============ --}}
    <div id="issueBookModal"
        class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/60 backdrop-blur-sm p-4 pt-16">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden">
            <div class="bg-[#0f1b4c] text-white px-5 py-3 flex justify-between items-center">
                <h3 class="text-sm font-bold flex items-center gap-2"><i class="bi bi-arrow-right"></i> Issue Book</h3>
                <button type="button" onclick="closeModal('issueBookModal')"
                    class="text-xl leading-none">&times;</button>
            </div>
            @if (Route::has('admin.library.circulation.issue'))
                <form action="{{ route('admin.library.circulation.issue') }}" method="POST" class="p-5 space-y-3">
                    @csrf
                    <div>
                        <label class="{{ $labelCls }}">Book <span class="text-red-500">*</span></label>
                        <select name="book_id" id="issue_book" required class="{{ $inputCls }} !text-sm">
                            <option value="">— Select available book —</option>
                            @foreach ($issueBooks ?? $books->where('available_copies', '>', 0) as $b)
                                <option value="{{ $b->id }}">{{ $b->title }} ({{ $b->acc_no }}) —
                                    {{ $b->available_copies }} left</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Member Type</label>
                        <select name="member_type" id="issue_member_type" onchange="switchMemberType()"
                            class="{{ $inputCls }} !text-sm">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher / Staff</option>
                        </select>
                    </div>
                    <div>
                        <label id="memberLabel" class="{{ $labelCls }}">Student <span
                                class="text-red-500">*</span></label>
                        <select name="student_id" id="issue_student" class="{{ $inputCls }} !text-sm">
                            <option value="">— Select member —</option>
                            @foreach ($students ?? [] as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <select name="teacher_id" id="issue_teacher" class="hidden {{ $inputCls }} !text-sm">
                            <option value="">— Select member —</option>
                            @foreach ($teachers ?? [] as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Issue Period (Days)</label>
                        <input type="number" name="days" value="14" min="1" max="90" required
                            class="{{ $inputCls }} !text-sm">
                        <p class="text-[11px] text-gray-500 mt-1">Fine of ₹{{ $finePerDay }}/day applies after the due
                            date</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" onclick="closeModal('issueBookModal')"
                            class="px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg">Issue
                            Book</button>
                    </div>
                </form>
            @else
                <div class="p-5 text-sm text-gray-600">
                    The issue route <code>admin.library.circulation.issue</code> is not defined yet.
                </div>
            @endif
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

            #booksTable tbody tr {
                display: table-row !important;
            }
        }
    </style>

    @if ($viewMode === 'table')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    @endif

    <script>
        // ---------- Modal helpers ----------
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function openEditBookModal(btn) {
            const book = JSON.parse(btn.dataset.book);
            document.getElementById('editBookForm').action = `/admin/library/books/${book.id}`;
            document.getElementById('edit_mode').value = 'edit';
            document.getElementById('edit_edit_id').value = book.id;
            ['title', 'author', 'category', 'shelf', 'total_copies'].forEach(function(f) {
                document.getElementById('edit_' + f).value = book[f] ?? '';
            });
            openModal('editBookModal');
        }

        function openIssueModal(bookId) {
            var sel = document.getElementById('issue_book');
            if (sel) sel.value = bookId;
            openModal('issueBookModal');
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
            if (e.key === 'Escape')['addBookModal', 'editBookModal', 'issueBookModal'].forEach(closeModal);
        });

        @if ($errors->any() && old('_mode') === 'add')
            // Failed add: reopen the add modal; its fields keep old() values.
            (function() {
                openModal('addBookModal');
            })();
        @endif

        @if ($errors->any() && old('_mode') === 'edit')
            // Reopen the edit modal with the user's input if validation failed.
            (function() {
                document.getElementById('editBookForm').action =
                    @js(route('admin.library.books.update', ['book' => old('_edit_id')]));
                var O = @json(old());
                ['title', 'author', 'category', 'shelf'].forEach(function(f) {
                    document.getElementById('edit_' + f).value = O[f] !== undefined && O[f] !== null ? O[f] : '';
                });
                document.getElementById('edit_total_copies').value = O.total_copies || '';
                openModal('editBookModal');
            })();
        @endif

        @if ($viewMode === 'table')
            // ---------- Table: search / page length / pagination ----------
            var rows = Array.from(document.querySelectorAll('#booksTable tbody tr'));
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
                var head = ['Acc No', 'Title', 'Author', 'Category', 'Shelf', 'Copies', 'Available'];
                var body = filtered.map(function(r) {
                    return Array.from(r.cells).slice(0, 7).map(function(c) {
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
                a.download = 'library-books.csv';
                a.click();
            }

            function exportPdf() {
                var d = tableData();
                var doc = new window.jspdf.jsPDF();
                doc.text('Library - Books Catalog', 14, 15);
                doc.autoTable({
                    head: [d.head],
                    body: d.body,
                    startY: 22,
                    headStyles: {
                        fillColor: [15, 27, 76]
                    }
                });
                doc.save('library-books.pdf');
            }
        @endif
    </script>
@endsection
