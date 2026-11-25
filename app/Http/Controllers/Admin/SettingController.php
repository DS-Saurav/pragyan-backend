<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MarksheetLayout;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /** Friendly section names offered in the layout editor (orderable one per line). */
    public const MARKSHEET_SECTION_NAMES = [
        'header' => 'Print header & branding',
        'identity' => 'Student information',
        'subjects' => 'Subject & result table',
        'gpa_result' => 'GPA & result summary',
        'assessment' => 'Assessment indicators',
        'attendance' => 'Attendance / height & weight',
        'notes' => 'Notes',
        'legend' => 'Grading legend table',
        'issue_date' => 'Date of issue',
        'signatures' => 'Signature blocks',
    ];

    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'school_name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'logo_url' => 'nullable|string',
            'time_zone' => 'required|string',
            'currency_symbol' => 'required|string|max:10',
            'academic_year' => 'required|string|max:20',
            'primary_color' => 'required|string|max:20',
            'accent_color' => 'required|string|max:20',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'School settings updated successfully.');
    }

    public function marksheet()
    {
        $layout = MarksheetLayout::load();

        return view('admin.settings.marksheet', [
            'layout' => $layout,
            'sections' => self::MARKSHEET_SECTION_NAMES,
            'canManage' => auth()->user()->can('settings.manage'),
        ]);
    }

    public function updateMarksheet(Request $request)
    {
        $order = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('sections_order', '')) as $line) {
            $name = trim($line);
            if ($name === '') {
                continue;
            }
            foreach (self::MARKSHEET_SECTION_NAMES as $id => $friendly) {
                if (strcasecmp($name, $friendly) === 0 || strcasecmp($name, $id) === 0) {
                    $order[] = $id;
                    break;
                }
            }
        }

        $layout = MarksheetLayout::fromForm($request->all() + ['order' => $order]);
        Setting::set('marksheet_layout', $layout->toJson());

        return redirect()->route('admin.settings.marksheet')
            ->with('success', 'Marksheet layout updated successfully.');
    }
}
