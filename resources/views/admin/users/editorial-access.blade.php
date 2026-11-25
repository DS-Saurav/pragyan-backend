@extends('layouts.admin')

@section('title', 'Public Editorial Access')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Public Editorial Access"
            subtitle="Grant only the public content actions this staff member needs. Changes are recorded below.">
            <x-slot name="actions">
                <a href="{{ route('admin.users.index') }}" class="ui-btn ui-btn-secondary">Back to users</a>
            </x-slot>
        </x-ui.page-header>

        @if (session('success'))
            <div class="ui-alert ui-alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="ui-alert ui-alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <section class="ui-card p-5">
            <h2 class="text-lg font-bold text-slate-900">{{ $recipient->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $recipient->email }} · {{ ucfirst($recipient->status) }} ·
                {{ $recipient->userRoles()->pluck('role')->implode(', ') ?: 'No role' }}</p>
            @if ($recipient->hasRole('admin'))
                <p class="mt-3 text-sm text-slate-700">Admins already have all capabilities. Direct grants are unnecessary.</p>
            @elseif (! $eligible)
                <p class="mt-3 text-sm text-rose-700">Only an active staff account without a Parent or Student role can receive these grants. Any existing grants may be removed below.</p>
            @else
                <p class="mt-3 text-sm text-slate-700">Each checkbox grants exactly one action. Authors still cannot approve their own work.</p>
            @endif
        </section>

        <form action="{{ route('admin.users.editorial-access.update', $recipient) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            @if ($eligible)
                <div class="grid gap-5 lg:grid-cols-2">
                    @foreach ($groups as $group => $permissions)
                        <fieldset class="ui-card p-5">
                            <legend class="px-1 text-base font-bold text-slate-900">{{ $group }}</legend>
                            <div class="mt-3 space-y-3">
                                @foreach ($permissions as $permission => $label)
                                    <label class="flex items-start gap-3 text-sm text-slate-700">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission }}"
                                            @checked(in_array($permission, old('permissions', $granted), true))
                                            class="mt-1 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
                                        <span>{{ $label }} <code class="text-xs text-slate-500">{{ $permission }}</code></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            @endif

            @if ($eligible || $granted !== [])
                <button type="submit" class="ui-btn ui-btn-primary">
                    {{ $eligible ? 'Save public editorial access' : 'Revoke all public editorial access' }}
                </button>
            @endif
        </form>

        <section class="ui-card p-5">
            <h2 class="text-base font-bold text-slate-900">Recent grant history</h2>
            @forelse ($events as $event)
                <p class="mt-3 border-t border-slate-200 pt-3 text-sm text-slate-700">
                    {{ ucfirst($event->action) }} <code>{{ $event->permission }}</code> by
                    {{ $event->actor?->name ?? 'Former administrator' }} on
                    {{ $event->created_at?->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }} Nepal time.
                </p>
            @empty
                <p class="mt-3 text-sm text-slate-600">No grants or revocations have been recorded.</p>
            @endforelse
        </section>
    </div>
@endsection
