@extends('layouts.admin')

@section('title', 'Marksheet Layout')

@section('content')
    @php
        $colFlag = fn (string $key) => $layout->flag('columns', $key);
        $gpaFlag = fn (string $key) => $layout->flag('gpa', $key);
        $idFlag = fn (string $key) => $layout->flag('identity', $key);
        $orderNames = [];
        foreach ($layout->order as $section) {
            $orderNames[] = $sections[$section] ?? $section;
        }
        $creditLines = [];
        foreach ($layout->creditHours as $code => $hours) {
            $creditLines[] = $code.'='.$hours;
        }
    @endphp

    <div class="ui-page">
        <x-ui.page-header title="Marksheet layout"
            subtitle="Configure the printed grade sheet: sections, columns, labels, branding and signatures. No code changes needed.">
            <x-slot name="actions">
                @if ($canManage)
                    <button type="submit" form="marksheetForm" class="ui-btn ui-btn-primary">
                        <i class="bi bi-check2-circle"></i> Save layout
                    </button>
                @endif
                @if (Gate::allows('results.view') && Route::has('admin.results.index'))
                    <a href="{{ route('admin.results.index') }}" class="ui-btn ui-btn-secondary">
                        <i class="bi bi-eye"></i> Preview marksheet
                    </a>
                @endif
            </x-slot>
        </x-ui.page-header>

        @unless ($canManage)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-eye mt-0.5"></i>
                <span>You have view-only access to the marksheet layout.</span>
            </div>
        @endunless

        <form id="marksheetForm" action="{{ route('admin.settings.marksheet.update') }}" method="POST">
            @csrf
            @method('PUT')

            <fieldset class="m-0 space-y-5 border-0 p-0" @if (! $canManage) disabled @endif>
                <div class="grid gap-5 lg:grid-cols-2">
                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Section order</h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    One section per line, top to bottom of the printed sheet. Student information,
                                    subject table, GPA summary and header cannot be removed (result integrity).
                                </p>
                            </div>
                            <i class="bi bi-list-ol text-slate-400"></i>
                        </div>
                        <div class="space-y-4 p-5">
                            <textarea name="sections_order" rows="10"
                                class="ui-input font-mono text-xs">{{ implode("\n", $orderNames) }}</textarea>
                            <p class="text-xs text-slate-500">Valid sections:
                                {{ implode(' · ', array_values($sections)) }}</p>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Header, identity &amp; watermark</h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Logo and phone/email come from the main Settings page.
                                </p>
                            </div>
                            <i class="bi bi-postcard text-slate-400"></i>
                        </div>
                        <div class="space-y-4 p-5">
                            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[header_show_logo]" value="1" class="rounded"
                                        {{ $layout->flag('header', 'show_logo') ? 'checked' : '' }}>
                                    School logo
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[header_show_photo]" value="1" class="rounded"
                                        {{ $layout->flag('header', 'show_photo') ? 'checked' : '' }}>
                                    Student photo
                                </label>
                            </div>

                            <div>
                                <p class="ui-label mb-2">Identity line fields</p>
                                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="toggles[id_dob]" value="1" class="rounded"
                                            {{ $idFlag('dob') ? 'checked' : '' }}>
                                        Date of birth (A.D.)
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="toggles[id_dob_bs]" value="1" class="rounded"
                                            {{ $idFlag('dob_bs') ? 'checked' : '' }}>
                                        Date of birth (B.S.)
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="toggles[id_admission_no]" value="1" class="rounded"
                                            {{ $idFlag('admission_no') ? 'checked' : '' }}>
                                        Admission no.
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="toggles[id_exam_name]" value="1" class="rounded"
                                            {{ $idFlag('exam_name') ? 'checked' : '' }}>
                                        Examination name
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="toggles[id_academic_year]" value="1" class="rounded"
                                            {{ $idFlag('academic_year') ? 'checked' : '' }}>
                                        Academic year
                                    </label>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="text-sm">
                                    <input type="checkbox" name="toggles[show_watermark]" value="1" class="rounded mb-1"
                                        {{ $layout->showWatermark ? 'checked' : '' }}>
                                    Watermark
                                </label>
                                <div>
                                    <label for="watermark_text" class="ui-label">Watermark text</label>
                                    <input id="watermark_text" type="text" name="watermark_text" maxlength="30"
                                        value="{{ $layout->watermarkText }}" class="ui-input">
                                </div>
                            </div>

                            <div>
                                <label for="label_exam_title" class="ui-label">Grade-sheet heading</label>
                                <input id="label_exam_title" type="text" name="label_exam_title" maxlength="60"
                                    value="{{ $layout->label('exam_title', 'GRADE SHEET') }}" class="ui-input">
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Subject table columns</h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Credit Hour and Final Grade print as "-" when the system has no source for them;
                                    set credit hours below to fill CH. The Grade column cannot be hidden.
                                </p>
                            </div>
                            <i class="bi bi-table text-slate-400"></i>
                        </div>
                        <div class="space-y-4 p-5">
                            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[col_sn]" value="1" class="rounded"
                                        {{ $colFlag('sn') ? 'checked' : '' }}>
                                    S.N.
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[col_subject_code]" value="1" class="rounded"
                                        {{ $colFlag('subject_code') ? 'checked' : '' }}>
                                    Subject code
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[col_credit_hour]" value="1" class="rounded"
                                        {{ $colFlag('credit_hour') ? 'checked' : '' }}>
                                    Credit hour
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[col_grade_point]" value="1" class="rounded"
                                        {{ $colFlag('grade_point') ? 'checked' : '' }}>
                                    Grade point
                                </label>
                                <label class="flex items-center gap-2 text-slate-400">
                                    <input type="checkbox" checked disabled class="rounded">
                                    Grade (always shown)
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[col_final_grade]" value="1" class="rounded"
                                        {{ $colFlag('final_grade') ? 'checked' : '' }}>
                                    Final grade
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[col_remarks]" value="1" class="rounded"
                                        {{ $colFlag('remarks') ? 'checked' : '' }}>
                                    Remarks
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="toggles[show_component_marks]" value="1" class="rounded"
                                        {{ $layout->showComponentMarks ? 'checked' : '' }}>
                                    Show separate TH/IN component rows on the marksheet
                                </label>
                            </div>

                            <div>
                                <label for="credit_hours" class="ui-label">Credit hours (one per line: CODE=hours)</label>
                                <textarea id="credit_hours" name="credit_hours" rows="4"
                                    class="ui-input font-mono text-xs"
                                    placeholder="e.g. NEP002=3.75">{{ implode("\n", $creditLines) }}</textarea>
                                <p class="text-xs text-slate-500">KEY per line = subject code shown on the marksheet.</p>
                            </div>

                            <div>
                                <p class="ui-label mb-2">GPA &amp; result summary cells</p>
                                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                    @foreach (['gpa' => 'GPA', 'grade' => 'Grade', 'division' => 'Division', 'total_marks' => 'Total marks', 'percentage' => 'Percentage', 'rank' => 'Rank', 'result_label' => 'Result'] as $key => $label)
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="toggles[gpa_{{ $key }}]" value="1" class="rounded"
                                                {{ $gpaFlag($key) ? 'checked' : '' }}>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-xs text-slate-500 mt-1">
                                    Defaults for the summary row. Per-result overrides live in
                                    "Result display by condition" below - show or hide any cell for Pass, Fail or
                                    Incomplete results. Incomplete results show only the INCOMPLETE label by
                                    default (Grade, GPA, division, total, percentage and rank are held back), so a
                                    partial result is never presented as a finalized outcome.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Result display by condition</h2>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Overrides the summary cells above for each final result: Pass, Fail or Incomplete.
                                    "Inherit" uses the GPA &amp; result summary cells; Show/Hide forces the cell for
                                    that result only. Incomplete results show only the INCOMPLETE label by
                                    default (Grade, GPA, division, total, percentage and rank are held back
                                    unless explicitly set to Show), so marks entered so far are never presented as
                                    the final outcome. Subject
                                    grades, the grading legend and the computed result itself are unaffected.
                                </p>
                            </div>
                            <i class="bi bi-sliders2 text-slate-400"></i>
                        </div>
                        <div class="space-y-4 p-5">
                            @php
                                $conditionLabels = [
                                    'pass' => 'Pass result',
                                    'fail' => 'Fail result',
                                    'incomplete' => 'Incomplete result',
                                ];
                                $fieldLabels = [
                                    'gpa' => 'GPA',
                                    'grade' => 'Overall grade',
                                    'division' => 'Division',
                                    'total_marks' => 'Total marks',
                                    'percentage' => 'Percentage',
                                    'rank' => 'Rank',
                                    'result_label' => 'Result label',
                                ];
                            @endphp
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($conditionLabels as $condition => $conditionLabel)
                                    <div class="rounded-lg border border-slate-200 p-4">
                                        <p class="ui-label mb-3">{{ $conditionLabel }}</p>
                                        <div class="space-y-2 text-sm">
                                            @foreach ($fieldLabels as $fieldKey => $fieldLabel)
                                                <label class="flex items-center justify-between gap-2">
                                                    <span class="text-slate-600">{{ $fieldLabel }}</span>
                                                    <select name="condition_rules[{{ $condition }}][{{ $fieldKey }}]"
                                                        class="ui-input w-32 px-2 py-1 text-xs">
                                                        <option value=""
                                                            @selected(($layout->conditionRules[$condition][$fieldKey] ?? null) === null)>Inherit</option>
                                                        <option value="1"
                                                            @selected(($layout->conditionRules[$condition][$fieldKey] ?? null) === true)>Show</option>
                                                        <option value="0"
                                                            @selected(($layout->conditionRules[$condition][$fieldKey] ?? null) === false)>Hide</option>
                                                    </select>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Indicators, notes, legend &amp; signatures</h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    List entries, one per line. Reorder by rearranging the lines.
                                </p>
                            </div>
                            <i class="bi bi-ui-checks text-slate-400"></i>
                        </div>
                        <div class="space-y-4 p-5">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="toggles[show_assessment]" value="1" class="rounded"
                                    {{ $layout->showAssessment ? 'checked' : '' }}>
                                Assessment indicator rows
                            </label>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Assessment
                                        indicators</label>
                                    @forelse ($layout->assessmentRows as $indicator)
                                        {{-- The hidden key is this indicator's STABLE identity: it is
                                             re-submitted unchanged on every save so renaming a label
                                             can never orphan the ratings already recorded for the
                                             marksheet (Stage P3I). --}}
                                        <div class="flex items-center gap-2 mb-2">
                                            <input type="text" name="assessment_labels[]" value="{{ $indicator['label'] }}"
                                                maxlength="80" class="ui-input flex-1 text-xs" placeholder="Indicator label">
                                            <input type="hidden" name="assessment_keys[]" value="{{ $indicator['key'] }}">
                                            <span class="text-[10px] text-slate-400 font-mono w-40 truncate"
                                                title="Stable key (unchanged by a rename)">{{ $indicator['key'] }}</span>
                                        </div>
                                    @empty
                                        @unless ($canManage)
                                            <p class="text-xs text-slate-500">No assessment indicators are configured.</p>
                                        @endunless
                                    @endforelse
                                    @if ($canManage)
                                        <div class="flex items-center gap-2 mb-2">
                                            <input type="text" name="assessment_labels[]" maxlength="80"
                                                class="ui-input flex-1 text-xs" placeholder="New indicator label (optional)">
                                            <input type="hidden" name="assessment_keys[]" value="">
                                            <span class="text-[10px] text-slate-400 w-40">new row</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="text" name="assessment_labels[]" maxlength="80"
                                                class="ui-input flex-1 text-xs" placeholder="New indicator label (optional)">
                                            <input type="hidden" name="assessment_keys[]" value="">
                                            <span class="text-[10px] text-slate-400 w-40">new row</span>
                                        </div>
                                    @endif
                                </div>
                                @unless ($canManage)
                                    <p class="text-xs text-slate-500">Only administrators can edit indicator labels.</p>
                                @endunless
                                <p class="text-xs text-slate-500">
                                    Indicators may be added by filling a blank row. Ratings are recorded per student and
                                    examination in the marks entry area and print on the marksheet ("Not recorded" when
                                    none exists).
                                </p>
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="toggles[show_attendance]" value="1" class="rounded"
                                    {{ $layout->showAttendance ? 'checked' : '' }}>
                                Attendance row
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="toggles[show_height_weight]" value="1" class="rounded"
                                    {{ $layout->showHeightWeight ? 'checked' : '' }}>
                                Height &amp; weight cells (per academic year; print "Not recorded" when none is recorded)
                            </label>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="toggles[show_legend]" value="1" class="rounded"
                                        {{ $layout->showLegend ? 'checked' : '' }}>
                                    Grading legend table
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="toggles[show_issue_date]" value="1" class="rounded"
                                        {{ $layout->showIssueDate ? 'checked' : '' }}>
                                    Date of issue
                                </label>
                            </div>

                            <div>
                                <label for="label_notes_heading" class="ui-label">Notes heading</label>
                                <input id="label_notes_heading" type="text" name="label_notes_heading" maxlength="60"
                                    value="{{ $layout->label('notes_heading', 'Note:') }}" class="ui-input">
                            </div>
                            <div>
                                <label for="notes" class="ui-label">Notes (one per line)</label>
                                <textarea id="notes" name="notes" rows="5" class="ui-input text-xs">{{ implode("\n", $layout->notes) }}</textarea>
                            </div>

                            <div>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="toggles[show_signatures]" value="1" class="rounded"
                                        {{ $layout->showSignatures ? 'checked' : '' }}>
                                    Signature blocks
                                </label>
                                <textarea name="signatures" rows="6" class="ui-input text-xs"
                                    placeholder="One signature title per line">{{ implode("\n", $layout->signatures) }}</textarea>
                            </div>
                        </div>
                    </section>
                </div>
            </fieldset>
        </form>
    </div>
@endsection
