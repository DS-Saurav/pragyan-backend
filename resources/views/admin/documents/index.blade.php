@extends('layouts.admin')

@section('content')
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>

    @use('App\Models\SchoolDocument', 'D')

    @php
        $who = fn($u) => $u ? $u->name ?? ($u->username ?? 'User #' . $u->id) : '—';
        $statusBadge = [
            'active' => ['Active', 'bg-emerald-100 text-emerald-800'],
            'draft' => ['Draft', 'bg-gray-200 text-gray-700'],
            'scheduled' => ['Scheduled', 'bg-sky-100 text-sky-800'],
            'expiring' => ['Expiring soon', 'bg-amber-100 text-amber-800'],
            'expired' => ['Expired', 'bg-rose-100 text-rose-800'],
        ];
        $tabs = [
            'all' => ['ALL', 'bg-slate-900 text-white'],
            'active' => ['ACTIVE', 'bg-emerald-600 text-white'],
            'draft' => ['DRAFTS', 'bg-gray-500 text-white'],
            'expiring' => ['EXPIRING SOON', 'bg-amber-500 text-white'],
            'expired' => ['EXPIRED', 'bg-rose-500 text-white'],
        ];
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div
                class="no-print mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="no-print mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 gap-4">
            <h2 class="text-xl font-bold text-gray-900"><i class="bi bi-folder"></i> Documents</h2>
            @if ($isManager)
                <button type="button" id="openAddBtn"
                    class="no-print px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">+
                    Add Document</button>
            @endif
        </div>

        @if ($isManager)
            <!-- Stat cards -->
            <div class="no-print grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                @foreach ([['Total documents', $stats['total'], 'bg-slate-900'], ['Active', $stats['active'], 'bg-emerald-600'], ['Drafts', $stats['draft'], 'bg-gray-500'], ['Expiring in ' . D::EXPIRING_DAYS . ' days', $stats['expiring'], 'bg-amber-500'], ['Total downloads', number_format($stats['downloads']), 'bg-indigo-600']] as [$label, $value, $color])
                    <div class="{{ $color }} text-white p-4 rounded-xl shadow-sm">
                        <p class="text-xl font-bold">{{ $value }}</p>
                        <p class="text-[11px] uppercase tracking-wider opacity-80 font-semibold mt-1">{{ $label }}
                        </p>
                    </div>
                @endforeach
            </div>

            <!-- Tabs -->
            <div class="no-print flex flex-wrap gap-2 mb-5">
                @foreach ($tabs as $key => [$label, $activeClass])
                    <a href="{{ route('admin.documents.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                        class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $tab === $key ? $activeClass . ' shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                        {{ $label }} ({{ $counts[$key] ?? 0 }})
                    </a>
                @endforeach
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Toolbar -->
            <div class="no-print flex flex-col lg:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.documents.csv', request()->query()) }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-folder2-open"></i>
                        CSV</a>
                    <button type="button" onclick="window.print()" title="Choose 'Save as PDF' in the print dialog"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-filetype-pdf"></i>
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-printer"></i>
                        PRINT</button>
                </div>

                <form method="GET" action="{{ route('admin.documents.index') }}"
                    class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <select name="per_page" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page
                            </option>
                        @endforeach
                    </select>

                    <select name="category" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All categories</option>
                        @foreach (D::CATEGORIES as $v => $l)
                            <option value="{{ $v }}" @selected(request('category') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>

                    @if ($isManager)
                        <select name="audience" onchange="this.form.submit()"
                            class="text-xs border border-gray-300 rounded-lg p-2">
                            <option value="">All audiences</option>
                            @foreach (D::AUDIENCES as $v => $l)
                                <option value="{{ $v }}" @selected(request('audience') === $v)>{{ $l }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Title, description, file name"
                        class="text-xs border border-gray-300 rounded-lg p-2 w-52 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">Search</button>

                    @if (request()->hasAny(['search', 'category', 'audience']))
                        <a href="{{ route('admin.documents.index', ['tab' => $tab]) }}"
                            class="px-3 py-2 bg-gray-200 text-xs font-bold rounded-lg">Reset</a>
                    @endif
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Document</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Audience</th>
                            <th class="px-4 py-3">Uploaded by</th>
                            @if ($isManager)
                                <th class="px-4 py-3">Downloads</th>
                            @endif
                            <th class="px-4 py-3 no-print">Open</th>
                            @if ($isManager)
                                <th class="px-4 py-3 text-right no-print">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($documents as $d)
                            @php
                                $status = $d->statusKey();
                                $payload = [
                                    'id' => $d->id,
                                    'title' => $d->title,
                                    'description' => $d->description,
                                    'category' => $d->category,
                                    'audience' => $d->audience,
                                    'effective_date' => $d->effective_date?->format('Y-m-d'),
                                    'expires_on' => $d->expires_on?->format('Y-m-d'),
                                    'external_url' => $d->external_url,
                                    'is_published' => $d->is_published,
                                    'file_name' => $d->file_name,
                                    'version' => $d->version,
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition" data-doc="{{ json_encode($payload) }}">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-800">
                                    {{ $d->shownDate()->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 max-w-sm">
                                    <div class="font-bold text-gray-900">
                                        {{ $d->title }}
                                        @if ($d->version > 1)
                                            <span
                                                class="ml-1 px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-bold">v{{ $d->version }}</span>
                                        @endif
                                        @if ($d->created_at->gt(now()->subDays(7)) && $status === 'active')
                                            <span
                                                class="ml-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase">New</span>
                                        @endif
                                        @if ($isManager && $status !== 'active')
                                            <span
                                                class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $statusBadge[$status][1] }}">{{ $statusBadge[$status][0] }}</span>
                                        @elseif (!$isManager && $status === 'expiring')
                                            <span
                                                class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $statusBadge['expiring'][1] }}">Until
                                                {{ $d->expires_on->format('Y-m-d') }}</span>
                                        @endif
                                    </div>
                                    @if ($d->description)
                                        <div class="text-[11px] text-gray-500 mt-0.5 line-clamp-2">{{ $d->description }}
                                        </div>
                                    @endif
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        @if ($d->file_path)
                                            <i class="bi bi-paperclip"></i> {{ $d->file_name }} · {{ $d->sizeLabel() }}
                                        @endif
                                        @if ($d->external_url)
                                            <i class="bi bi-link-45deg"></i> {{ parse_url($d->external_url, PHP_URL_HOST) }}
                                        @endif
                                        @if ($isManager && $d->expires_on)
                                            · expires {{ $d->expires_on->format('Y-m-d') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-blue-50 text-blue-700">{{ D::CATEGORIES[$d->category] ?? $d->category }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-gray-100 text-gray-700">{{ D::AUDIENCES[$d->audience] ?? $d->audience }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $who($d->uploader) }}</td>
                                @if ($isManager)
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-700"
                                        title="{{ $d->last_downloaded_at ? 'Last opened ' . $d->last_downloaded_at->diffForHumans() : 'Not opened yet' }}">
                                        {{ number_format($d->download_count) }}</td>
                                @endif
                                <td class="px-4 py-3 whitespace-nowrap space-x-1 no-print">
                                    @if ($d->file_path)
                                        @if ($d->isPreviewable())
                                            <a href="{{ route('admin.documents.download', [$d, 'view' => 1]) }}"
                                                target="_blank"
                                                class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-[11px] font-bold rounded-md"><i class="bi bi-eye"></i>
                                                View</a>
                                        @endif
                                        <a href="{{ route('admin.documents.download', $d) }}"
                                            class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold rounded-md"><i class="bi bi-download"></i>
                                            Download</a>
                                    @endif
                                    @if ($d->external_url)
                                        <a href="{{ route('admin.documents.download', $d) }}" target="_blank"
                                            rel="noopener"
                                            class="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold rounded-md"><i class="bi bi-link-45deg"></i>
                                            Open link</a>
                                    @endif
                                </td>
                                @if ($isManager)
                                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                        <button type="button" class="edit-btn p-1 hover:text-amber-600"
                                            title="Edit"><i class="bi bi-pencil"></i></button>

                                        <form action="{{ route('admin.documents.publish', $d) }}" method="POST"
                                            class="inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="p-1 hover:text-indigo-600"
                                                title="{{ $d->is_published ? 'Move to drafts (hide from audience)' : 'Publish' }}">
                                                <i class="bi {{ $d->is_published ? 'bi-eye-slash' : 'bi-megaphone' }}"></i>
                                            </button>
                                        </form>

                                        @can('documents.delete')
                                            <form action="{{ route('admin.documents.destroy', $d) }}" method="POST"
                                                class="inline"
                                                onsubmit="return confirm('Delete &quot;{{ addslashes($d->title) }}&quot;?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="p-1 hover:text-rose-600"
                                                    title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isManager ? 8 : 6 }}" class="px-4 py-8 text-center text-gray-500">No
                                    documents found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                <div>Showing {{ $documents->firstItem() ?? 0 }} to {{ $documents->lastItem() ?? 0 }} of
                    {{ $documents->total() }} entries</div>
                <div class="no-print">{{ $documents->links() }}</div>
            </div>
        </div>
    </div>

    @if ($isManager)
        <!-- Add / edit modal -->
        <div id="docModal" style="display:none;"
            class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl p-6 max-h-[92vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <h3 class="text-md font-bold" id="docModalTitle">Add Document</h3>
                    <button type="button" class="close-modal text-xl">&times;</button>
                </div>

                <form id="docForm" method="POST" action="{{ route('admin.documents.store') }}"
                    enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_method" value="PUT" id="methodField" disabled>
                    <input type="hidden" name="_mode" value="add" id="modeField">
                    <input type="hidden" name="_id" value="" id="idField">

                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Title *</label>
                        <input type="text" name="title" required maxlength="150"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Category *</label>
                            <select name="category" required class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (D::CATEGORIES as $v => $l)
                                    <option value="{{ $v }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Who can see it *</label>
                            <select name="audience" required class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (D::AUDIENCES as $v => $l)
                                    <option value="{{ $v }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Description</label>
                        <textarea name="description" rows="2" maxlength="1000" placeholder="What is this document for?"
                            class="w-full text-xs border rounded-lg p-2.5"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">File</label>
                        <input type="file" name="file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" class="w-full text-xs">
                        <p class="text-[11px] text-gray-400 mt-1" id="fileHint">PDF, Word, Excel, PowerPoint or image, up
                            to 10 MB.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Or link to an online document</label>
                        <input type="url" name="external_url" maxlength="500" placeholder="https://…"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Visible from</label>
                            <input type="date" name="effective_date" class="w-full text-xs border rounded-lg p-2.5">
                            <p class="text-[11px] text-gray-400 mt-1">Leave empty to show straight away.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Hide after</label>
                            <input type="date" name="expires_on" class="w-full text-xs border rounded-lg p-2.5">
                            <p class="text-[11px] text-gray-400 mt-1">Leave empty to keep it visible.</p>
                        </div>
                    </div>

                    <label class="flex items-start gap-2 bg-gray-50 border rounded-lg p-3 text-xs text-gray-700">
                        <input type="checkbox" name="is_published" value="1" checked class="mt-0.5">
                        <span><b>Publish now.</b> Untick to save it as a draft that only staff managing documents can
                            see.</span>
                    </label>

                    <div class="flex justify-end space-x-2 pt-1">
                        <button type="button"
                            class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg"
                            id="docSubmit">Save Document</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function() {
                var STORE_URL = @json(route('admin.documents.store'));
                var BASE_URL = @json(url('/admin/documents'));
                var modal = document.getElementById('docModal');
                var form = document.getElementById('docForm');

                function openForm(mode, d) {
                    d = d || {};
                    form.reset();
                    var edit = mode === 'edit';

                    form.setAttribute('action', edit ? BASE_URL + '/' + d.id : STORE_URL);
                    document.getElementById('methodField').disabled = !edit;
                    document.getElementById('modeField').value = mode;
                    document.getElementById('idField').value = edit ? d.id : '';
                    document.getElementById('docModalTitle').textContent = edit ? 'Edit Document' : 'Add Document';
                    document.getElementById('docSubmit').textContent = edit ? 'Save Changes' : 'Save Document';

                    form.elements['title'].value = d.title || '';
                    form.elements['category'].value = d.category || 'general';
                    form.elements['audience'].value = d.audience || 'all';
                    form.elements['description'].value = d.description || '';
                    form.elements['external_url'].value = d.external_url || '';
                    form.elements['effective_date'].value = d.effective_date || '';
                    form.elements['expires_on'].value = d.expires_on || '';
                    form.elements['is_published'].checked = (d.is_published === undefined) ? true : !!d.is_published;

                    document.getElementById('fileHint').textContent = (edit && d.file_name) ?
                        'Current file: ' + d.file_name + ' (version ' + d.version +
                        '). Choose a new file to replace it and publish version ' + (d.version + 1) + '.' :
                        'PDF, Word, Excel, PowerPoint or image, up to 10 MB.';

                    modal.style.display = 'flex';
                }

                document.addEventListener('click', function(e) {
                    if (e.target.closest('#openAddBtn')) return openForm('add');

                    var edit = e.target.closest('.edit-btn');
                    if (edit) {
                        try {
                            openForm('edit', JSON.parse(edit.closest('tr').dataset.doc));
                        } catch (err) {
                            console.error(err);
                        }
                        return;
                    }

                    if (e.target.closest('.close-modal') || e.target === modal) modal.style.display = 'none';
                });

                // Reopen the form with the user's input if validation failed
                @if ($errors->any() && old('_mode'))
                    (function() {
                        var o = @json(old());
                        var isEdit = o._mode === 'edit' && o._id;
                        openForm(isEdit ? 'edit' : 'add', {
                            id: o._id,
                            title: o.title,
                            category: o.category,
                            audience: o.audience,
                            description: o.description,
                            external_url: o.external_url,
                            effective_date: o.effective_date,
                            expires_on: o.expires_on,
                            is_published: !!o.is_published
                        });
                    })();
                @endif
            })();
        </script>
    @endif
@endsection
