@extends('layouts.admin')

@section('title', $cycle->exists ? 'Edit Public Admissions Cycle' : 'New Public Admissions Cycle')

@section('content')
    @php
        $isEditing = $cycle->exists;
        $gradeRows = old('grades', $cycle->grades->map(fn ($grade) => ['grade' => $grade->grade, 'sort_order' => $grade->sort_order])->all());
        $stepRows = old('steps', $cycle->steps->map(fn ($step) => ['title' => $step->title, 'body' => $step->body, 'sort_order' => $step->sort_order])->all());
        $requirementRows = old('requirements', $cycle->requirements->map(fn ($requirement) => ['label' => $requirement->label, 'detail' => $requirement->detail, 'sort_order' => $requirement->sort_order])->all());
        $faqRows = old('faqs', $cycle->faqs->map(fn ($faq) => ['question' => $faq->question, 'answer' => $faq->answer, 'sort_order' => $faq->sort_order])->all());
        $gradeRows = array_pad($gradeRows, 12, ['grade' => '', 'sort_order' => '']);
        $stepRows = array_pad($stepRows, 12, ['title' => '', 'body' => '', 'sort_order' => '']);
        $requirementRows = array_pad($requirementRows, 12, ['label' => '', 'detail' => '', 'sort_order' => '']);
        $faqRows = array_pad($faqRows, 12, ['question' => '', 'answer' => '', 'sort_order' => '']);
    @endphp

    <div class="ui-page">
        <x-ui.page-header :title="$isEditing ? 'Edit public admissions cycle' : 'New public admissions cycle'"
            subtitle="Only approved content belongs here. This editor has no access to applicant records.">
            <x-slot name="actions">
                <a href="{{ route('admin.public-admissions.index') }}" class="ui-btn ui-btn-secondary">Back to cycles</a>
            </x-slot>
        </x-ui.page-header>

        @if ($isEditing)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-info-circle mt-0.5"></i>
                <span>
                    Publication: <strong>{{ $states[$cycle->publication_state] }}</strong>.
                    @if ($cycle->publication_state === 'published')
                        Saving changes updates the information already shown to families. Withdraw the cycle first to make changes privately.
                    @else
                        Saving this form does not publish the cycle.
                    @endif
                </span>
            </div>
        @endif

        <form action="{{ $isEditing ? route('admin.public-admissions.update', $cycle) : route('admin.public-admissions.store') }}" method="POST" class="space-y-6">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Cycle and public status</h2>
                        <p class="mt-0.5 text-xs text-slate-500">The public status is what families see after the cycle is published.</p>
                    </div>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-2">
                    <div>
                        <label for="label" class="ui-label">Cycle label <span class="text-rose-600">*</span></label>
                        <input id="label" name="label" value="{{ old('label', $cycle->label) }}" required maxlength="120" class="ui-input @error('label') ui-input-error @enderror">
                        @error('label') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="public_status" class="ui-label">Public status <span class="text-rose-600">*</span></label>
                        <select id="public_status" name="public_status" required class="ui-input @error('public_status') ui-input-error @enderror">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('public_status', $cycle->public_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('public_status') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="public_intro" class="ui-label">Short public introduction</label>
                        <textarea id="public_intro" name="public_intro" rows="3" maxlength="2000" class="ui-input @error('public_intro') ui-input-error @enderror">{{ old('public_intro', $cycle->public_intro) }}</textarea>
                        @error('public_intro') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="display_starts_on" class="ui-label">Display starts</label>
                        <input id="display_starts_on" type="date" name="display_starts_on" value="{{ old('display_starts_on', $cycle->display_starts_on?->toDateString()) }}" class="ui-input @error('display_starts_on') ui-input-error @enderror">
                        @error('display_starts_on') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="display_ends_on" class="ui-label">Display ends</label>
                        <input id="display_ends_on" type="date" name="display_ends_on" value="{{ old('display_ends_on', $cycle->display_ends_on?->toDateString()) }}" class="ui-input @error('display_ends_on') ui-input-error @enderror">
                        <p class="mt-1 text-xs text-slate-500">Dates are inclusive and use Nepal time. A published cycle cannot overlap another published window.</p>
                        @error('display_ends_on') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Contact and approved fee summary</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Leave contact overrides empty to use the public school contact details.</p>
                    </div>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-2">
                    <div>
                        <label for="public_phone" class="ui-label">Admissions phone override</label>
                        <input id="public_phone" name="public_phone" value="{{ old('public_phone', $cycle->public_phone) }}" maxlength="50" class="ui-input @error('public_phone') ui-input-error @enderror">
                        @error('public_phone') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="public_email" class="ui-label">Admissions email override</label>
                        <input id="public_email" type="email" name="public_email" value="{{ old('public_email', $cycle->public_email) }}" class="ui-input @error('public_email') ui-input-error @enderror">
                        @error('public_email') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="primary_action_label" class="ui-label">Primary action label</label>
                        <input id="primary_action_label" name="primary_action_label" value="{{ old('primary_action_label', $cycle->primary_action_label) }}" maxlength="60" placeholder="Plan a visit" class="ui-input @error('primary_action_label') ui-input-error @enderror">
                        @error('primary_action_label') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="primary_action_url" class="ui-label">Primary action destination</label>
                        <input id="primary_action_url" name="primary_action_url" value="{{ old('primary_action_url', $cycle->primary_action_url) }}" maxlength="255" placeholder="/contact#visit" class="ui-input @error('primary_action_url') ui-input-error @enderror">
                        @error('primary_action_url') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="fee_summary" class="ui-label">Approved public fee summary</label>
                        <textarea id="fee_summary" name="fee_summary" rows="4" maxlength="4000" placeholder="Leave empty until fee information is specifically approved for publication." class="ui-input @error('fee_summary') ui-input-error @enderror">{{ old('fee_summary', $cycle->fee_summary) }}</textarea>
                        @error('fee_summary') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Eligible grades</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Use the order column to control how approved grades appear publicly. Leave unused rows blank.</p>
                    </div>
                </div>
                <div class="space-y-3 p-5">
                    @foreach ($gradeRows as $index => $row)
                        <div class="grid gap-3 sm:grid-cols-[7rem_1fr]">
                            <div>
                                <label for="grade-order-{{ $index }}" class="ui-label">Order</label>
                                <input id="grade-order-{{ $index }}" type="number" name="grades[{{ $index }}][sort_order]" min="0" value="{{ $row['sort_order'] ?? '' }}" class="ui-input">
                            </div>
                            <div>
                                <label for="grade-{{ $index }}" class="ui-label">Grade</label>
                                <input id="grade-{{ $index }}" name="grades[{{ $index }}][grade]" maxlength="120" value="{{ $row['grade'] ?? '' }}" class="ui-input">
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Admissions process</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Publish only the verified steps families should follow.</p>
                    </div>
                </div>
                <div class="space-y-5 p-5">
                    @foreach ($stepRows as $index => $row)
                        <div class="grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-[7rem_1fr]">
                            <div>
                                <label for="step-order-{{ $index }}" class="ui-label">Order</label>
                                <input id="step-order-{{ $index }}" type="number" name="steps[{{ $index }}][sort_order]" min="0" value="{{ $row['sort_order'] ?? '' }}" class="ui-input">
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label for="step-title-{{ $index }}" class="ui-label">Step title</label>
                                    <input id="step-title-{{ $index }}" name="steps[{{ $index }}][title]" maxlength="160" value="{{ $row['title'] ?? '' }}" class="ui-input">
                                </div>
                                <div>
                                    <label for="step-body-{{ $index }}" class="ui-label">Explanation</label>
                                    <textarea id="step-body-{{ $index }}" name="steps[{{ $index }}][body]" rows="2" maxlength="2000" class="ui-input">{{ $row['body'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Required documents</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Leave empty until document requirements are approved for publication.</p>
                    </div>
                </div>
                <div class="space-y-5 p-5">
                    @foreach ($requirementRows as $index => $row)
                        <div class="grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-[7rem_1fr]">
                            <div>
                                <label for="requirement-order-{{ $index }}" class="ui-label">Order</label>
                                <input id="requirement-order-{{ $index }}" type="number" name="requirements[{{ $index }}][sort_order]" min="0" value="{{ $row['sort_order'] ?? '' }}" class="ui-input">
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label for="requirement-label-{{ $index }}" class="ui-label">Document</label>
                                    <input id="requirement-label-{{ $index }}" name="requirements[{{ $index }}][label]" maxlength="160" value="{{ $row['label'] ?? '' }}" class="ui-input">
                                </div>
                                <div>
                                    <label for="requirement-detail-{{ $index }}" class="ui-label">Helpful detail</label>
                                    <textarea id="requirement-detail-{{ $index }}" name="requirements[{{ $index }}][detail]" rows="2" maxlength="2000" class="ui-input">{{ $row['detail'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Frequently asked questions</h2>
                        <p class="mt-0.5 text-xs text-slate-500">A question requires an approved answer before it is shown publicly.</p>
                    </div>
                </div>
                <div class="space-y-5 p-5">
                    @foreach ($faqRows as $index => $row)
                        <div class="grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-[7rem_1fr]">
                            <div>
                                <label for="faq-order-{{ $index }}" class="ui-label">Order</label>
                                <input id="faq-order-{{ $index }}" type="number" name="faqs[{{ $index }}][sort_order]" min="0" value="{{ $row['sort_order'] ?? '' }}" class="ui-input">
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label for="faq-question-{{ $index }}" class="ui-label">Question</label>
                                    <input id="faq-question-{{ $index }}" name="faqs[{{ $index }}][question]" maxlength="255" value="{{ $row['question'] ?? '' }}" class="ui-input">
                                </div>
                                <div>
                                    <label for="faq-answer-{{ $index }}" class="ui-label">Answer</label>
                                    <textarea id="faq-answer-{{ $index }}" name="faqs[{{ $index }}][answer]" rows="3" maxlength="4000" class="ui-input">{{ $row['answer'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('admin.public-admissions.index') }}" class="ui-btn ui-btn-secondary">Cancel</a>
                <button type="submit" class="ui-btn ui-btn-primary"><i class="bi bi-check2"></i> Save {{ $isEditing ? 'changes' : 'draft' }}</button>
            </div>
        </form>

        @if ($isEditing)
            <section class="ui-card mt-6">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Publication controls</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Publisher: {{ $cycle->publisher?->name ?? 'Not published yet' }}. Last editor: {{ $cycle->editor?->name ?? 'Not recorded' }}.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 p-5">
                    @if ($cycle->publication_state !== 'published' && $cycle->publication_state !== 'archived')
                        <form action="{{ route('admin.public-admissions.publish', $cycle) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn ui-btn-primary">Publish cycle</button>
                        </form>
                    @endif
                    @if ($cycle->publication_state === 'published')
                        <form action="{{ route('admin.public-admissions.withdraw', $cycle) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn ui-btn-secondary">Withdraw from public site</button>
                        </form>
                    @endif
                    @if ($cycle->publication_state !== 'archived')
                        <form action="{{ route('admin.public-admissions.archive', $cycle) }}" method="POST"
                            onsubmit="return confirm('Archive this cycle? It stays in history and will not appear on the public website.')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn ui-btn-secondary">Archive cycle</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
