@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="dashboard-shell" data-dashboard-shell>
        <x-ui.page-header title="Dashboard" :subtitle="'A focused view of the school day, your priorities and the latest published updates.'">
            <x-slot name="actions">
                <span class="dashboard-date-pill">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <span>{{ now()->format('D, M j, Y') }}</span>
                </span>
                <span class="dashboard-year-pill">
                    <span class="sr-only">Academic year</span>
                    AY {{ current_academic_year() }}
                </span>
            </x-slot>
        </x-ui.page-header>

        @if ($isCommunity)
            <section class="dashboard-briefing dashboard-briefing-community" aria-labelledby="community-dashboard-heading">
                <div class="dashboard-briefing-content">
                    <div>
                        <span class="dashboard-eyebrow">
                            <i class="bi bi-house-heart" aria-hidden="true"></i>
                            Your school portal
                        </span>
                        <h2 id="community-dashboard-heading" class="dashboard-briefing-title">
                            Welcome back, {{ Auth::user()->name }}
                        </h2>
                        <p class="dashboard-briefing-copy">
                            Stay connected with school notices, raise a helpdesk request, file a complaint or find
                            the documents shared with you.
                        </p>
                    </div>
                    <nav class="dashboard-shortcut-grid" aria-label="Your portal shortcuts">
                        <a href="{{ route('admin.helpdesk.index') }}"
                            class="dashboard-shortcut">
                            <i class="bi bi-headset" aria-hidden="true"></i>
                            <span class="dashboard-shortcut-title">Helpdesk</span>
                            <span class="dashboard-shortcut-copy">Get support</span>
                        </a>
                        <a href="{{ route('admin.complaints.index') }}"
                            class="dashboard-shortcut">
                            <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                            <span class="dashboard-shortcut-title">Complaints</span>
                            <span class="dashboard-shortcut-copy">Share a concern</span>
                        </a>
                        <a href="{{ route('admin.documents.index') }}"
                            class="dashboard-shortcut">
                            <i class="bi bi-folder2-open" aria-hidden="true"></i>
                            <span class="dashboard-shortcut-title">Documents</span>
                            <span class="dashboard-shortcut-copy">Files for you</span>
                        </a>
                        <a href="{{ route('public.notices') }}"
                            class="dashboard-shortcut">
                            <i class="bi bi-newspaper" aria-hidden="true"></i>
                            <span class="dashboard-shortcut-title">Notice Board</span>
                            <span class="dashboard-shortcut-copy">Latest updates</span>
                        </a>
                    </nav>
                </div>
            </section>
        @else
            <section class="dashboard-briefing dashboard-briefing-staff" aria-labelledby="staff-dashboard-heading">
                <div class="dashboard-briefing-content">
                    <div>
                        <span class="dashboard-eyebrow">
                            <i class="bi bi-activity" aria-hidden="true"></i>
                            Daily overview
                        </span>
                        <h2 id="staff-dashboard-heading" class="dashboard-briefing-title">School day at a glance</h2>
                        <p class="dashboard-briefing-copy">
                            Start with the school records you can act on, then move through the newest published updates.
                        </p>
                    </div>
                    <dl class="dashboard-context" aria-label="Today’s dashboard context">
                        <div>
                            <dt>Today</dt>
                            <dd>{{ now()->format('l, j M') }}</dd>
                        </div>
                        <div>
                            <dt>Academic year</dt>
                            <dd>{{ current_academic_year() }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            @if (count($stats))
                <section aria-label="School records at a glance">
                    <div class="dashboard-section-heading">
                        <p>School records</p>
                        <span>Only the areas available to your account are shown.</span>
                    </div>
                    <div class="dashboard-stat-grid">
                        @foreach ($stats as $stat)
                            <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :tone="$stat['tone']" />
                        @endforeach
                    </div>
                </section>
            @endif
        @endif

        <div class="dashboard-workspace">
            <section class="ui-card dashboard-notices-panel">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Latest notices</h2>
                        <p class="dashboard-panel-subtitle">Published announcements for your school community.</p>
                    </div>
                    <a href="{{ route('public.notices') }}" class="ui-link text-xs">View all</a>
                </div>

                @if ($notices->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state icon="bi-megaphone" title="No notices yet"
                            message="Published notices will appear here as soon as they are added." />
                    </div>
                @else
                    <ul class="dashboard-notice-list">
                        @foreach ($notices as $notice)
                            <li class="dashboard-notice-row">
                                <span class="dashboard-notice-icon">
                                    <i class="bi bi-megaphone" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="dashboard-notice-title">{{ $notice->title }}</p>
                                    @if ($notice->content)
                                        <p class="dashboard-notice-summary">{{ \Illuminate\Support\Str::limit($notice->content, 140) }}</p>
                                    @endif
                                    <p class="dashboard-notice-meta">
                                        <span>
                                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                                            {{ \Illuminate\Support\Carbon::parse($notice->date)->format('M j, Y') }}
                                        </span>
                                        <span>{{ ucfirst($notice->audience) }}</span>
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section id="dashboard-actions" class="ui-card dashboard-actions-panel" aria-labelledby="dashboard-actions-heading">
                <div class="ui-card-header">
                    <div>
                        <h2 id="dashboard-actions-heading" class="text-sm font-bold text-slate-900">Next actions</h2>
                        <p class="dashboard-panel-subtitle">Jump straight to your regular work.</p>
                    </div>
                    <i class="bi bi-lightning-charge dashboard-panel-icon" aria-hidden="true"></i>
                </div>
                <div class="dashboard-action-list">
                    @if ($isCommunity)
                        <a href="{{ route('admin.helpdesk.index') }}" class="dashboard-action">
                            <i class="bi bi-headset" aria-hidden="true"></i><span>Open helpdesk</span>
                        </a>
                        <a href="{{ route('admin.complaints.index') }}" class="dashboard-action">
                            <i class="bi bi-chat-left-text" aria-hidden="true"></i><span>My complaints</span>
                        </a>
                        <a href="{{ route('admin.documents.index') }}" class="dashboard-action">
                            <i class="bi bi-folder2-open" aria-hidden="true"></i><span>My documents</span>
                        </a>
                    @else
                        <a href="{{ route('admin.students.index') }}" class="dashboard-action">
                            <i class="bi bi-mortarboard" aria-hidden="true"></i><span>Manage students</span>
                        </a>
                        <a href="{{ route('admin.attendance.index') }}" class="dashboard-action">
                            <i class="bi bi-person-check" aria-hidden="true"></i><span>Mark attendance</span>
                        </a>
                        @can('fees.view')
                            <a href="{{ route('admin.fees.index') }}" class="dashboard-action">
                                <i class="bi bi-wallet2" aria-hidden="true"></i><span>Fees collection</span>
                            </a>
                        @endcan
                        @can('discipline.view')
                            <a href="{{ route('admin.discipline.index') }}" class="dashboard-action">
                                <i class="bi bi-shield-check" aria-hidden="true"></i><span>Discipline register</span>
                            </a>
                        @endcan
                        @can('settings.manage')
                            <a href="{{ route('admin.settings.index') }}" class="dashboard-action">
                                <i class="bi bi-gear" aria-hidden="true"></i><span>School settings</span>
                            </a>
                        @endcan
                    @endif
                    <a href="{{ route('profile.edit') }}" class="dashboard-action">
                        <i class="bi bi-person-circle" aria-hidden="true"></i><span>My account</span>
                    </a>
                </div>
            </section>
        </div>
    </div>
@endsection
