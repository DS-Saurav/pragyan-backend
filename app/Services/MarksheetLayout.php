<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Validated marksheet layout configuration.
 *
 * Stored as one JSON document under the `marksheet_layout` setting key
 * (no schema change needed - `settings.value` is TEXT). Invalid or missing
 * configurations always fall back to safe built-in defaults; unknown keys
 * and sections are dropped, and control sections carrying student identity
 * and result data (header/identity/subjects/gpa_result) can never be
 * removed from the render order.
 *
 * This is layout/presentation configuration only. It intentionally has no
 * influence over grade bands, GPA math, pass/absent/NG logic or divisions.
 */
class MarksheetLayout
{
    /** Default render order of the marksheet sections. */
    public const SECTIONS = [
        'header',
        'identity',
        'subjects',
        'gpa_result',
        'assessment',
        'attendance',
        'notes',
        'legend',
        'issue_date',
        'signatures',
    ];

    /** Sections that cannot be removed (student identity + result integrity). */
    public const PROTECTED_SECTIONS = ['header', 'identity', 'subjects', 'gpa_result'];

    /** Subject-table column flags that may be toggled. */
    public const COLUMNS = ['sn', 'subject_code', 'credit_hour', 'grade_point', 'grade', 'final_grade', 'remarks'];

    /** GPA summary flags that may be toggled ("result_label" governs the result cell). */
    public const GPA_FLAGS = ['total_marks', 'percentage', 'gpa', 'grade', 'division', 'rank', 'result_label'];

    /**
     * Overall result conditions exactly as classified by ResultService.
     * Subject-level grades (NG/AB) are NOT conditions here - condition
     * rules only ever affect the overall result summary section.
     */
    public const RESULT_CONDITIONS = ['pass', 'fail', 'incomplete'];

    /** Summary cells controllable per overall result condition. */
    public const CONDITION_FIELDS = ['gpa', 'grade', 'division', 'total_marks', 'percentage', 'rank', 'result_label'];

    /** Identity-line flags that may be toggled (name/roll/class stay always on). */
    public const IDENTITY_FLAGS = ['dob', 'dob_bs', 'admission_no', 'exam_name', 'academic_year'];

    /**
     * Assessment indicator rows as list of ['key' => stable, 'label' => display]
     * pairs. The KEY is the stable machine identity (stored with saved
     * ratings, never regenerated when the label is edited); the LABEL is
     * the administrator-editable display text. Legacy saved documents that
     * stored plain strings get their keys derived deterministically from
     * the label once, and the settings editor keeps re-submitting the
     * hidden keys so later renames preserve every existing rating.
     */
    public function __construct(
        public array $order = self::SECTIONS,
        public array $labels = [],
        public array $header = [],
        public array $identity = [],
        public array $columns = [],
        public array $gpa = [],
        public bool $showComponentMarks = false,
        public bool $showAssessment = true,
        public array $assessmentRows = [],
        public bool $showAttendance = true,
        public bool $showHeightWeight = true,
        public array $notes = [],
        public bool $showLegend = true,
        public bool $showIssueDate = true,
        public bool $showSignatures = true,
        public array $signatures = [],
        public bool $showWatermark = false,
        public string $watermarkText = 'PROVISIONAL',
        public array $creditHours = [],
        public array $conditionRules = [],
    ) {
        $this->order = $this->normalizeOrder($this->order);
        $this->labels = $this->normalizeLabels($this->labels);
        $this->header = $this->filterFlags($this->header, ['show_logo', 'show_photo']);
        $this->identity = $this->filterFlags($this->identity, self::IDENTITY_FLAGS);
        $this->columns = $this->filterFlags($this->columns, self::COLUMNS);
        $this->gpa = $this->filterFlags($this->gpa, self::GPA_FLAGS);
        $this->assessmentRows = $this->normalizeAssessmentRows($this->assessmentRows);
        $this->notes = $this->normalizeStrings($this->notes, 15, 200);
        $this->signatures = $this->normalizeStrings($this->signatures, 6, 60);
        $this->creditHours = $this->normalizeCreditHours($this->creditHours);
        $this->conditionRules = $this->normalizeConditionRules($this->conditionRules);
        $this->watermarkText = $this->clean($this->watermarkText, 30) ?: 'PROVISIONAL';
    }

    /** Load the persisted configuration, safely falling back to defaults. */
    public static function load(): self
    {
        $raw = Setting::get('marksheet_layout');

        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $result = json_decode($raw, true);
            if (is_array($result)) {
                $decoded = $result;
            }
        }

        if ($decoded === null) {
            return new self; // full defaults
        }

        return new self(
            order: $decoded['order'] ?? self::SECTIONS,
            labels: $decoded['labels'] ?? [],
            header: $decoded['header'] ?? [],
            identity: $decoded['identity'] ?? [],
            columns: $decoded['columns'] ?? [],
            gpa: $decoded['gpa'] ?? [],
            showComponentMarks: filter_var($decoded['show_component_marks'] ?? false, FILTER_VALIDATE_BOOL),
            showAssessment: filter_var($decoded['show_assessment'] ?? true, FILTER_VALIDATE_BOOL),
            assessmentRows: $decoded['assessment_rows'] ?? [],
            showAttendance: filter_var($decoded['show_attendance'] ?? true, FILTER_VALIDATE_BOOL),
            showHeightWeight: filter_var($decoded['show_height_weight'] ?? true, FILTER_VALIDATE_BOOL),
            notes: $decoded['notes'] ?? [],
            showLegend: filter_var($decoded['show_legend'] ?? true, FILTER_VALIDATE_BOOL),
            showIssueDate: filter_var($decoded['show_issue_date'] ?? true, FILTER_VALIDATE_BOOL),
            showSignatures: filter_var($decoded['show_signatures'] ?? true, FILTER_VALIDATE_BOOL),
            signatures: $decoded['signatures'] ?? [],
            showWatermark: filter_var($decoded['show_watermark'] ?? false, FILTER_VALIDATE_BOOL),
            watermarkText: (string) ($decoded['watermark_text'] ?? ''),
            creditHours: $decoded['credit_hours'] ?? [],
            conditionRules: $decoded['condition_rules'] ?? [],
        );
    }

    /** Full defaults - exactly what a fresh install renders today. */
    public static function defaults(): array
    {
        return [
            'order' => self::SECTIONS,
            'labels' => [
                'exam_title' => 'GRADE SHEET',
                'notes_heading' => 'Note:',
            ],
            'header' => ['show_logo' => true, 'show_photo' => true],
            'identity' => [
                'dob' => true,
                'dob_bs' => false,
                'admission_no' => true,
                'exam_name' => true,
                'academic_year' => true,
            ],
            'columns' => [
                'sn' => true,
                'subject_code' => true,
                'credit_hour' => true,
                'grade_point' => true,
                'grade' => true,
                'final_grade' => true,
                'remarks' => true,
            ],
            'gpa' => [
                'total_marks' => true,
                'percentage' => true,
                'gpa' => true,
                'grade' => true,
                'division' => true,
                'rank' => true,
            ],
            'show_component_marks' => false,
            'show_assessment' => true,
            // Assessment indicator rows (key/label pairs - ratings come
            // from student_assessments per exam, Stage P3I).
            'assessment_rows' => [
                ['key' => 'handwriting', 'label' => 'Handwriting'],
                ['key' => 'punctuality', 'label' => 'Punctuality'],
                ['key' => 'cleanliness_discipline', 'label' => 'Cleanliness & Discipline'],
                ['key' => 'participation_in_co_curricular_activities', 'label' => 'Participation in Co-curricular Activities'],
                ['key' => 'general_conduct', 'label' => 'General Conduct'],
            ],
            'show_attendance' => true,
            'show_height_weight' => true,
            'notes' => [
                'Theory (TH) and internal (IN) components are combined into one subject row; the subject grade already reflects both components.',
                'Credit Hour (CH) and Final Grade are shown as \'-\' where no verified source exists in the system.',
                'AB = Absent, NG = Not Graded. Below 35% is NG with no grade point (CDC Letter Grading Directive, 2083).',
                'GPA = total(Credit Hour x Grade Point) / total Credit Hours when credit hours are recorded for every subject; otherwise the unweighted mean of grade points is shown and labelled as such.',
            ],
            'show_legend' => true,
            'show_issue_date' => true,
            'show_signatures' => true,
            // Full six-signature list preserves the historical output
            // (the former duplicate block) until administrators change it.
            'signatures' => [
                'Prepared By',
                'Checked By',
                'Controller of Examination',
                'Class Teacher',
                'Exam Coordinator',
                'Principal',
            ],
            'show_watermark' => false,
            'watermark_text' => 'PROVISIONAL',
            'credit_hours' => [],
            'condition_rules' => [],
        ];
    }

    /** Serialize for storage. */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function toArray(): array
    {
        return [
            'order' => $this->order,
            'labels' => $this->labels,
            'header' => $this->header,
            'identity' => $this->identity,
            'columns' => $this->columns,
            'gpa' => $this->gpa,
            'show_component_marks' => $this->showComponentMarks,
            'show_assessment' => $this->showAssessment,
            'assessment_rows' => $this->assessmentRows,
            'show_attendance' => $this->showAttendance,
            'show_height_weight' => $this->showHeightWeight,
            'notes' => $this->notes,
            'show_legend' => $this->showLegend,
            'show_issue_date' => $this->showIssueDate,
            'show_signatures' => $this->showSignatures,
            'signatures' => $this->signatures,
            'show_watermark' => $this->showWatermark,
            'watermark_text' => $this->watermarkText,
            'credit_hours' => $this->creditHours,
            'condition_rules' => $this->conditionRules,
        ];
    }

    /** True when a toggle flag is on (defaults to the built-in default). */
    public function flag(string $group, string $key, bool $default = true): bool
    {
        $prop = match ($group) {
            'header', 'identity', 'columns', 'gpa' => $this->{$group},
            default => [],
        };

        return (bool) ($prop[$key] ?? $default);
    }

    /**
     * Effective display flag for one overall-result summary cell on the
     * marksheet, honouring per-condition overrides saved by an authorized
     * administrator ("condition_rules"). Conditions come straight from
     * ResultService's `$result` values (PASS/FAIL/INCOMPLETE, case
     * insensitive); unknown values fall back to the global GPA flags.
     *
     * Safe default: an INCOMPLETE result shows only the honest
     * "INCOMPLETE" result label - overall grade, GPA, division, total
     * marks, percentage and rank stay hidden until a rule explicitly
     * turns each one on, so marks entered so far can never be presented
     * as the final outcome.
     */
    public function gpaFlagFor(string $result, string $key): bool
    {
        $condition = strtolower(trim($result));
        $rules = $this->conditionRules[$condition] ?? null;

        if (is_array($rules) && array_key_exists($key, $rules)) {
            return (bool) $rules[$key];
        }

        if ($condition === 'incomplete' && $key !== 'result_label') {
            return false;
        }

        return $this->flag('gpa', $key, true);
    }

    public function label(string $key, string $default): string
    {
        return $this->labels[$key] ?? $default;
    }

    /** Credit hours configured for a subject code, or null when unconfigured. */
    public function creditHour(?string $code): ?string
    {
        if ($code !== null && isset($this->creditHours[$code])) {
            return (string) $this->creditHours[$code];
        }

        return null;
    }

    /**
     * Build a layout from a raw admin form payload (all fields optional).
     * Anything invalid falls back to the built-in default silently -
     * the persisted document is always safe to render.
     */
    public static function fromForm(array $in): self
    {
        $signed = array_map([self::class, 'truthy'], $in['toggles'] ?? []);
        $flags = fn (string $key) => $signed[$key] ?? false;

        $columns = [];
        foreach (self::COLUMNS as $key) {
            $columns[$key] = $flags('col_'.$key);
        }
        // The grade column carries the actual result - keep it always on.
        $columns['grade'] = true;

        $identity = [];
        foreach (self::IDENTITY_FLAGS as $key) {
            $identity[$key] = $flags('id_'.$key);
        }

        $gpa = [];
        foreach (self::GPA_FLAGS as $key) {
            $gpa[$key] = $flags('gpa_'.$key);
        }

        $header = [
            'show_logo' => $flags('header_show_logo'),
            'show_photo' => $flags('header_show_photo'),
        ];

        $lines = fn (string $key): array => array_values(array_filter(
            array_map(fn ($v) => self::clean($v, 200), preg_split('/\r\n|\r|\n/', (string) ($in[$key] ?? ''))),
            fn ($v) => $v !== '',
        ));

        // Assessment indicators (Stage P3I): the editor submits one label
        // input per row, each paired by position with a HIDDEN key input
        // (blank rows and their keys are dropped). Keys keep saved ratings
        // mapped across label renames; new rows without a key get a stable
        // key derived from their label when normalized below.
        $assessmentPairs = [];
        $submittedLabels = (array) ($in['assessment_labels'] ?? []);
        $submittedKeys = (array) ($in['assessment_keys'] ?? []);
        $pairCount = max(count($submittedLabels), count($submittedKeys));
        for ($i = 0; $i < $pairCount; $i++) {
            $label = (string) ($submittedLabels[$i] ?? '');
            if (trim($label) === '') {
                continue; // blank editor row: dropped, key along with it
            }
            $assessmentPairs[] = [
                'key' => (string) ($submittedKeys[$i] ?? ''),
                'label' => $label,
            ];
        }

        $creditHours = [];
        foreach ($lines('credit_hours') as $line) {
            if (preg_match('/^([A-Za-z0-9._\-\/ ]{1,20})\s*=\s*(\d{1,3}(?:\.\d{1,2})?)$/', $line, $m)) {
                $creditHours[strtoupper($m[1])] = $m[2];
            }
        }

        $conditionRules = [];
        foreach ((array) ($in['condition_rules'] ?? []) as $conditionKey => $rules) {
            $conditionKey = is_string($conditionKey) ? strtolower(trim($conditionKey)) : '';
            if (! in_array($conditionKey, self::RESULT_CONDITIONS, true) || ! is_array($rules)) {
                continue;
            }
            foreach ($rules as $fieldKey => $value) {
                $fieldKey = is_string($fieldKey) ? $fieldKey : '';
                // Absent/null/'' means "inherit" - not stored as a rule.
                if (in_array($fieldKey, self::CONDITION_FIELDS, true) && $value !== null && $value !== '') {
                    $conditionRules[$conditionKey][$fieldKey] = self::truthy($value);
                }
            }
        }

        return new self(
            order: (array) ($in['order'] ?? []),
            labels: [
                'exam_title' => self::clean($in['label_exam_title'] ?? '', 60) ?: 'GRADE SHEET',
                'notes_heading' => self::clean($in['label_notes_heading'] ?? '', 60) ?: 'Note:',
            ],
            header: $header,
            identity: $identity,
            columns: $columns,
            gpa: $gpa,
            showComponentMarks: $flags('show_component_marks'),
            showAssessment: $flags('show_assessment'),
            assessmentRows: $assessmentPairs,
            showAttendance: $flags('show_attendance'),
            showHeightWeight: $flags('show_height_weight'),
            notes: $lines('notes'),
            showLegend: $flags('show_legend'),
            showIssueDate: $flags('show_issue_date'),
            showSignatures: $flags('show_signatures'),
            signatures: $lines('signatures'),
            showWatermark: $flags('show_watermark'),
            watermarkText: (string) ($in['watermark_text'] ?? ''),
            creditHours: $creditHours,
            conditionRules: $conditionRules,
        );
    }

    private static function truthy($value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes'], true);
    }

    private function normalizeOrder(array $order): array
    {
        $clean = array_values(array_unique(array_filter(
            array_map(fn ($v) => is_string($v) ? trim($v) : '', $order),
            fn ($v) => in_array($v, self::SECTIONS, true),
        )));

        // Missing sections (including all protected ones) slide back into
        // their default slot so a custom order stays sensible.
        foreach (self::SECTIONS as $idx => $section) {
            if (in_array($section, $clean, true)) {
                continue;
            }
            $pos = count($clean);
            foreach ($clean as $j => $existing) {
                if (array_search($existing, self::SECTIONS, true) > $idx) {
                    $pos = $j;
                    break;
                }
            }
            array_splice($clean, $pos, 0, [$section]);
        }

        return $clean;
    }

    private function normalizeLabels(array $labels): array
    {
        $out = [];
        foreach (['exam_title', 'notes_heading'] as $key) {
            $value = $this->clean($labels[$key] ?? '', 60);
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function filterFlags(array $in, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $in)) {
                $out[$key] = filter_var($in[$key], FILTER_VALIDATE_BOOL);
            }
        }

        return $out;
    }

    private function normalizeStrings(array $in, int $maxItems, int $maxLen): array
    {
        if (! is_array($in)) {
            return [];
        }

        $out = [];
        foreach ($in as $item) {
            if (is_array($item)) {
                $item = $item['label'] ?? '';
            }
            $value = $this->clean((string) $item, $maxLen);
            if ($value !== '') {
                $out[] = $value;
            }
            if (count($out) >= $maxItems) {
                break;
            }
        }

        return $out;
    }

    /**
     * Normalize assessment indicator rows into unique {key, label} pairs
     * (see the constructor docblock). Accepts pairs from new documents and
     * plain strings from legacy ones. Keys are cleaned, lowercased, capped
     * and made unique (derived from the label slug when missing/colliding);
     * labels are tag-stripped and capped (max 15 rows, like before).
     */
    private function normalizeAssessmentRows(array $in): array
    {
        if (! is_array($in)) {
            return [];
        }

        $out = [];
        foreach ($in as $item) {
            if (is_array($item)) {
                $label = $this->clean((string) ($item['label'] ?? ''), 80);
                $key = $this->normalizeAssessmentKey((string) ($item['key'] ?? ''));
            } else {
                $label = $this->clean((string) $item, 80);
                $key = '';
            }

            if ($label === '' || $key === '' || in_array($key, array_column($out, 'key'), true)) {
                if ($label === '') {
                    continue;
                }
                // Derive or deduplicate from the label slug.
                $derived = $this->assessmentKeyFromLabel($label, array_column($out, 'key'));
                $out[] = ['key' => $derived, 'label' => $label];
            } else {
                $out[] = ['key' => $key, 'label' => $label];
            }

            if (count($out) >= 15) {
                break;
            }
        }

        return $out;
    }

    /** Machine-safe indicator key: lowercase [a-z0-9_], max 60 chars. */
    private function normalizeAssessmentKey(string $raw): string
    {
        return mb_substr(preg_replace('/[^a-z0-9_]/', '', strtolower(trim($raw))), 0, 60);
    }

    /** Stable dedup-safe slug for a label, e.g. "Cleanliness & Discipline" -> cleanliness_discipline. */
    private function assessmentKeyFromLabel(string $label, array $usedKeys): string
    {
        $base = $this->normalizeAssessmentKey($label);
        if ($base === '') {
            // Non-Latin label: fall back to a stable suffix series rather
            // than string identity.
            $base = 'indicator';
        }

        $key = $base;
        $n = 2;
        while (in_array($key, $usedKeys, true)) {
            $key = $base.'_'.$n;
            $n++;
        }

        return $key;
    }

    private function normalizeCreditHours(array $in): array
    {
        $out = [];
        if (is_array($in)) {
            foreach ($in as $code => $value) {
                $code = strtoupper($this->clean((string) $code, 20));
                if ($code === '' || ! is_numeric($value) || (float) $value <= 0) {
                    continue;
                }
                $out[$code] = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            }
        }

        return $out;
    }

    /**
     * Per-overall-result display rules, strictly allowlisted: unknown
     * conditions and unknown field keys are dropped, values are coerced
     * to booleans, and empty rule sets are removed so the persisted
     * document only maps what an administrator actually chose.
     */
    private function normalizeConditionRules(array $in): array
    {
        $out = [];
        foreach ($in as $condition => $rules) {
            $condition = is_string($condition) ? strtolower(trim($condition)) : '';
            if (! in_array($condition, self::RESULT_CONDITIONS, true) || ! is_array($rules)) {
                continue;
            }
            $clean = [];
            foreach ($rules as $key => $value) {
                if (is_string($key) && in_array($key, self::CONDITION_FIELDS, true)) {
                    $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOL);
                }
            }
            if ($clean !== []) {
                $out[$condition] = $clean;
            }
        }

        return $out;
    }

    private static function clean(string $raw, int $maxLen): string
    {
        // Presentation strings only - tags stripped, escaped later in Blade with {{ }}.
        $value = strip_tags(trim($raw));

        return mb_substr($value, 0, $maxLen);
    }
}
