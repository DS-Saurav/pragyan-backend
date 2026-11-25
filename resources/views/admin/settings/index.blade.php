@extends('layouts.admin')

@section('title', 'School Settings')

@section('content')
    @php
        $canManage = Gate::allows('settings.manage');
        $primary = $settings['primary_color'] ?? '#0f2248';
        $accent = $settings['accent_color'] ?? '#16a34a';
    @endphp

    <div class="ui-page">
        <x-ui.page-header title="School Settings"
            subtitle="Update your school profile, academic defaults and branding colors.">
            <x-slot name="actions">
                <a href="{{ route('admin.settings.marksheet') }}" class="ui-btn ui-btn-secondary">
                    <i class="bi bi-postcard"></i> Marksheet layout
                </a>
                @if ($canManage)
                    <button type="submit" form="settingsForm" class="ui-btn ui-btn-primary">
                        <i class="bi bi-check2-circle"></i> Save settings
                    </button>
                @endif
            </x-slot>
        </x-ui.page-header>

        @unless ($canManage)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-eye mt-0.5"></i>
                <span>You have view-only access to school settings.</span>
            </div>
        @endunless

        <form id="settingsForm" action="{{ route('admin.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            <fieldset class="m-0 space-y-5 border-0 p-0" @if (! $canManage) disabled @endif>
                <div class="grid gap-5 lg:grid-cols-3">
                    <section class="ui-card lg:col-span-2">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">School information</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Shown across the application, notices and reports.</p>
                            </div>
                            <i class="bi bi-building text-slate-400"></i>
                        </div>

                        <div class="space-y-4 p-5">
                            <div>
                                <label for="school_name" class="ui-label">School name <span class="text-rose-600">*</span></label>
                                <input id="school_name" type="text" name="school_name"
                                    value="{{ old('school_name', $settings['school_name'] ?? 'Pragyan Pathashala') }}" required
                                    class="ui-input @error('school_name') ui-input-error @enderror">
                                @error('school_name')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tagline" class="ui-label">Tagline</label>
                                <input id="tagline" type="text" name="tagline"
                                    value="{{ old('tagline', $settings['tagline'] ?? 'Learn. Grow. Succeed.') }}"
                                    class="ui-input @error('tagline') ui-input-error @enderror">
                                @error('tagline')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="address" class="ui-label">Address</label>
                                <textarea id="address" name="address" rows="2"
                                    class="ui-input @error('address') ui-input-error @enderror">{{ old('address', $settings['address'] ?? 'Birendranagar, Surkhet, Nepal') }}</textarea>
                                @error('address')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="phone" class="ui-label">Phone</label>
                                    <input id="phone" type="text" name="phone"
                                        value="{{ old('phone', $settings['phone'] ?? '+977 83-520100') }}"
                                        class="ui-input @error('phone') ui-input-error @enderror">
                                    @error('phone')
                                        <p class="ui-error">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="email" class="ui-label">Email</label>
                                    <input id="email" type="email" name="email"
                                        value="{{ old('email', $settings['email'] ?? 'info@pragyanpathashala.edu.np') }}"
                                        class="ui-input @error('email') ui-input-error @enderror">
                                    @error('email')
                                        <p class="ui-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="website" class="ui-label">Website</label>
                                    <input id="website" type="text" name="website"
                                        value="{{ old('website', $settings['website'] ?? 'https://www.pragyanpathashala.edu.np') }}"
                                        class="ui-input @error('website') ui-input-error @enderror">
                                    @error('website')
                                        <p class="ui-error">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="logo_url" class="ui-label">Logo URL</label>
                                    <input id="logo_url" type="text" name="logo_url"
                                        value="{{ old('logo_url', $settings['logo_url'] ?? '') }}" placeholder="https://..."
                                        class="ui-input @error('logo_url') ui-input-error @enderror">
                                    @error('logo_url')
                                        <p class="ui-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="time_zone" class="ui-label">Time zone</label>
                                    <select id="time_zone" name="time_zone" class="ui-input">
                                        <option value="Asia/Kathmandu"
                                            {{ old('time_zone', $settings['time_zone'] ?? '') == 'Asia/Kathmandu' ? 'selected' : '' }}>
                                            Asia/Kathmandu
                                        </option>
                                        <option value="Asia/Kolkata"
                                            {{ old('time_zone', $settings['time_zone'] ?? '') == 'Asia/Kolkata' ? 'selected' : '' }}>
                                            Asia/Kolkata
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label for="currency_symbol" class="ui-label">Currency symbol</label>
                                    <input id="currency_symbol" type="text" name="currency_symbol"
                                        value="{{ old('currency_symbol', $settings['currency_symbol'] ?? 'रू') }}"
                                        class="ui-input">
                                </div>
                                <div>
                                    <label for="academic_year" class="ui-label">Academic year</label>
                                    <input id="academic_year" type="text" name="academic_year"
                                        value="{{ old('academic_year', $settings['academic_year'] ?? '2082-2083') }}"
                                        class="ui-input">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Theme &amp; colors</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Match the application to your school branding.</p>
                            </div>
                            <i class="bi bi-palette text-slate-400"></i>
                        </div>

                        <div class="space-y-5 p-5">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="primary_input" class="ui-label">Primary</label>
                                    <div class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white p-1.5">
                                        <input type="color" id="primary_picker" value="{{ $primary }}"
                                            class="h-7 w-8 cursor-pointer rounded border-0 bg-transparent p-0"
                                            onchange="document.getElementById('primary_input').value = this.value">
                                        <input id="primary_input" type="text" name="primary_color" value="{{ $primary }}"
                                            class="w-full border-0 bg-transparent p-0 font-mono text-xs text-slate-700 focus:ring-0">
                                    </div>
                                </div>
                                <div>
                                    <label for="accent_input" class="ui-label">Accent</label>
                                    <div class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white p-1.5">
                                        <input type="color" id="accent_picker" value="{{ $accent }}"
                                            class="h-7 w-8 cursor-pointer rounded border-0 bg-transparent p-0"
                                            onchange="document.getElementById('accent_input').value = this.value">
                                        <input id="accent_input" type="text" name="accent_color" value="{{ $accent }}"
                                            class="w-full border-0 bg-transparent p-0 font-mono text-xs text-slate-700 focus:ring-0">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="ui-label">Presets</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" onclick="setTheme('#0f2248', '#16a34a')"
                                        class="ui-btn ui-btn-secondary ui-btn-sm">Navy (default)</button>
                                    <button type="button" onclick="setTheme('#1e3a8a', '#2563eb')"
                                        class="ui-btn ui-btn-secondary ui-btn-sm">Royal blue</button>
                                    <button type="button" onclick="setTheme('#064e3b', '#059669')"
                                        class="ui-btn ui-btn-secondary ui-btn-sm">Forest</button>
                                    <button type="button" onclick="setTheme('#7f1d1d', '#dc2626')"
                                        class="ui-btn ui-btn-secondary ui-btn-sm">Maroon</button>
                                    <button type="button" onclick="setTheme('#581c87', '#9333ea')"
                                        class="ui-btn ui-btn-secondary ui-btn-sm">Plum</button>
                                    <button type="button" onclick="setTheme('#1f2937', '#4b5563')"
                                        class="ui-btn ui-btn-secondary ui-btn-sm">Charcoal</button>
                                </div>
                            </div>

                            <div id="preview_box" class="rounded-xl p-4 text-white shadow-sm" style="background-color: {{ $primary }}">
                                <p class="text-[11px] font-semibold text-white/80">Preview</p>
                                <p class="mt-2 text-sm font-bold">Sidebar and headers</p>
                                <button type="button" id="preview_btn"
                                    class="mt-3 rounded-lg px-3 py-1.5 text-xs font-bold text-white shadow"
                                    style="background-color: {{ $accent }}">
                                    Accent button
                                </button>
                            </div>

                            @if ($canManage)
                                <button type="submit" class="ui-btn ui-btn-primary w-full">
                                    <i class="bi bi-check2-circle"></i> Save settings
                                </button>
                            @endif
                        </div>
                    </section>
                </div>
            </fieldset>
        </form>
    </div>

    @push('scripts')
        <script>
            function setTheme(primary, accent) {
                document.getElementById('primary_input').value = primary;
                document.getElementById('primary_picker').value = primary;
                document.getElementById('accent_input').value = accent;
                document.getElementById('accent_picker').value = accent;

                document.getElementById('preview_box').style.backgroundColor = primary;
                document.getElementById('preview_btn').style.backgroundColor = accent;
            }
        </script>
    @endpush
@endsection
