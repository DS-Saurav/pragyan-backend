@extends('layouts.admin')

@section('content')
    {{-- openModal starts true when the last submit failed validation, so the errors are actually visible --}}
    <div x-data="{ openModal: {{ $errors->any() ? 'true' : 'false' }} }" @keydown.escape.window="openModal = false" class="space-y-6">

        <!-- Page Header & New Registration Button -->
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Admissions Management</h2>
            </div>
            <button type="button" @click="openModal = true"
                class="px-4 py-2 bg-emerald-600 text-white font-semibold text-sm rounded-lg hover:bg-emerald-700 transition shadow-sm flex items-center space-x-1">
                <span>+ New Registration</span>
            </button>
        </div>

        <!-- Success / Error Messages -->
        @if (session('success'))
            <div class="p-4 bg-green-100 text-green-700 rounded-lg text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="p-4 bg-rose-100 text-rose-700 rounded-lg text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif

        <!-- Status Filter Tabs -->
        <div class="bg-white p-2 rounded-xl shadow-sm border border-gray-200 flex space-x-2">
            <a href="{{ route('admin.admissions.index') }}"
                class="flex-1 text-center py-2.5 rounded-lg text-sm font-bold {{ request('status') == '' ? 'bg-slate-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                ALL ({{ $counts['all'] ?? 0 }})
            </a>
            <a href="{{ route('admin.admissions.index', ['status' => 'registered']) }}"
                class="flex-1 text-center py-2.5 rounded-lg text-sm font-bold {{ request('status') == 'registered' ? 'bg-sky-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                REGISTERED ({{ $counts['registered'] ?? 0 }})
            </a>
            <a href="{{ route('admin.admissions.index', ['status' => 'admitted']) }}"
                class="flex-1 text-center py-2.5 rounded-lg text-sm font-bold {{ request('status') == 'admitted' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                ADMITTED ({{ $counts['admitted'] ?? 0 }})
            </a>
            <a href="{{ route('admin.admissions.index', ['status' => 'rejected']) }}"
                class="flex-1 text-center py-2.5 rounded-lg text-sm font-bold {{ request('status') == 'rejected' ? 'bg-rose-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                REJECTED ({{ $counts['rejected'] ?? 0 }})
            </a>
        </div>

        <!-- Data Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-xs tracking-wider">
                            <th class="p-4">Reg #</th>
                            <th class="p-4">Applicant</th>
                            <th class="p-4">Gender</th>
                            <th class="p-4">DOB</th>
                            <th class="p-4">Class Applied</th>
                            <th class="p-4">Academic Year</th>
                            <th class="p-4">Parent / Guardian</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($admissions as $admission)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4 font-bold text-gray-700">{{ $admission->reg_number }}</td>
                                <td class="p-4 font-semibold text-gray-900">{{ $admission->first_name }}
                                    {{ $admission->last_name }}</td>
                                <td class="p-4 text-gray-600">{{ $admission->gender }}</td>
                                <td class="p-4 text-gray-600">
                                    {{ $admission->dob ? \Illuminate\Support\Carbon::parse($admission->dob)->format('Y-m-d') : '—' }}
                                </td>
                                <td class="p-4 text-gray-600">{{ $admission->class_applied }}</td>
                                <td class="p-4 text-gray-600">{{ $admission->academic_year }}</td>
                                <td class="p-4 text-gray-600">
                                    {{ $admission->parent_name }}<br>
                                    <span class="text-xs text-gray-400">{{ $admission->mobile }}</span>
                                </td>
                                <td class="p-4">
                                    @if ($admission->status == 'registered')
                                        <span
                                            class="px-2.5 py-1 bg-sky-50 text-sky-700 rounded-full text-xs font-bold">REGISTERED</span>
                                    @elseif($admission->status == 'admitted')
                                        <span
                                            class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-bold">ADMITTED</span>
                                    @else
                                        <span
                                            class="px-2.5 py-1 bg-rose-50 text-rose-700 rounded-full text-xs font-bold cursor-help"
                                            title="{{ $admission->rejection_reason }}">REJECTED</span>
                                    @endif
                                </td>
                                <td class="p-4 text-right">
                                    <div class="inline-flex items-center space-x-1">
                                        <!-- Quick status toggles -->
                                        <form action="{{ route('admin.admissions.status', $admission->id) }}"
                                            method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="admitted">
                                            <button type="submit"
                                                class="p-1.5 bg-emerald-50 text-emerald-600 rounded hover:bg-emerald-100 text-xs"
                                                title="Admit">✅</button>
                                        </form>
                                        <form action="{{ route('admin.admissions.status', $admission->id) }}"
                                            method="POST" class="inline"
                                            onsubmit="return confirm('Reject this applicant?');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="hidden" name="rejection_reason" value="Did not meet criteria.">
                                            <button type="submit"
                                                class="p-1.5 bg-rose-50 text-rose-600 rounded hover:bg-rose-100 text-xs"
                                                title="Reject">❌</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-8 text-center text-gray-400">No registration records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- New Registration Modal -->
        <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
            style="display: none;">
            <div @click.outside="openModal = false"
                class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden border border-gray-200">
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="font-bold text-base">New Registration</h3>
                    <button type="button" @click="openModal = false"
                        class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form action="{{ route('admin.admissions.store') }}" method="POST"
                    class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    @csrf

                    @if ($errors->any())
                        <div class="p-4 bg-rose-100 text-rose-700 rounded-lg text-xs font-bold">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">First Name *</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Last Name *</label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" required
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Gender *</label>
                            <select name="gender" required class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Date of Birth *</label>
                            <input type="date" name="dob" value="{{ old('dob') }}" required
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Class Applied For *</label>
                            <select name="class_applied" required class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="">Select Class...</option>
                                @foreach ($classes as $className)
                                    <option value="{{ $className }}"
                                        {{ old('class_applied') == $className ? 'selected' : '' }}>{{ $className }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Academic Year *</label>
                            <input type="text" name="academic_year"
                                value="{{ old('academic_year', current_academic_year()) }}" required
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Address *</label>
                            <input type="text" name="address" value="{{ old('address') }}"
                                placeholder="e.g. Birendranagar-6, Surkhet" required
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Parent Name *</label>
                            <input type="text" name="parent_name" value="{{ old('parent_name') }}" required
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Mobile *</label>
                            <input type="text" name="mobile" value="{{ old('mobile') }}" placeholder="98XXXXXXXX"
                                required class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="openModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">Save
                            Registration</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
