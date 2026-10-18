@extends('layouts.admin')

@section('content')
    @php
        // Falls back to empty if the current_academic_year() helper isn't loaded yet
$defaultAcademicYear = function_exists('current_academic_year') ? current_academic_year() : '';
    @endphp

    <div x-data="{
        openModal: false,
        editModalOpen: false,
        currentStudent: {},
        activeModal: null,
        selectedStudent: ''
    }" @keydown.escape.window="openModal = false; editModalOpen = false; activeModal = null"
        class="space-y-6">

        <!-- Header & Add Button -->
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-800">Students Management</h2>
            <button type="button" @click="openModal = true"
                class="px-4 py-2 bg-emerald-600 text-white font-semibold text-sm rounded-lg hover:bg-emerald-700 transition shadow-sm">
                + Add Student
            </button>
        </div>

        @if (session('success'))
            <div class="p-4 bg-green-100 text-green-700 rounded-lg text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 bg-red-100 text-red-700 rounded-lg text-sm">
                <p class="font-bold mb-1">Please fix the following:</p>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Status Filter Ribbon -->
        <div
            class="bg-white p-3 rounded-xl shadow-sm border border-gray-200 grid grid-cols-2 md:grid-cols-6 gap-2 text-center text-xs font-bold">
            <a href="{{ route('admin.students.index') }}"
                class="p-3 rounded-lg {{ request('status') == '' ? 'bg-slate-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                ALL ({{ $counts['all'] ?? 0 }})
            </a>
            <a href="{{ route('admin.students.index', ['status' => 'active']) }}"
                class="p-3 rounded-lg {{ request('status') == 'active' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                ACTIVE ({{ $counts['active'] ?? 0 }})
            </a>
            <a href="{{ route('admin.students.index', ['status' => 'inactive']) }}"
                class="p-3 rounded-lg {{ request('status') == 'inactive' ? 'bg-gray-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                INACTIVE ({{ $counts['inactive'] ?? 0 }})
            </a>
            <a href="{{ route('admin.students.index', ['status' => 'transferred']) }}"
                class="p-3 rounded-lg {{ request('status') == 'transferred' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100' }}">
                TRANSFERRED ({{ $counts['transferred'] ?? 0 }})
            </a>
            <a href="{{ route('admin.students.index', ['status' => 'passed_out']) }}"
                class="p-3 rounded-lg {{ request('status') == 'passed_out' ? 'bg-pink-600 text-white' : 'bg-pink-50 text-pink-700 hover:bg-pink-100' }}">
                PASSED OUT ({{ $counts['passed_out'] ?? 0 }})
            </a>
            <a href="{{ route('admin.students.index', ['status' => 'suspended']) }}"
                class="p-3 rounded-lg {{ request('status') == 'suspended' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}">
                SUSPENDED ({{ $counts['suspended'] ?? 0 }})
            </a>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="{{ route('admin.students.index') }}"
            class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 grid grid-cols-1 md:grid-cols-6 gap-3 items-end text-xs">
            {{-- Keep the active status tab when filtering --}}
            @if (request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div>
                <label class="block font-bold text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search students..."
                    class="w-full border-gray-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block font-bold text-gray-700 mb-1">Class</label>
                <select name="class" class="w-full border-gray-300 rounded-lg text-xs">
                    <option value="">All Classes</option>
                    @foreach ($classes as $className)
                        <option value="{{ $className }}" {{ request('class') == $className ? 'selected' : '' }}>
                            {{ $className }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-bold text-gray-700 mb-1">Gender</label>
                <select name="gender" class="w-full border-gray-300 rounded-lg text-xs">
                    <option value="">All Genders</option>
                    <option value="Male" {{ request('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ request('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                    <option value="Other" {{ request('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <div>
                <label class="block font-bold text-gray-700 mb-1">House</label>
                <select name="house" class="w-full border-gray-300 rounded-lg text-xs">
                    <option value="">All Houses</option>
                    @foreach (['Red', 'Blue', 'Green', 'Yellow'] as $houseOption)
                        <option value="{{ $houseOption }}" {{ request('house') == $houseOption ? 'selected' : '' }}>
                            {{ $houseOption }}</option>
                    @endforeach
                </select>
            </div>
            <!-- Academic Year Filter Dropdown -->
            <div>
                <label class="block font-bold text-gray-700 mb-1">Academic Year</label>
                <select name="academic_year" class="form-select text-sm border-gray-300 rounded-md">
                    @foreach ($academicYears as $year)
                        <option value="{{ $year }}" {{ $academicYear == $year ? 'selected' : '' }}>
                            {{ $year }} {{ $year == current_academic_year() ? '(Current)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center space-x-2 pt-4">
                <input type="checkbox" id="filter_allergies" name="has_allergies" value="1"
                    {{ request('has_allergies') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                <label for="filter_allergies" class="font-bold text-rose-600">Has allergies</label>
            </div>
            <div class="flex space-x-2">
                <button type="submit"
                    class="flex-1 bg-indigo-600 text-white py-2 rounded-lg font-bold hover:bg-indigo-700">Filter</button>
                <a href="{{ route('admin.students.index') }}"
                    class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg font-bold hover:bg-gray-200">Clear</a>
            </div>
        </form>

        <!-- Students Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-xs tracking-wider">
                            <th class="p-4">Adm #</th>
                            <th class="p-4">Student</th>
                            <th class="p-4">EAL</th>
                            <th class="p-4">Class</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">House</th>
                            <th class="p-4">Allergies</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-xs">
                        @forelse($students as $student)
                            @php
                                $initials = strtoupper(
                                    mb_substr($student->first_name, 0, 1) . mb_substr($student->last_name, 0, 1),
                                );

                                $houseColors = [
                                    'Red' => 'bg-rose-600',
                                    'Blue' => 'bg-blue-600',
                                    'Green' => 'bg-emerald-600',
                                    'Yellow' => 'bg-amber-500',
                                ];
                                $houseClass = $houseColors[$student->house] ?? 'bg-gray-500';

                                $statusColors = [
                                    'active' => 'bg-emerald-50 text-emerald-700',
                                    'inactive' => 'bg-gray-100 text-gray-700',
                                    'transferred' => 'bg-purple-50 text-purple-700',
                                    'passed_out' => 'bg-pink-50 text-pink-700',
                                    'suspended' => 'bg-rose-50 text-rose-700',
                                ];
                                $statusClass = $statusColors[$student->status] ?? 'bg-gray-100 text-gray-700';

                                // Safe data for Alpine (dates formatted for <input type="date">, allergies as real boolean)
                                $editData = [
                                    'id' => $student->id,
                                    'update_url' => route('admin.students.update', $student->id),
                                    'first_name' => $student->first_name,
                                    'last_name' => $student->last_name,
                                    'gender' => $student->gender,
                                    'dob' => $student->dob
                                        ? \Illuminate\Support\Carbon::parse($student->dob)->format('Y-m-d')
                                        : '',
                                    'languages_spoken' => $student->languages_spoken ?? '',
                                    'address' => $student->address ?? '',
                                    'class_applied' => $student->class_applied,
                                    'roll_no' => $student->roll_no ?? 1,
                                    'academic_year' => $student->academic_year ?? '',
                                    'status' => $student->status ?? 'active',
                                    'house' => $student->house,
                                    'eal' => $student->eal,
                                    'admission_date' => $student->admission_date
                                        ? \Illuminate\Support\Carbon::parse($student->admission_date)->format('Y-m-d')
                                        : '',
                                    'previous_school' => $student->previous_school ?? '',
                                    'blood_group' => $student->blood_group ?? '',
                                    'emergency_contact' => $student->emergency_contact ?? '',
                                    'hobbies' => $student->hobbies ?? '',
                                    'has_allergies' => (bool) $student->has_allergies,
                                ];
                                $fullName = $student->first_name . ' ' . $student->last_name;
                            @endphp
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4 font-bold text-indigo-600">{{ $student->adm_number }}</td>
                                <td class="p-4">
                                    <div class="flex items-center space-x-2">
                                        <span
                                            class="w-7 h-7 shrink-0 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-[10px]">{{ $initials }}</span>
                                        <div class="font-semibold text-gray-900">
                                            {{ $fullName }}<br>
                                            <span class="text-[10px] text-gray-400 font-normal">Roll
                                                {{ $student->roll_no ?? 1 }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4"><span
                                        class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded font-bold text-[10px]">{{ $student->eal }}</span>
                                </td>
                                <td class="p-4 text-gray-700 font-medium">{{ $student->class_applied }}</td>
                                <td class="p-4">
                                    <span
                                        class="px-2.5 py-1 rounded-full font-bold text-[10px] uppercase {{ $statusClass }}">{{ str_replace('_', ' ', $student->status) }}</span>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="px-2 py-0.5 text-white rounded font-bold text-[10px] {{ $houseClass }}">{{ $student->house }}</span>
                                </td>
                                <td class="p-4">
                                    @if ($student->has_allergies)
                                        <span class="text-rose-600 font-bold">⚠️ Allergy</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="p-4 text-right">
                                    <div class="inline-flex items-center space-x-1">
                                        <button type="button"
                                            @click="activeModal = 'fee'; selectedStudent = {{ \Illuminate\Support\Js::from($fullName) }}"
                                            class="p-1.5 bg-emerald-50 text-emerald-600 rounded hover:bg-emerald-100"
                                            title="Fee Summary">💰</button>
                                        <button type="button"
                                            @click="activeModal = 'attendance'; selectedStudent = {{ \Illuminate\Support\Js::from($fullName) }}"
                                            class="p-1.5 bg-blue-50 text-blue-600 rounded hover:bg-blue-100"
                                            title="Attendance">📅</button>
                                        <button type="button"
                                            @click="activeModal = 'results'; selectedStudent = {{ \Illuminate\Support\Js::from($fullName) }}"
                                            class="p-1.5 bg-purple-50 text-purple-600 rounded hover:bg-purple-100"
                                            title="Results">📊</button>
                                        <button type="button"
                                            @click="activeModal = 'parents'; selectedStudent = {{ \Illuminate\Support\Js::from($fullName) }}"
                                            class="p-1.5 bg-sky-50 text-sky-600 rounded hover:bg-sky-100"
                                            title="Parents">👪</button>
                                        <button type="button"
                                            @click="activeModal = 'idcard'; selectedStudent = {{ \Illuminate\Support\Js::from($fullName) }}"
                                            class="p-1.5 bg-amber-50 text-amber-600 rounded hover:bg-amber-100"
                                            title="ID Card">🪪</button>
                                        <button type="button"
                                            @click="currentStudent = {{ \Illuminate\Support\Js::from($editData) }}; editModalOpen = true"
                                            class="p-1.5 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100"
                                            title="Edit Student">✏️</button>
                                        <form action="{{ route('admin.students.status', $student->id) }}" method="POST"
                                            class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" onchange="this.form.submit()" title="Change Status"
                                                class="text-[10px] border-gray-300 rounded p-1 w-24">
                                                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'transferred' => 'Transferred', 'passed_out' => 'Passed Out', 'suspended' => 'Suspended'] as $value => $label)
                                                    <option value="{{ $value }}"
                                                        {{ $student->status === $value ? 'selected' : '' }}>
                                                        {{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                        <form action="{{ route('admin.students.destroy', $student->id) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Delete this student? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="p-1.5 bg-rose-50 text-rose-600 rounded hover:bg-rose-100"
                                                title="Delete">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-400">No students found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200">
                {{ $students->links() }}
            </div>
        </div>

        <!-- Add Student Modal -->
        <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
            style="display: none;">
            <div @click.outside="openModal = false"
                class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden border border-gray-200">
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="font-bold text-base">Add Student</h3>
                    <button type="button" @click="openModal = false"
                        class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>
                <form action="{{ route('admin.students.store') }}" method="POST"
                    class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">

                        <!-- PERSONAL INFORMATION -->
                        <h4
                            class="text-xs font-bold text-gray-400 uppercase tracking-wider md:col-span-2 pt-2 border-b border-gray-100 pb-1">
                            Personal Information</h4>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">First Name *</label>
                            <input type="text" name="first_name" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Last Name *</label>
                            <input type="text" name="last_name" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Gender *</label>
                            <select name="gender" required class="w-full border-gray-300 rounded-lg text-xs">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Date of Birth *</label>
                            <input type="date" name="dob" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Languages Spoken</label>
                            <input type="text" name="languages_spoken" placeholder="e.g. English, Nepali"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-bold text-gray-700 uppercase mb-1">Address</label>
                            <input type="text" name="address" placeholder="e.g. Birendranagar, Surkhet"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>

                        <!-- ACADEMIC INFORMATION -->
                        <h4
                            class="text-xs font-bold text-gray-400 uppercase tracking-wider md:col-span-2 pt-4 border-b border-gray-100 pb-1">
                            Academic Information</h4>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Class *</label>
                            <select name="class_applied" required class="w-full border-gray-300 rounded-lg text-xs">
                                @foreach ($classes as $className)
                                    <option value="{{ $className }}">{{ $className }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Roll No</label>
                            <input type="number" name="roll_no" value="1" min="1"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Academic Year *</label>
                            <input type="text" name="academic_year"
                                value="{{ old('academic_year', $defaultAcademicYear) }}" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">House</label>
                            <select name="house" class="w-full border-gray-300 rounded-lg text-xs">
                                <option value="Red">Red</option>
                                <option value="Blue">Blue</option>
                                <option value="Green">Green</option>
                                <option value="Yellow">Yellow</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">English Level (EAL)</label>
                            <input type="text" name="eal" value="Native"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Admission Date</label>
                            <input type="date" name="admission_date"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-bold text-gray-700 uppercase mb-1">Previous School</label>
                            <input type="text" name="previous_school"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>

                        <!-- MEDICAL & ADDITIONAL DETAILS -->
                        <h4
                            class="text-xs font-bold text-gray-400 uppercase tracking-wider md:col-span-2 pt-4 border-b border-gray-100 pb-1">
                            Medical &amp; Additional Details</h4>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Blood Group</label>
                            <input type="text" name="blood_group" placeholder="e.g. A+"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Emergency Contact</label>
                            <input type="text" name="emergency_contact" placeholder="Phone / Name"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-bold text-gray-700 uppercase mb-1">Hobbies</label>
                            <input type="text" name="hobbies" placeholder="e.g. Football, Coding"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div class="md:col-span-2 flex items-center space-x-2 pt-2">
                            {{-- Hidden 0 ensures an unchecked box still submits false --}}
                            <input type="hidden" name="has_allergies" value="0">
                            <input type="checkbox" id="add_has_allergies" name="has_allergies" value="1"
                                class="rounded border-gray-300 text-indigo-600">
                            <label for="add_has_allergies" class="font-bold text-gray-700 text-xs">Has Allergies / Medical
                                Notes</label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="openModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-xs font-bold">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold">Save Student</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Student Modal -->
        <div x-show="editModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
            style="display: none;">
            <div @click.outside="editModalOpen = false"
                class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden border border-gray-200">
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="font-bold text-base">Edit Student</h3>
                    <button type="button" @click="editModalOpen = false"
                        class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>
                <form :action="currentStudent.update_url" method="POST"
                    class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">

                        <!-- PERSONAL INFORMATION -->
                        <h4
                            class="text-xs font-bold text-gray-400 uppercase tracking-wider md:col-span-2 pt-2 border-b border-gray-100 pb-1">
                            Personal Information</h4>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">First Name *</label>
                            <input type="text" name="first_name" x-model="currentStudent.first_name" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Last Name *</label>
                            <input type="text" name="last_name" x-model="currentStudent.last_name" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Gender *</label>
                            <select name="gender" x-model="currentStudent.gender" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Date of Birth *</label>
                            <input type="date" name="dob" x-model="currentStudent.dob" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Languages Spoken</label>
                            <input type="text" name="languages_spoken" x-model="currentStudent.languages_spoken"
                                placeholder="e.g. English, Nepali" class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-bold text-gray-700 uppercase mb-1">Address</label>
                            <input type="text" name="address" x-model="currentStudent.address"
                                placeholder="e.g. Birendranagar, Surkhet"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>

                        <!-- ACADEMIC INFORMATION -->
                        <h4
                            class="text-xs font-bold text-gray-400 uppercase tracking-wider md:col-span-2 pt-4 border-b border-gray-100 pb-1">
                            Academic Information</h4>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Class *</label>
                            <select name="class_applied" x-model="currentStudent.class_applied" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                                @foreach ($classes as $className)
                                    <option value="{{ $className }}">{{ $className }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Roll No</label>
                            <input type="number" name="roll_no" x-model="currentStudent.roll_no" min="1"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Academic Year *</label>
                            <input type="text" name="academic_year" x-model="currentStudent.academic_year" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Status *</label>
                            <select name="status" x-model="currentStudent.status" required
                                class="w-full border-gray-300 rounded-lg text-xs">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="transferred">Transferred</option>
                                <option value="passed_out">Passed Out</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">House</label>
                            <select name="house" x-model="currentStudent.house"
                                class="w-full border-gray-300 rounded-lg text-xs">
                                <option value="Red">Red</option>
                                <option value="Blue">Blue</option>
                                <option value="Green">Green</option>
                                <option value="Yellow">Yellow</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">English Level (EAL)</label>
                            <input type="text" name="eal" x-model="currentStudent.eal"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Admission Date</label>
                            <input type="date" name="admission_date" x-model="currentStudent.admission_date"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Previous School</label>
                            <input type="text" name="previous_school" x-model="currentStudent.previous_school"
                                class="w-full border-gray-300 rounded-lg text-xs">
                        </div>

                        <!-- MEDICAL & ADDITIONAL DETAILS -->
                        <h4
                            class="text-xs font-bold text-gray-400 uppercase tracking-wider md:col-span-2 pt-4 border-b border-gray-100 pb-1">
                            Medical &amp; Additional Details</h4>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Blood Group</label>
                            <input type="text" name="blood_group" x-model="currentStudent.blood_group"
                                placeholder="e.g. A+" class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Emergency Contact</label>
                            <input type="text" name="emergency_contact" x-model="currentStudent.emergency_contact"
                                placeholder="Phone / Name" class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-bold text-gray-700 uppercase mb-1">Hobbies</label>
                            <input type="text" name="hobbies" x-model="currentStudent.hobbies"
                                placeholder="e.g. Football, Coding" class="w-full border-gray-300 rounded-lg text-xs">
                        </div>
                        <div class="md:col-span-2 flex items-center space-x-2 pt-2">
                            <input type="hidden" name="has_allergies" value="0">
                            <input type="checkbox" id="edit_has_allergies" name="has_allergies" value="1"
                                x-model="currentStudent.has_allergies" class="rounded border-gray-300 text-indigo-600">
                            <label for="edit_has_allergies" class="font-bold text-gray-700 text-xs">Has Allergies /
                                Medical Notes</label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="editModalOpen = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-xs font-bold">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold">Update Student</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Universal Action Modal (Fee, Attendance, Results, Parents, ID Card) -->
        <div x-show="activeModal !== null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
            style="display: none;">
            <div @click.outside="activeModal = null"
                class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-200">
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="font-bold text-base capitalize"
                        x-text="(activeModal === 'idcard' ? 'ID Card' : activeModal) + ' — ' + selectedStudent"></h3>
                    <button type="button" @click="activeModal = null"
                        class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>
                <div class="p-6 text-sm text-gray-600 space-y-4">
                    <p>Showing sample records for <strong x-text="selectedStudent"></strong>.</p>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 text-center font-medium">
                        Module details for <span x-text="activeModal"></span> will appear here.
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end">
                    <button type="button" @click="activeModal = null"
                        class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">Close</button>
                </div>
            </div>
        </div>

    </div>
@endsection
