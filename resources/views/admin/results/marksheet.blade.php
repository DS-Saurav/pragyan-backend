<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Grade Sheet - {{ $exam->name }} - {{ $class->name }}</title>
    @php
        use App\Models\Attendance;
        use App\Services\GradeScale;
        use App\Services\MarksheetLayout;
        use App\Services\ResultService;

        $n = fn($v) => ResultService::n($v);
        $layout = MarksheetLayout::load();

        // School branding: prefer the keys the admin Settings page saves,
        // fall back to the legacy marksheet-only keys.
        $schoolName = \App\Models\Setting::get('school_name', config('app.name', 'Pragyan Pathashala'));
        $schoolAddress = \App\Models\Setting::get('address', \App\Models\Setting::get('school_address', ''));
        $schoolPhone = \App\Models\Setting::get('phone', \App\Models\Setting::get('school_phone', ''));
        $schoolEmail = \App\Models\Setting::get('email', \App\Models\Setting::get('school_email', ''));
        $schoolLogo = \App\Models\Setting::get('logo_url', \App\Models\Setting::get('school_logo'));
        $logoUrl = $schoolLogo ? \App\Support\Photo::url($schoolLogo) : null;

        // Attendance over the exam window (or all recorded days when the exam
        // has no dates). The window is the exam's start_date/end_date; when
        // neither is set, every recorded day for the class counts.
        $attendanceBase = Attendance::where('school_class_id', $class->id)
            ->when($exam->start_date, fn ($q, $d) => $q->whereDate('date', '>=', $d->toDateString()))
            ->when($exam->end_date, fn ($q, $d) => $q->whereDate('date', '<=', $d->toDateString()));
        // The class's recorded attendance dates, as Y-m-d strings - the raw
        // material for admission-aware per-student denominators. The
        // denominator is CLASS-RECORDED dates, not every instructional day.
        $dateStr = fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->toDateString() : (string) $d;
        $attendanceDates = (clone $attendanceBase)->distinct()->orderBy('date')->pluck('date')->map($dateStr)->values();
        $attendanceTotal = $attendanceDates->count();
        // Per-student distinct dates within the window: of ANY status
        // (coverage evidence), and of present/late (attendance evidence).
        // Pre-admission rows stay OUT of both numerator and eligible
        // denominator - the admission-aware filter is applied per student
        // in the attendance section, where students.admission_date lives.
        $attendanceRows = (clone $attendanceBase)->get(['student_id', 'date', 'status']);
        $distinctDates = fn ($rows) => $rows->pluck('date')->map($dateStr)->unique()->values();
        $attendanceMarked = $attendanceRows
            ->groupBy('student_id')
            ->map($distinctDates);
        $attendancePresent = $attendanceRows
            ->whereIn('status', [Attendance::PRESENT, Attendance::LATE])
            ->groupBy('student_id')
            ->map($distinctDates);
    @endphp
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            background: #e5e7eb;
        }

        .toolbar {
            padding: 10px 16px;
            background: #0f172a;
            color: #fff;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .toolbar button {
            background: #1e40af;
            border: 0;
            color: #fff;
            padding: 6px 14px;
            font-size: 12px;
            cursor: pointer;
        }

        .sheet {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            padding: 10mm 9mm;
            margin: 10px auto;
            background: #fff;
            border: 4px double #1e40af;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
        }

        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            padding: 0 24mm 60mm 0;
            font-size: 54px;
            font-weight: bold;
            color: rgba(30, 64, 175, .10);
            transform: rotate(-25deg);
            pointer-events: none;
            z-index: 0;
        }

        .head {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 6px;
            align-items: center;
        }

        .head .brand {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
        }

        .head p {
            margin: 2px 0;
            font-size: 10px;
        }

        .head .crest,
        .photo {
            width: 64px;
            height: 70px;
        }

        .head .crest,
        .photo {
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid #1e3a8a;
            background: #f8fafc;
        }

        .head .crest img,
        .photo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .head .photo {
            justify-self: end;
        }

        .photo img {
            object-fit: cover;
        }

        .head-note {
            font-size: 10px;
            line-height: 1.35;
        }

        .exam-title {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            margin: 6px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #1e40af;
        }

        .grade-sheet-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 4px 0 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: underline;
        }

        .identity {
            margin-bottom: 8px;
            font-size: 11px;
            line-height: 1.7;
        }

        .identity .pair {
            display: inline-block;
            margin-right: 18px;
        }

        .identity .label {
            font-weight: bold;
        }

        .identity .val {
            font-weight: bold;
            text-decoration: underline;
        }

        .identity .na {
            text-decoration: none;
            color: #64748b;
        }

        table.marks {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #1e40af;
        }

        table.marks th,
        table.marks td {
            border: 1px solid #1e40af;
            padding: 4px 3px;
            text-align: center;
            font-size: 10px;
        }

        table.marks th {
            background: #eff6ff;
            font-weight: bold;
        }

        table.marks td.left {
            text-align: left;
        }

        table.marks td .sub {
            display: block;
            font-size: 9px;
            color: #334155;
        }

        table.marks .merged-cell {
            font-weight: bold;
        }

        .fail {
            color: #b91c1c;
            font-weight: bold;
        }

        .gpa-summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            border: 2px solid #1e40af;
        }

        .gpa-summary td {
            border: 1px solid #1e40af;
            padding: 5px 8px;
            text-align: center;
            font-weight: bold;
            background: #eff6ff;
            font-size: 10px;
        }

        .assessment,
        .meta-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            border: 1px solid #1e40af;
        }

        .assessment th,
        .assessment td,
        .meta-row th,
        .meta-row td {
            border: 1px solid #1e40af;
            padding: 3px 5px;
            font-size: 10px;
        }

        .assessment th,
        .meta-row th {
            background: #eff6ff;
            text-align: left;
        }

        .assessment td.grade {
            text-align: center;
            width: 60px;
        }

        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 230px;
            gap: 12px;
            margin-top: 8px;
            align-items: start;
        }

        .notes {
            font-size: 9px;
            line-height: 1.4;
        }

        .notes h4 {
            margin: 4px 0;
            font-size: 10px;
        }

        .notes ul {
            margin: 2px 0;
            padding-left: 14px;
        }

        .scale-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #1e40af;
            font-size: 9px;
        }

        .scale-table th,
        .scale-table td {
            border: 1px solid #1e40af;
            padding: 2px 4px;
            text-align: center;
        }

        .scale-table th {
            background: #eff6ff;
        }

        .issue-date {
            margin-top: 24px;
            font-size: 11px;
        }

        .issue-date .val {
            font-weight: bold;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
            gap: 24px;
            margin-top: 44px;
            text-align: center;
            font-size: 10px;
        }

        .signatures .sig-line {
            border-top: 1px solid #111;
            padding-top: 4px;
            font-weight: bold;
        }

        @page {
            size: A4;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }

            table.marks tr,
            .gpa-summary tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <span>{{ count($rows) }} marksheet(s) · {{ $exam->name }} · {{ $class->name }}</span>
    </div>

    @forelse ($rows as $row)
        <div class="sheet">
            @php
                $student = $row['student'];
                // Assessment indicators (Stage P3I): ratings saved per
                // EXAM and student - keyed by the layout's STABLE indicator
                // key, so renaming a label never loses a rating. Unset
                // indicators render the explicit "Not recorded" state in
                // the assessment section.
                $studentAssessments = \App\Models\StudentAssessment::where('exam_id', $exam->id)
                    ->where('student_id', $student->id)
                    ->pluck('rating', 'indicator_key');
                // Height/weight (Stage P3G): measurements are per academic
                // year. Resolve the record for THE EXAM'S OWN academic_year
                // via exact match - never a newer/older year's row, never a
                // fabricated value. Null height/weight (or no record at
                // all) renders as the explicit "Not recorded" state in the
                // attendance/height-weight section.
                $studentMeasurement = \App\Models\StudentMeasurement::where('student_id', $student->id)
                    ->where('academic_year', $exam->academic_year)
                    ->first();
                $heightCm = $studentMeasurement?->height_cm;
                $weightKg = $studentMeasurement?->weight_kg;
            @endphp
            @if (! $layout->showWatermark && $exam->status !== 'published')
                <div class="watermark">PROVISIONAL</div>
            @elseif ($layout->showWatermark)
                <div class="watermark">{{ $layout->watermarkText }}</div>
            @endif

            @foreach ($layout->order as $section)
                @include('admin.results.marksheet.sections.'.$section)
            @endforeach
        </div>
    @empty
        <div class="sheet">
            <p>No students found.</p>
        </div>
    @endforelse
</body>

</html>
