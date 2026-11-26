<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MarksheetLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
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

    public function index(): JsonResponse
    {
        return response()->json(['settings' => $this->loadSettings()]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schoolName' => 'required_without:school_name|string|max:255',
            'school_name' => 'required_without:schoolName|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'website' => 'nullable|string|max:255',
            'logoUrl' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string|max:500',
            'timeZone' => 'required_without:time_zone|string|max:100',
            'time_zone' => 'required_without:timeZone|string|max:100',
            'currencySymbol' => 'required_without:currency_symbol|string|max:10',
            'currency_symbol' => 'required_without:currencySymbol|string|max:10',
            'academicYear' => 'required_without:academic_year|string|max:20',
            'academic_year' => 'required_without:academicYear|string|max:20',
            'primaryColor' => 'required_without:primary_color|string|max:20',
            'primary_color' => 'required_without:primaryColor|string|max:20',
            'accentColor' => 'required_without:accent_color|string|max:20',
            'accent_color' => 'required_without:accentColor|string|max:20',
        ]);

        $map = [
            'school_name' => $validated['schoolName'] ?? $validated['school_name'],
            'tagline' => $validated['tagline'] ?? '',
            'address' => $validated['address'] ?? '',
            'phone' => $validated['phone'] ?? '',
            'email' => $validated['email'] ?? '',
            'website' => $validated['website'] ?? '',
            'logo_url' => $validated['logoUrl'] ?? $validated['logo_url'] ?? '',
            'time_zone' => $validated['timeZone'] ?? $validated['time_zone'],
            'currency_symbol' => $validated['currencySymbol'] ?? $validated['currency_symbol'],
            'academic_year' => $validated['academicYear'] ?? $validated['academic_year'],
            'primary_color' => $validated['primaryColor'] ?? $validated['primary_color'],
            'accent_color' => $validated['accentColor'] ?? $validated['accent_color'],
        ];

        foreach ($map as $key => $value) {
            Setting::set($key, (string) $value);
        }

        return response()->json(['settings' => $this->loadSettings()]);
    }

    public function marksheet(): JsonResponse
    {
        return response()->json([
            'layout' => MarksheetLayout::load(),
            'sections' => self::MARKSHEET_SECTION_NAMES,
        ]);
    }

    public function updateMarksheet(Request $request): JsonResponse
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

        if ($order !== []) {
            Setting::set('marksheet_sections_order', json_encode(array_values(array_unique($order))));
        }

        return response()->json(['layout' => MarksheetLayout::load()]);
    }

    private function loadSettings(): array
    {
        $raw = Setting::pluck('value', 'key')->toArray();

        return [
            'schoolName' => $raw['school_name'] ?? 'Pragyan Pathashala',
            'tagline' => $raw['tagline'] ?? 'Learn. Grow. Succeed.',
            'address' => $raw['address'] ?? 'Latikoili, Birendranagar-9, Surkhet, Karnali Province, Nepal',
            'phone' => $raw['phone'] ?? '+977 83-520100',
            'email' => $raw['email'] ?? 'info@pragyanpathashala.edu.np',
            'website' => $raw['website'] ?? 'https://www.pragyanpathashala.edu.np',
            'logoUrl' => $raw['logo_url'] ?? '',
            'timeZone' => $raw['time_zone'] ?? 'Asia/Kathmandu',
            'currencySymbol' => $raw['currency_symbol'] ?? 'रू',
            'academicYear' => $raw['academic_year'] ?? '2082-2083',
            'primaryColor' => $raw['primary_color'] ?? '#0f2248',
            'accentColor' => $raw['accent_color'] ?? '#16a34a',
        ];
    }
}
