@extends('layouts.admin')

@section('title', 'Public Identity & Visit')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Public Identity & Visit"
            subtitle="Manage only the verified contact and visit details shown to families. This does not change internal school settings.">
            <x-slot name="actions">
                @can('public_identity.manage')
                    <a href="{{ route('admin.public-localisations.site.edit', 'identity') }}" class="ui-btn ui-btn-secondary">
                        <i class="bi bi-translate" aria-hidden="true"></i> Nepali translation
                    </a>
                @endcan
                <button type="submit" form="publicIdentityForm" class="ui-btn ui-btn-primary">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Save public details
                </button>
            </x-slot>
        </x-ui.page-header>

        <form id="publicIdentityForm" action="{{ route('admin.public-identity.update') }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Verified contact details</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Only these values are available to the public Contact page.</p>
                    </div>
                    <i class="bi bi-telephone text-slate-400" aria-hidden="true"></i>
                </div>

                <div class="grid gap-4 p-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="address" class="ui-label">Public address</label>
                        <textarea id="address" name="address" rows="2" class="ui-input @error('address') ui-input-error @enderror">{{ old('address', $identity['address']) }}</textarea>
                        @error('address') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="ui-label">Public telephone</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $identity['phone']) }}" class="ui-input @error('phone') ui-input-error @enderror">
                        @error('phone') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="ui-label">Public email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $identity['email']) }}" class="ui-input @error('email') ui-input-error @enderror">
                        @error('email') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="public_website_placeholder" class="ui-label">Future website address</label>
                        <input id="public_website_placeholder" name="public_website_placeholder" type="text" value="{{ old('public_website_placeholder', $identity['website_placeholder']) }}" class="ui-input @error('public_website_placeholder') ui-input-error @enderror" placeholder="www.example.edu.np">
                        <p class="mt-1 text-xs text-slate-500">Shown as plain text with a “coming soon” label. Clear this after the address is registered and the website link is ready.</p>
                        @error('public_website_placeholder') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Visit information</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Leave a field empty when it has not yet been approved for families.</p>
                    </div>
                    <i class="bi bi-geo-alt text-slate-400" aria-hidden="true"></i>
                </div>

                <div class="grid gap-4 p-5 md:grid-cols-2">
                    <div>
                        <label for="public_location" class="ui-label">Public location</label>
                        <input id="public_location" name="public_location" type="text" value="{{ old('public_location', $identity['location']) }}" class="ui-input @error('public_location') ui-input-error @enderror">
                        @error('public_location') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="public_office_hours" class="ui-label">Office hours</label>
                        <input id="public_office_hours" name="public_office_hours" type="text" value="{{ old('public_office_hours', $identity['office_hours']) }}" class="ui-input @error('public_office_hours') ui-input-error @enderror">
                        @error('public_office_hours') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="public_directions_url" class="ui-label">Directions link</label>
                        <input id="public_directions_url" name="public_directions_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('public_directions_url', $identity['directions_url']) }}" class="ui-input @error('public_directions_url') ui-input-error @enderror">
                        <p class="mt-1 text-xs text-slate-500">Only a valid http or https link will be shown.</p>
                        @error('public_directions_url') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="public_visit_instructions" class="ui-label">Visit instructions</label>
                        <textarea id="public_visit_instructions" name="public_visit_instructions" rows="4" class="ui-input @error('public_visit_instructions') ui-input-error @enderror">{{ old('public_visit_instructions', $identity['visit_instructions']) }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">Plain text only. Do not include personal contact details that are not approved for public use.</p>
                        @error('public_visit_instructions') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="ui-btn ui-btn-primary">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Save public details
                </button>
            </div>
        </form>
    </div>
@endsection
