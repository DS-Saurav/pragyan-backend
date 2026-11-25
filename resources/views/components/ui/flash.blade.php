@if (session('success'))
    <div class="ui-alert ui-alert-success" role="status">
        <i class="bi bi-check-circle-fill mt-0.5"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if (session('error'))
    <div class="ui-alert ui-alert-error" role="alert">
        <i class="bi bi-x-circle-fill mt-0.5"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if (session('warning'))
    <div class="ui-alert ui-alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
        <span>{{ session('warning') }}</span>
    </div>
@endif

@if (session('status'))
    <div class="ui-alert ui-alert-info" role="status">
        <i class="bi bi-info-circle-fill mt-0.5"></i>
        <span>{{ session('status') }}</span>
    </div>
@endif

@if ($errors->any())
    <div class="ui-alert ui-alert-error" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
        <div>
            <p class="font-semibold">Please fix the following:</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
