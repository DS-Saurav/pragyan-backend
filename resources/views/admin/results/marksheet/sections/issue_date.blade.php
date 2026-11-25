@if ($layout->showIssueDate)
    <div class="issue-date">
        Date of Issue: <span class="val">{{ ($exam->published_at ?? null)?->format('Y-m-d') ?? now()->format('Y-m-d') }}</span>
    </div>
@endif
