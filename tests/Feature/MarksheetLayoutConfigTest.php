<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\MarksheetLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layout-configuration behaviour only: defaults, persistence round trip,
 * safe fallbacks and sanitization. No exam or student records involved.
 */
class MarksheetLayoutConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_match_sections_and_protect_the_grade_column(): void
    {
        $layout = new MarksheetLayout;

        foreach (MarksheetLayout::SECTIONS as $section) {
            $this->assertContains($section, $layout->order);
        }

        $form = MarksheetLayout::fromForm([
            'toggles' => [],
            'order' => MarksheetLayout::SECTIONS,
        ]);
        // The grade column always stays on whatever the form says.
        $this->assertTrue($form->columns['grade']);
        $this->assertContains('identity', $form->order);
        $this->assertContains('subjects', $form->order);
        $this->assertContains('gpa_result', $form->order);
    }

    public function test_json_round_trip_through_the_settings_table(): void
    {
        $original = MarksheetLayout::fromForm([
            'toggles' => [
                'col_credit_hour' => '0', // checkbox absent in a real form; explicit here
                'show_component_marks' => '1',
                'show_watermark' => '1',
            ],
            'watermark_text' => 'SCHOOL COPY',
            'credit_hours' => "NEP002=3.75\nNEP003=1.25",
            'assessment_labels' => ['Cleanliness', 'Sports'],
            'assessment_keys' => ['cleanliness', 'sports'],
            'signatures' => "Prepared By\nChecked By\nController of Examination",
            'notes' => "a note\n<b>markup?</b> note",
            'sections_order' => "GPA & result summary\nStudent information\nSubject & result table",
        ]);

        Setting::set('marksheet_layout', $original->toJson());
        $loaded = MarksheetLayout::load();

        $this->assertSame(true, $loaded->showComponentMarks);
        $this->assertTrue($loaded->showWatermark);
        $this->assertSame('SCHOOL COPY', $loaded->watermarkText);
        $this->assertSame(['NEP002' => '3.75', 'NEP003' => '1.25'], $loaded->creditHours);
        $this->assertSame(
            [['key' => 'cleanliness', 'label' => 'Cleanliness'], ['key' => 'sports', 'label' => 'Sports']],
            $loaded->assessmentRows,
            'Stable keys and display labels round-trip through the settings table.'
        );
        $this->assertSame('Prepared By', $loaded->signatures[0]);
        $this->assertSame('markup? note', $loaded->notes[1], 'Tags must be stripped from notes.');
    }

    public function test_malformed_or_missing_configuration_falls_back_to_defaults(): void
    {
        Setting::set('marksheet_layout', '{ not valid json ][ 32');

        $layout = MarksheetLayout::load();

        $this->assertSame(MarksheetLayout::SECTIONS, $layout->order);
        $this->assertTrue($layout->flag('columns', 'grade'));
        $this->assertSame([], $layout->creditHours);
        $this->assertFalse($layout->showWatermark);

        // Nothing stored at all: same safe defaults.
        Setting::where('key', 'marksheet_layout')->delete();
        $this->assertSame(MarksheetLayout::SECTIONS, MarksheetLayout::load()->order);
    }

    public function test_unknown_sections_are_dropped_and_protected_sections_survive_deletion(): void
    {
        $layout = new MarksheetLayout(['order' => ['signatures', 'not_a_section', 'header', 'gpa_result']]);

        $this->assertNotContains('not_a_section', $layout->order);
        // Identity/subjects/gpa/header sections always render, in their default spot.
        $this->assertContains('identity', $layout->order);
        $this->assertContains('subjects', $layout->order);
        foreach (MarksheetLayout::PROTECTED_SECTIONS as $required) {
            $this->assertContains($required, $layout->order);
        }
        // Optional sections reappear from the default tail.
        $this->assertCount(count(MarksheetLayout::SECTIONS), $layout->order);
        $this->assertCount(count(array_unique($layout->order)), $layout->order, 'No duplicate sections.');
    }

    public function test_reordered_sections_persist(): void
    {
        $in = MarksheetLayout::fromForm([
            'toggles' => [],
            'order' => ['gpa_result', 'header', 'identity', 'subjects',
                'assessment', 'attendance', 'notes', 'legend', 'issue_date', 'signatures'],
        ]);

        $this->assertSame('gpa_result', $in->order[0]);
        $this->assertContains('identity', $in->order);
        $this->assertContains('subjects', $in->order);
        $this->assertSame(count(MarksheetLayout::SECTIONS), count($in->order));
        $this->assertCount(count(array_unique($in->order)), $in->order);
    }

    public function test_strings_are_limited_and_sanitized(): void
    {
        $layout = new MarksheetLayout(
            notes: array_fill(0, 30, '<img>x'),
            signatures: ['<script>alert(1)</script>'.str_repeat('x', 80), ''],
            assessmentRows: array_fill(0, 40, 'y'),
            watermarkText: '<b>'.str_repeat('W', 50).'</b>',
        );

        $this->assertCount(15, $layout->notes);
        $this->assertCount(15, $layout->assessmentRows);
        $this->assertSame('alert(1)'.str_repeat('x', 52), $layout->signatures[0], 'Stripped tags and length-capped at 60.');
        $this->assertSame(str_repeat('W', 30), $layout->watermarkText, 'Stripped tags and length-capped at 30.');
    }

    public function test_from_form_maps_checkbox_and_line_fields(): void
    {
        $layout = MarksheetLayout::fromForm([
            'toggles' => [
                'header_show_logo' => '1',
                'id_dob' => '1',
                'gpa_rank' => '1',
                'show_attendance' => '1',
                'show_legend' => '1',
                'show_signatures' => '1',
                'show_issue_date' => '1',
                'show_height_weight' => '1',
                'show_assessment' => '1',
            ],
            'label_exam_title' => 'PROGRESS REPORT',
            'label_notes_heading' => 'Remarks:',
            'credit_hours' => "mth9=5\nbogus\nN=-1\nX99=y",
        ]);

        $this->assertSame('PROGRESS REPORT', $layout->label('exam_title', 'GRADE SHEET'));
        $this->assertSame('Remarks:', $layout->label('notes_heading', 'Note:'));
        $this->assertTrue($layout->flag('identity', 'dob'));
        $this->assertTrue($layout->flag('gpa', 'rank'));
        $this->assertSame(['MTH9' => '5'], $layout->creditHours, 'Only well-formed positive entries are kept.');
        $this->assertFalse($layout->showComponentMarks);
        $this->assertTrue($layout->showLegend);
    }
}
