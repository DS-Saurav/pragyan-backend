<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\DisciplineIncident;
use App\Models\FeeReceipt;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isCommunity = $user->hasRole('student', 'parent');

        $stats = [];

        if (! $isCommunity) {
            $stats[] = [
                'label' => 'Total students',
                'value' => Student::count(),
                'icon' => 'bi-mortarboard',
                'tone' => 'emerald',
            ];

            $stats[] = [
                'label' => 'Pending admissions',
                'value' => Admission::where('status', 'registered')->count(),
                'icon' => 'bi-person-plus',
                'tone' => 'sky',
            ];

            if ($user->hasPermission('discipline.view')) {
                $stats[] = [
                    'label' => 'Open discipline cases',
                    'value' => DisciplineIncident::whereIn('status', ['open', 'in_review'])->count(),
                    'icon' => 'bi-shield-exclamation',
                    'tone' => 'rose',
                ];
            }

            if ($user->hasPermission('fees.view')) {
                $stats[] = [
                    'label' => 'Open fee receipts',
                    'value' => FeeReceipt::whereIn('status', ['unpaid', 'partial'])->count(),
                    'icon' => 'bi-wallet2',
                    'tone' => 'amber',
                ];
            }

            if ($user->hasPermission('users.view')) {
                $stats[] = [
                    'label' => 'System users',
                    'value' => User::count(),
                    'icon' => 'bi-people',
                    'tone' => 'indigo',
                ];
            }

            $stats[] = [
                'label' => 'Classes & sections',
                'value' => SchoolClass::count(),
                'icon' => 'bi-diagram-3',
                'tone' => 'slate',
            ];

            $stats = array_slice($stats, 0, 4);
        }

        $noticeQuery = Notice::query()
            ->where('status', 'published');

        $staffRoles = [
            'admin',
            'principal',
            'academic_coordinator',
            'discipline_coordinator',
            'eca_coordinator',
            'librarian',
            'accountant',
        ];

        if (! $user->hasRole(...$staffRoles)) {
            $audiences = ['all', 'public'];

            if ($user->hasRole('student')) {
                $audiences[] = 'students';
            }

            if ($user->hasRole('parent')) {
                $audiences[] = 'parents';
            }

            if ($user->hasRole('teacher')) {
                $audiences[] = 'teachers';
            }

            $noticeQuery->whereIn('audience', array_unique($audiences));
        }

        $notices = $noticeQuery->orderByDesc('date')->limit(4)->get();

        return view('dashboard', compact('stats', 'notices', 'isCommunity'));
    }
}
