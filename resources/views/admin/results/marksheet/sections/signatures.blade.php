@if ($layout->showSignatures && count($layout->signatures))
    <div class="signatures">
        @foreach ($layout->signatures as $label)
            <div>
                <div class="sig-line">{{ $label }}</div>
            </div>
        @endforeach
    </div>
@endif
