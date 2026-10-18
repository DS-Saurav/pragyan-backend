@extends('layouts.admin')

@section('content')
    <div class="max-w-4xl mx-auto bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Edit Student Record: {{ $student->first_name }} {{ $student->last_name }}</h2>

        <form action="{{ route('admin.students.update', $student->id) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Gender</label>
                    <select name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
                        <option value="Male" {{ $student->gender == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ $student->gender == 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other" {{ $student->gender == 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Date of Birth</label>
                    <input type="date" name="dob" value="{{ old('dob', $student->dob) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Class</label>
                    <select name="class_applied" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
                        @foreach($classes as $cls)
                            @php
                                $className = trim("{$cls->name} {$cls->section}");
                            @endphp
                            <option value="{{ $className }}" {{ $student->class_applied == $className ? 'selected' : '' }}>{{ $className }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
                        <option value="active" {{ $student->status == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $student->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="transferred" {{ $student->status == 'transferred' ? 'selected' : '' }}>Transferred</option>
                        <option value="passed_out" {{ $student->status == 'passed_out' ? 'selected' : '' }}>Passed Out</option>
                        <option value="suspended" {{ $student->status == 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-6">
                <a href="{{ route('admin.students.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-bold">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold">Update Student</button>
            </div>
        </form>
    </div>
@endsection