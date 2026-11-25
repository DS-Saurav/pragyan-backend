@extends('layouts.admin')

@section('title', 'Public website photographs')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Public website photographs"
            subtitle="Choose approved Media Library images for fixed public placements. Each use needs its own description and private rights note." />

        <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950">
            Upload, review and publish photographs in the <a class="font-semibold underline" href="{{ route('admin.media.index') }}">Media Library</a> first. Only active published derivatives can be selected here. The rights note is visible to staff only.
        </div>

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ($labels as $slotKey => $slotLabel)
                @php $slot = $slots->get($slotKey); @endphp
                <section class="ui-card p-5" aria-labelledby="slot-{{ $slotKey }}">
                    <h2 id="slot-{{ $slotKey }}" class="text-lg font-bold text-slate-900">{{ $slotLabel }}</h2>
                    @if ($previews->get($slotKey))
                        <img src="{{ $previews->get($slotKey)['url'] }}" alt="" class="mt-4 h-44 w-full rounded-lg object-cover">
                    @endif
                    <form action="{{ route('admin.public-photos.update', $slotKey) }}" method="POST" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="asset-{{ $slotKey }}" class="ui-label">Published photograph</label>
                            <select id="asset-{{ $slotKey }}" name="media_asset_id" class="ui-input">
                                <option value="">No photograph (text fallback)</option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->id }}" @selected($slot?->media_asset_id === $asset->id)>
                                        {{ $asset->title ?: $asset->original_filename }} · #{{ $asset->id }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="alt-{{ $slotKey }}" class="ui-label">English image description</label>
                            <input id="alt-{{ $slotKey }}" name="alt_text" type="text" maxlength="255" value="{{ $slot?->alt_text }}" class="ui-input" placeholder="Describe this photograph in its page context">
                        </div>
                        <div>
                            <label for="alt-ne-{{ $slotKey }}" class="ui-label">Nepali image description</label>
                            <input id="alt-ne-{{ $slotKey }}" name="alt_text_ne" type="text" maxlength="255" value="{{ $slot?->alt_text_ne }}" class="ui-input" lang="ne">
                            <p class="mt-1 text-xs text-slate-500">The photograph stays hidden on Nepali pages until this is filled.</p>
                        </div>
                        <div>
                            <label for="caption-{{ $slotKey }}" class="ui-label">Public caption (optional)</label>
                            <input id="caption-{{ $slotKey }}" name="caption" type="text" maxlength="500" value="{{ $slot?->caption }}" class="ui-input">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="focal-x-{{ $slotKey }}" class="ui-label">Horizontal focus %</label>
                                <input id="focal-x-{{ $slotKey }}" name="focal_x" type="number" min="0" max="100" step="0.01" value="{{ $slot?->focal_x ?? 50 }}" class="ui-input">
                            </div>
                            <div>
                                <label for="focal-y-{{ $slotKey }}" class="ui-label">Vertical focus %</label>
                                <input id="focal-y-{{ $slotKey }}" name="focal_y" type="number" min="0" max="100" step="0.01" value="{{ $slot?->focal_y ?? 50 }}" class="ui-input">
                            </div>
                        </div>
                        <div>
                            <label for="rights-{{ $slotKey }}" class="ui-label">Private source and rights evidence</label>
                            <textarea id="rights-{{ $slotKey }}" name="source_rights_note" rows="3" maxlength="2000" class="ui-input" placeholder="Who supplied it, permission scope, date, and where evidence is kept">{{ $slot?->source_rights_note }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Required when selecting a photo. Never shown publicly.</p>
                        </div>
                        @if ($slot?->approved_at)
                            <p class="text-xs text-slate-500">Last approved {{ $slot->approved_at->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }} Nepal time.</p>
                        @endif
                        <button type="submit" class="ui-btn ui-btn-primary">Save {{ $slotLabel }}</button>
                    </form>
                </section>
            @endforeach
        </div>
    </div>
@endsection
