<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Complaint;
use App\Models\DisciplineIncident;
use App\Models\FeeReceipt;
use App\Models\HelpdeskTicket;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $isCommunity = $user->hasRole('student', 'parent');

        $stats = [];

        if (! $isCommunity) {
            $stats[] = [
                'label' => 'Total students',
                'value' => Student::count(),
                'icon' => 'graduation-cap',
                'tone' => 'emerald',
            ];

            $stats[] = [
                'label' => 'Pending admissions',
                'value' => Admission::where('status', 'registered')->count(),
                'icon' => 'user-plus',
                'tone' => 'sky',
            ];

            if ($user->hasPermission('discipline.view')) {
                $stats[] = [
                    'label' => 'Open discipline cases',
                    'value' => DisciplineIncident::whereIn('status', ['open', 'in_review'])->count(),
                    'icon' => 'shield-alert',
                    'tone' => 'rose',
                ];
            }

            if ($user->hasPermission('fees.view')) {
                $stats[] = [
                    'label' => 'Open fee receipts',
                    'value' => FeeReceipt::whereIn('status', ['unpaid', 'partial'])->count(),
                    'icon' => 'wallet',
                    'tone' => 'amber',
                ];
            }

            if ($user->hasPermission('users.view')) {
                $stats[] = [
                    'label' => 'System users',
                    'value' => User::count(),
                    'icon' => 'users',
                    'tone' => 'indigo',
                ];
            }

            $stats[] = [
                'label' => 'Classes & sections',
                'value' => SchoolClass::count(),
                'icon' => 'layers',
                'tone' => 'slate',
            ];
        }

        $noticeQuery = Notice::query()
            ->with('publishedRevision')
            ->where(function ($q) {
                $q->where('publication_state', 'published')
                    ->orWhere('status', 'published');
            });

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

        $notices = $noticeQuery->orderByDesc('date')->limit(4)->get()->map(function (Notice $notice) {
            $rev = $notice->publishedRevision;

            return [
                'id' => $notice->id,
                'title' => $rev?->title ?? $notice->title ?? 'Untitled notice',
                'content' => $rev?->content ?? $notice->content ?? '',
                'date' => optional($notice->date)->format('Y-m-d') ?? now()->format('Y-m-d'),
                'audience' => $rev?->audience ?? $notice->audience ?? 'all',
            ];
        });

        return response()->json([
            'academicYear' => function_exists('current_academic_year') ? current_academic_year() : '2082-2083',
            'isCommunity' => $isCommunity,
            'stats' => $stats,
            'latestNotices' => $notices,
            'badges' => [
                'helpdesk' => HelpdeskTicket::whereIn('status', ['open', 'in_progress'])->count(),
                'complaints' => Complaint::whereIn('status', ['open', 'under_review'])->count(),
            ],
        ]);
    }
}
