@extends('layouts.admin')

@section('title', 'Website Content')

@section('content')
    @php
        $canManage = Gate::allows('settings.manage');
    @endphp

    <div class="ui-page">
        <x-ui.page-header title="Website Content"
            subtitle="Homepage hero photographs, copy, learning highlights and retained legacy settings for the public website.">
            <x-slot name="actions">
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ui-btn ui-btn-secondary">
                    <i class="bi bi-box-arrow-up-right"></i> Open homepage
                </a>
                @if ($canManage)
                    <a href="{{ route('admin.public-localisations.site.edit', 'homepage') }}" class="ui-btn ui-btn-secondary">
                        <i class="bi bi-translate" aria-hidden="true"></i> Nepali translation
                    </a>
                @endif
                @if ($canManage)
                    <button type="submit" form="websiteForm" class="ui-btn ui-btn-primary">
                        <i class="bi bi-check2-circle"></i> Save details
                    </button>
                @endif
            </x-slot>
        </x-ui.page-header>

        @unless ($canManage)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-eye mt-0.5"></i>
                <span>You have view-only access to website content.</span>
            </div>
        @endunless

        <div class="grid gap-5 xl:grid-cols-3">
            {{-- ---------------------------------------------------------------- --}}
            {{-- Hero photographs (upload / alt text / reorder / remove / preview) --}}
            {{-- ---------------------------------------------------------------- --}}
            <section class="ui-card xl:col-span-2">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Hero photographs</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Shown in the homepage hero. Landscape photographs work best (at least 480×280 px).
                            Uploads are automatically resized and optimised.
                        </p>
                    </div>
                    <i class="bi bi-images text-slate-400"></i>
                </div>

                <div class="space-y-5 p-5">
                    @if ($canManage)
                        <form id="heroUploadForm" action="{{ route('admin.settings.website.images.upload') }}" method="POST"
                            enctype="multipart/form-data" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-4">
                            @csrf
                            <label for="hero_files" class="ui-label">Add photographs <span class="text-xs font-normal text-slate-500">(1–4 at a time, up to 8 MB each, JPEG / PNG / WebP / GIF)</span></label>
                            <input id="hero_files" type="file" name="hero_files[]" multiple accept="image/*"
                                class="ui-input @error('hero_files.*', 'upload') ui-input-error @enderror" aria-describedby="heroUploadErrors">
                            {{-- Upload errors live ONLY in the "upload" bag so they
                                 render inside this form's own area — never page-globally,
                                 and never as a Save details failure. --}}
                            <div id="heroUploadErrors">
                                @error('hero_files', 'upload')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                {{-- MessageBag::get('hero_files.*') returns message
                                     ARRAYS keyed per file (hero_files.0, …), so
                                     flatten before echoing — otherwise e(Array)
                                     throws and the whole page 500s on a per-file
                                     validation error. --}}
                                @forelse ($errors->upload->get('hero_files.*') as $uploadError)
                                    @foreach ((array) $uploadError as $uploadMessage)
                                        <p class="ui-error">{{ $uploadMessage }}</p>
                                    @endforeach
                                @empty
                                    @error('processing', 'upload')
                                        <p class="ui-error">{{ $message }}</p>
                                    @enderror
                                @endforelse
                            </div>
                            <button type="submit" class="ui-btn ui-btn-primary mt-3">
                                <i class="bi bi-cloud-arrow-up"></i> Upload
                            </button>
                        </form>
                    @endif

                    @if (count($heroes) === 0)
                        <x-ui.empty-state icon="bi-images" title="No hero photographs yet"
                            message="Without photographs the homepage shows a restrained green gradient hero. Upload school photographs here — real campus imagery only." />
                    @else
                        <ul class="space-y-3" role="list">
                            @foreach ($heroes as $i => $hero)
                                <li class="flex gap-4 rounded-xl border border-slate-200 bg-white p-3">
                                        @php($heroUrl = \App\Support\Photo::url($hero['path']))
                                        <a href="{{ $heroUrl }}" target="_blank" rel="noopener" class="block shrink-0">
                                            <img src="{{ $heroUrl }}" alt="Hero photograph {{ $i + 1 }}"
                                                class="h-20 w-36 rounded-lg border border-slate-200 object-cover">
                                        </a>
                                    <div class="min-w-0 flex-1">
                                        @if ($canManage)
                                            <label for="alt-{{ $i }}" class="ui-label text-xs">Alt text</label>
                                        @endif
                                        @if ($canManage)
                                            <input id="alt-{{ $i }}" type="text" form="websiteForm"
                                                name="alts[{{ $hero['path'] }}]" value="{{ old('alts')[$hero['path']] ?? ($hero['alt'] ?? '') }}"
                                                placeholder="Describe the photograph (e.g. Morning assembly on the main lawn)"
                                                class="ui-input mt-1">
                                        @else
                                            <p class="text-sm text-slate-700">{{ $hero['alt'] ?? '' }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        @if ($canManage)
                                            <form action="{{ route('admin.settings.website.images.action') }}" method="POST" class="flex flex-col gap-1">
                                                @csrf
                                                <input type="hidden" name="index" value="{{ $i }}">
                                                <button type="submit" name="action" value="move-up" @if ($i === 0) disabled @endif
                                                    class="ui-btn ui-btn-secondary h-8 !px-2 text-xs" title="Move up" aria-label="Move photograph {{ $i + 1 }} up">
                                                    <i class="bi bi-arrow-up"></i>
                                                </button>
                                                <button type="submit" name="action" value="move-down" @if ($i === count($heroes) - 1) disabled @endif
                                                    class="ui-btn ui-btn-secondary h-8 !px-2 text-xs" title="Move down" aria-label="Move photograph {{ $i + 1 }} down">
                                                    <i class="bi bi-arrow-down"></i>
                                                </button>
                                                <button type="submit" name="action" value="remove"
                                                    class="ui-btn h-8 !px-2 text-xs ui-btn-secondary !text-rose-600" title="Remove"
                                                    aria-label="Remove photograph {{ $i + 1 }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <p class="text-xs text-slate-500">The first photograph is shown first. Files are stored on the public disk under <code>storage/hero/</code>.</p>
                    @endif
                </div>
            </section>

            {{-- ------------------------------------------------ --}}
            {{-- Headline/intro plus retained legacy hero settings (details form) --}}
            {{-- ------------------------------------------------ --}}
            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Hero text and legacy settings</h2>
                        <p class="mt-0.5 text-xs text-slate-500">The public homepage shows the first ordered hero image with fixed Admissions and Plan a Visit actions. Rotation and custom CTA values are retained here for compatibility.</p>
                    </div>
                    <i class="bi bi-type-h1 text-slate-400"></i>
                </div>

                <form id="websiteForm" action="{{ route('admin.settings.website.update') }}" method="POST" class="space-y-4 p-5">
                    @csrf
                    @method('PUT')

                    {{-- Save-details validation errors (default error bag) surface
                         inside the Save details form card; upload failures use the
                         separate "upload" bag and never appear here. --}}
                    <div aria-live="polite">
                        @error('website_hero_headline')
                            <p class="ui-error">{{ $message }}</p>
                        @enderror
                        @foreach (['website_cta_primary_url', 'website_cta_secondary_url'] as $saveErrorKey)
                            @error($saveErrorKey)
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        @endforeach
                    </div>

                    <fieldset class="m-0 space-y-4 border-0 p-0" @if (! $canManage) disabled @endif>
                        <div>
                            <label for="website_hero_headline" class="ui-label">Headline</label>
                            <input id="website_hero_headline" type="text" name="website_hero_headline"
                                value="{{ old('website_hero_headline', $settings['website_hero_headline'] ?? '') }}"
                                placeholder="Defaults to the school name" maxlength="160" class="ui-input">
                        </div>

                        <div>
                            <label for="website_hero_intro" class="ui-label">Introduction</label>
                            <textarea id="website_hero_intro" name="website_hero_intro" rows="3" maxlength="400"
                                placeholder="One or two sentences shown under the headline"
                                class="ui-input">{{ old('website_hero_intro', $settings['website_hero_intro'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label for="website_hero_rotation" class="ui-label">Automatic rotation (retained setting)</label>
                            <select id="website_hero_rotation" name="website_hero_rotation" class="ui-input">
                                @foreach ($rotations as $seconds)
                                    <option value="{{ $seconds }}"
                                        {{ old('website_hero_rotation', $settings['website_hero_rotation'] ?? '8') == (string) $seconds ? 'selected' : '' }}>
                                        {{ $seconds === 0 ? 'No rotation (static hero)' : 'Every '.$seconds.' seconds' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Visitors who prefer reduced motion always see a static hero.</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="website_cta_primary_text" class="ui-label">Primary button text</label>
                                <input id="website_cta_primary_text" type="text" name="website_cta_primary_text" maxlength="60"
                                    value="{{ old('website_cta_primary_text', $settings['website_cta_primary_text'] ?? '') }}"
                                    placeholder="Admission inquiry" class="ui-input">
                            </div>
                            <div>
                                <label for="website_cta_primary_url" class="ui-label">Primary destination</label>
                                <input id="website_cta_primary_url" type="text" name="website_cta_primary_url" maxlength="255"
                                    value="{{ old('website_cta_primary_url', $settings['website_cta_primary_url'] ?? '') }}"
                                    placeholder="/admissions or https://…" class="ui-input">
                            </div>
                            <div>
                                <label for="website_cta_secondary_text" class="ui-label">Secondary button text</label>
                                <input id="website_cta_secondary_text" type="text" name="website_cta_secondary_text" maxlength="60"
                                    value="{{ old('website_cta_secondary_text', $settings['website_cta_secondary_text'] ?? '') }}"
                                    placeholder="See the calendar" class="ui-input">
                            </div>
                            <div>
                                <label for="website_cta_secondary_url" class="ui-label">Secondary destination</label>
                                <input id="website_cta_secondary_url" type="text" name="website_cta_secondary_url" maxlength="255"
                                    value="{{ old('website_cta_secondary_url', $settings['website_cta_secondary_url'] ?? '') }}"
                                    placeholder="/calendar or https://…" class="ui-input">
                            </div>
                        </div>

                        <div>
                            <label for="website_home_intro" class="ui-label">Homepage introduction</label>
                            <textarea id="website_home_intro" name="website_home_intro" rows="5" maxlength="900"
                                placeholder="The short school introduction shown under the hero"
                                class="ui-input">{{ old('website_home_intro', $settings['website_home_intro'] ?? '') }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Leave empty to hide the introduction section.</p>
                        </div>

                        <div>
                            <label for="website_home_highlights" class="ui-label">Learning highlights</label>
                            <textarea id="website_home_highlights" name="website_home_highlights" rows="5" maxlength="1200"
                                placeholder="One short line per row (up to 6): e.g. Daily reading circles from Grade 1"
                                class="ui-input">{{ old('website_home_highlights', implode("\n", $highlights)) }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">One line each, up to 6. Leave empty to hide the section.</p>
                        </div>
                    </fieldset>
                </form>
            </section>

            {{-- ------------------------------ --}}
            {{-- Retained homepage statistics (≤4 rows) --}}
            {{-- ------------------------------ --}}
            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Legacy homepage statistics</h2>
                        <p class="mt-0.5 text-xs text-slate-500">These saved values are retained for compatibility. The public homepage no longer displays a statistics strip.</p>
                    </div>
                    <i class="bi bi-bar-chart text-slate-400"></i>
                </div>

                <div class="p-5">
                    @if ($canManage)
                        <p class="text-xs text-slate-500">Up to four value + label pairs. Leave both fields of a row empty to skip it.</p>
                    @endif

                    @if (! $canManage && count($stats) === 0)
                        <x-ui.empty-state icon="bi-bar-chart" title="No statistics entered"
                            message="No legacy statistics are saved. The public homepage does not display a statistics strip." />
                    @endif

                    <div class="mt-3 space-y-3" @if (! $canManage) hidden @endif>
                        @for ($i = 0; $i < 4; $i++)
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="stat-value-{{ $i }}" class="ui-label text-xs">Value {{ $i + 1 }}</label>
                                    <input id="stat-value-{{ $i }}" type="text" form="websiteForm"
                                        name="stat[{{ $i }}][value]" maxlength="20"
                                        value="{{ old('stat.'.$i.'.value', $stats[$i]['value'] ?? '') }}"
                                        placeholder="e.g. 24" class="ui-input">
                                </div>
                                <div>
                                    <label for="stat-label-{{ $i }}" class="ui-label text-xs">Label {{ $i + 1 }}</label>
                                    <input id="stat-label-{{ $i }}" type="text" form="websiteForm"
                                        name="stat[{{ $i }}][label]" maxlength="60"
                                        value="{{ old('stat.'.$i.'.label', $stats[$i]['label'] ?? '') }}"
                                        placeholder="e.g. Classes" class="ui-input">
                                </div>
                            </div>
                        @endfor
                        <p class="text-xs text-slate-500">When all rows are empty, the homepage automatically shows real counts from the database (classes, subjects, staff) if available.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
