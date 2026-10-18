<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Pragyan Pathashala - Admin Control Panel') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-900">
                <h3 class="text-lg font-bold mb-2">Welcome back, Admin!</h3>
                <p class="text-gray-600 mb-4">Manage classes, sections, and school structure below.</p>

                <!-- Navigation Link in Body -->
                <a href="{{ route('admin.classes.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 transition ease-in-out duration-150">
                    Manage Classes & Sections &rarr;
                </a>
            </div>
        </div>
    </div>
</x-app-layout>