@props(['icon' => 'bi-inbox', 'title', 'message' => null])

<div class="ui-empty">
    <div class="ui-empty-icon"><i class="bi {{ $icon }}"></i></div>
    <p class="ui-empty-title">{{ $title }}</p>
    @if ($message)
        <p class="ui-empty-text">{{ $message }}</p>
    @endif
    @isset($actions)
        <div class="mt-4">{{ $actions }}</div>
    @endisset
</div>
