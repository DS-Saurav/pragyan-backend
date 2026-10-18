@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-800">Admin Dashboard</h2>
            <span class="text-xs font-semibold px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full">Academic Year: {{ current_academic_year() }}</span>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Total Students</p>
                    <h3 class="text-2xl font-extrabold text-gray-800 mt-1">{{\App\Models\Student::count()}}</h3>
                </div>
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center font-bold text-lg">🎓</div>
            </div>

            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Admissions</p>
                    <h3 class="text-2xl font-extrabold text-gray-800 mt-1">{{\App\Models\Admission::count()}}</h3>
                </div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center font-bold text-lg">📝</div>
            </div>

            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Classes & Sections</p>
                    <h3 class="text-2xl font-extrabold text-gray-800 mt-1">{{\App\Models\SchoolClass::count()}}</h3>
                </div>
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center font-bold text-lg">🏫</div>
            </div>

            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">System Users</p>
                    <h3 class="text-2xl font-extrabold text-gray-800 mt-1">{{\App\Models\User::count()}}</h3>
                </div>
                <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center font-bold text-lg">👥</div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white p-8 rounded-2xl shadow-lg flex justify-between items-center">
            <div>
                <h3 class="text-2xl font-extrabold">Welcome back, {{ Auth::user()->name }}! 🏫</h3>
                <p class="text-slate-300 text-sm mt-2">Pragyan Pathashala School Management System is up and running smoothly.</p>
            </div>
            <div class="hidden md:flex space-x-3">
                <a href="{{ route('admin.students.index') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow">Manage Students</a>
                <a href="{{ route('admin.classes.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg border border-slate-700">View Classes</a>
            </div>
        </div>
    </div>
@endsection