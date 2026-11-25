@if (count($layout->notes))
    <div class="bottom-section">
        <div class="notes">
            <h4>{{ $layout->label('notes_heading', 'Note:') }}</h4>
            <ul>
                @foreach ($layout->notes as $note)
                    <li>{{ $note }}</li>
                @endforeach
                @if ($row['failed'])
                    <li class="fail">Not passed in: {{ implode(', ', $row['failed']) }}</li>
                @endif
            </ul>
        </div>
    </div>
@endif
