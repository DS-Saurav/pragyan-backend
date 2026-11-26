<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    private const FILE_RULE = ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,doc,docx'];

    /** Staff who can work complaints see everything; everyone else only sees what they filed. */
    private function manager(): bool
    {
        return (bool) auth()->user()?->can('complaints.manage');
    }

    private function visible()
    {
        $q = Complaint::query();

        return $this->manager() ? $q : $q->where('filed_by', auth()->id());
    }

    private function userName($u): string
    {
        return $u ? ($u->name ?? $u->username ?? ('User #'.$u->id)) : '—';
    }

    /** user_id => role, read from the user_roles table. */
    private function roleMap($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values()->all();

        return $ids ? DB::table('user_roles')->whereIn('user_id', $ids)->pluck('role', 'user_id')->all() : [];
    }

    /** Users a complaint can be assigned to: everyone except students and parents. */
    private function staff()
    {
        return User::query()
            ->whereNotIn('id', DB::table('user_roles')->whereIn('role', ['student', 'parent'])->select('user_id'))
            ->orderBy('name')
            ->get();
    }

    /** Filters shared by the list, tab counts and CSV. The status tab is applied separately. */
    private function filtered(Request $request)
    {
        return $this->visible()
            ->with(['filer', 'assignee'])
            ->when($request->filled('severity') && isset(Complaint::SEVERITIES[$request->severity]),
                fn ($q) => $q->where('severity', $request->severity))
            ->when($request->filled('against') && isset(Complaint::AGAINST[$request->against]),
                fn ($q) => $q->where('against', $request->against))
            ->when($request->assigned === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when(is_numeric($request->assigned), fn ($q) => $q->where('assigned_to', $request->assigned))
            ->when($request->boolean('escalated'), fn ($q) => $q->where('is_escalated', true))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn ($q) => $q->where('complaint_no', 'like', "%{$s}%")
                    ->orWhere('subject', 'like', "%{$s}%")
                    ->orWhere('against_detail', 'like', "%{$s}%")
                    // never let a name search reveal who filed an anonymous complaint
                    ->orWhere(fn ($w) => $w->where('is_anonymous', false)
                        ->whereHas('filer', fn ($u) => $u->where('name', 'like', "%{$s}%"))));
            });
    }

    private function applyTab($query, string $tab)
    {
        return in_array($tab, Complaint::STATUSES, true) ? $query->where('status', $tab) : $query;
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $complaints = $this->applyTab($this->filtered($request), $tab)
            ->orderByRaw("case status when 'open' then 0 when 'investigating' then 1 when 'resolved' then 2 else 3 end")
            ->orderByDesc('is_escalated')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $raw = $this->filtered($request)->reorder()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts = ['all' => (int) $raw->sum()];
        foreach (Complaint::STATUSES as $s) {
            $counts[$s] = (int) ($raw[$s] ?? 0);
        }

        $stats = [
            'open' => $this->visible()->where('status', 'open')->count(),
            'investigating' => $this->visible()->where('status', 'investigating')->count(),
            'escalated' => $this->visible()->whereIn('status', ['open', 'investigating'])->where('is_escalated', true)->count(),
            'overdue' => $this->visible()->overdue()->count(),
            'resolved_month' => $this->visible()->where('status', 'resolved')->where('resolved_at', '>=', now()->startOfMonth())->count(),
        ];

        $rows = $complaints->getCollection();

        return response()->json([
            'complaints' => $complaints,
            'counts' => $counts,
            'stats' => $stats,
            'tab' => $tab,
            'perPage' => $perPage,
            'staff' => $this->manager() ? $this->staff() : collect(),
            'roles' => $this->roleMap($rows->pluck('filed_by')->merge($rows->pluck('assigned_to'))),
            'isManager' => $this->manager(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'against' => ['required', Rule::in(array_keys(Complaint::AGAINST))],
            'against_detail' => ['nullable', 'string', 'max:150'],
            'severity' => ['required', Rule::in(array_keys(Complaint::SEVERITIES))],
            'incident_date' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:5000'],
            'is_anonymous' => ['nullable', 'boolean'],
            'attachment' => self::FILE_RULE,
        ]);

        $complaint = DB::transaction(function () use ($data, $request) {
            return Complaint::create([
                'complaint_no' => Complaint::nextNumber(),
                'subject' => $data['subject'],
                'against' => $data['against'],
                'against_detail' => $data['against_detail'] ?? null,
                'severity' => $data['severity'],
                'incident_date' => $data['incident_date'] ?? null,
                'description' => $data['description'],
                'is_anonymous' => $request->boolean('is_anonymous'),
                'status' => 'open',
                'filed_by' => auth()->id(),
                'attachment_path' => $request->file('attachment')?->store('complaints', 'public'),
                'last_activity_at' => now(),
            ]);
        });

        return response()->json(["message" => "Complaint {$complaint->complaint_no} filed. You can follow its progress on this page."]);
    }

    public function show(Complaint $complaint)
    {
        abort_unless($this->manager() || $complaint->filed_by === auth()->id(), 403);

        $complaint->load(['filer', 'assignee']);

        $updates = $complaint->updates()->with('user')
            ->when(! $this->manager(), fn ($q) => $q->where('is_internal', false))
            ->get();

        $roles = $this->roleMap($updates->pluck('user_id')->merge([$complaint->filed_by, $complaint->assigned_to]));

        return view('admin.complaints.show', [
            'complaint' => $complaint,
            'updates' => $updates,
            'roles' => $roles,
            'staff' => $this->manager() ? $this->staff() : collect(),
            'isManager' => $this->manager(),
            // Anonymous complaints: the filer's own messages are masked for everyone else
            'authorName' => fn ($u) => ($complaint->is_anonymous && $u && $complaint->hidesFilerFrom(auth()->id()) && $u->id === $complaint->filed_by)
                ? 'Anonymous'
                : $this->userName($u),
        ]);
    }

    /** Staff only: change status, severity, assignee. A resolution note is required to resolve or dismiss. */
    public function update(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Complaint::STATUSES)],
            'severity' => ['required', Rule::in(array_keys(Complaint::SEVERITIES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $assigned = $data['assigned_to'] ?? null;
        $closing = in_array($data['status'], ['resolved', 'dismissed'], true);
        $note = trim((string) ($data['resolution_note'] ?? ''));

        if ($closing && $data['status'] !== $complaint->status && $note === '') {
            return response()->json(["message" => "Validation failed."], 422);
        }

        // Picking up an unassigned open complaint moves it to "investigating" unless staff chose a status themselves.
        if ($assigned && ! $complaint->assigned_to && $complaint->status === 'open' && $data['status'] === 'open') {
            $data['status'] = 'investigating';
        }

        $public = [];   // the complainant sees these
        $internal = []; // staff only

        if ($data['status'] !== $complaint->status) {
            $public[] = ['system', 'Status changed from '.Complaint::STATUS_LABELS[$complaint->status].' to '.Complaint::STATUS_LABELS[$data['status']].'.'];
        }
        if ($data['severity'] !== $complaint->severity) {
            $internal[] = 'Severity changed from '.Complaint::SEVERITIES[$complaint->severity].' to '.Complaint::SEVERITIES[$data['severity']].'.';
        }
        if ((int) $assigned !== (int) $complaint->assigned_to) {
            $internal[] = $assigned ? 'Assigned to '.$this->userName(User::find($assigned)).'.' : 'Complaint unassigned.';
        }
        if ($closing && $note !== '' && $note !== $complaint->resolution_note) {
            $public[] = ['resolution', $note];
        }

        if (! $public && ! $internal) {
            return response()->json(["message" => 'Nothing changed.']);
        }

        DB::transaction(function () use ($complaint, $data, $assigned, $closing, $note, $public, $internal) {
            $complaint->update([
                'status' => $data['status'],
                'severity' => $data['severity'],
                'assigned_to' => $assigned,
                'resolution_note' => $closing && $note !== '' ? $note : $complaint->resolution_note,
                'resolved_at' => $closing ? ($complaint->resolved_at ?? now()) : null,
                'last_activity_at' => now(),
            ]);

            foreach ($public as [$type, $body]) {
                $complaint->updates()->create(['user_id' => auth()->id(), 'type' => $type, 'body' => $body]);
            }
            foreach ($internal as $body) {
                $complaint->updates()->create(['user_id' => auth()->id(), 'type' => 'system', 'body' => $body, 'is_internal' => true]);
            }
        });

        return response()->json(["message" => 'Complaint updated.']);
    }

    /** Staff only: flag an active complaint for senior review. */
    public function escalate(Request $request, Complaint $complaint)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if (! $complaint->isActive() || $complaint->is_escalated) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        DB::transaction(function () use ($complaint, $data) {
            $complaint->update(['is_escalated' => true, 'escalated_at' => now(), 'last_activity_at' => now()]);

            $complaint->updates()->create([
                'user_id' => auth()->id(), 'type' => 'system', 'body' => 'This complaint has been escalated for senior review.',
            ]);
            $complaint->updates()->create([
                'user_id' => auth()->id(), 'type' => 'system', 'is_internal' => true, 'body' => 'Escalation reason: '.$data['reason'],
            ]);
        });

        return response()->json(["message" => 'Complaint escalated.']);
    }

    public function reply(Request $request, Complaint $complaint)
    {
        abort_unless($this->manager() || $complaint->filed_by === auth()->id(), 403);

        if ($complaint->status === 'dismissed') {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'attachment' => self::FILE_RULE,
        ]);

        $internal = $this->manager() && $request->boolean('is_internal');
        $reopened = false;

        DB::transaction(function () use ($complaint, $data, $internal, $request, &$reopened) {
            $complaint->updates()->create([
                'user_id' => auth()->id(),
                'type' => 'comment',
                'body' => $data['body'],
                'is_internal' => $internal,
                'attachment_path' => $request->file('attachment')?->store('complaints', 'public'),
            ]);

            $change = ['last_activity_at' => now()];

            if (! $internal) {
                if ($this->manager() && $complaint->status === 'open') {
                    $change['status'] = 'investigating';
                    $change['assigned_to'] = $complaint->assigned_to ?? auth()->id();
                } elseif (! $this->manager() && $complaint->status === 'resolved') {
                    // The complainant answered after it was marked resolved, so it was not resolved to their satisfaction.
                    $change += ['status' => 'open', 'resolved_at' => null];
                    $reopened = true;
                }
            }

            $complaint->update($change);

            if ($reopened) {
                $complaint->updates()->create([
                    'user_id' => auth()->id(), 'type' => 'system', 'body' => 'Reopened by the complainant.',
                ]);
            }
        });

        return response()->json(["message" => $internal ? 'Internal note added.' : ($reopened ? 'Complaint reopened.' : 'Message sent.')]);
    }

    public function destroy(Complaint $complaint)
    {
        $complaint->delete(); // soft delete: the complaint number is never reused

        return response()->json(["message" => "Complaint {$complaint->complaint_no} deleted."]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->applyTab($this->filtered($request), $request->get('tab', 'all'))
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, ['Complaint No', 'Subject', 'Against', 'Detail', 'Filed By', 'Severity', 'Assigned To',
                'Status', 'Escalated', 'Filed On', 'Resolved On', 'Overdue']);

            foreach ($rows as $c) {
                fputcsv($out, [
                    $c->complaint_no,
                    $c->subject,
                    Complaint::AGAINST[$c->against] ?? $c->against,
                    $c->against_detail,
                    $c->filerName(),
                    $c->severity,
                    $this->userName($c->assignee),
                    Complaint::STATUS_LABELS[$c->status] ?? $c->status,
                    $c->is_escalated ? 'Yes' : 'No',
                    $c->created_at->format('Y-m-d H:i'),
                    $c->resolved_at?->format('Y-m-d H:i'),
                    $c->isOverdue() ? 'Yes' : 'No',
                ]);
            }

            fclose($out);
        }, 'complaints-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
