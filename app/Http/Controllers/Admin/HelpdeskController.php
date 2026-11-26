<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HelpdeskController extends Controller
{
    private const FILE_RULE = ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,doc,docx'];

    /** Staff who can work tickets see everything; everyone else only sees their own. */
    private function manager(): bool
    {
        return (bool) auth()->user()?->can('helpdesk.manage');
    }

    private function visible()
    {
        $q = HelpdeskTicket::query();

        return $this->manager() ? $q : $q->where('raised_by', auth()->id());
    }

    private function userName($u): string
    {
        return $u ? ($u->name ?? $u->username ?? ('User #'.$u->id)) : '—';
    }

    /** Users a ticket can be assigned to (everyone except students and parents, when a role column exists). */
    private function staff()
    {
        return User::query()
            ->when(Schema::hasColumn('users', 'role'), fn ($q) => $q->whereNotIn('role', ['student', 'parent']))
            ->orderBy('name')
            ->get();
    }

    /** Filters shared by the list, tab counts and CSV. The status tab is applied separately. */
    private function filtered(Request $request)
    {
        return $this->visible()
            ->with(['raiser', 'assignee'])
            ->when($request->filled('priority') && isset(HelpdeskTicket::PRIORITIES[$request->priority]),
                fn ($q) => $q->where('priority', $request->priority))
            ->when($request->filled('category') && isset(HelpdeskTicket::CATEGORIES[$request->category]),
                fn ($q) => $q->where('category', $request->category))
            ->when($request->assigned === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when(is_numeric($request->assigned), fn ($q) => $q->where('assigned_to', $request->assigned))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn ($q) => $q->where('ticket_no', 'like', "%{$s}%")
                    ->orWhere('subject', 'like', "%{$s}%")
                    ->orWhereHas('raiser', fn ($u) => $u->where('name', 'like', "%{$s}%")));
            });
    }

    private function applyTab($query, string $tab)
    {
        return in_array($tab, HelpdeskTicket::STATUSES, true) ? $query->where('status', $tab) : $query;
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $tickets = $this->applyTab($this->filtered($request), $tab)
            ->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 when 'resolved' then 2 else 3 end")
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $raw = $this->filtered($request)->reorder()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts = ['all' => (int) $raw->sum()];
        foreach (HelpdeskTicket::STATUSES as $s) {
            $counts[$s] = (int) ($raw[$s] ?? 0);
        }

        $stats = [
            'open' => $this->visible()->where('status', 'open')->count(),
            'in_progress' => $this->visible()->where('status', 'in_progress')->count(),
            'overdue' => $this->visible()->overdue()->count(),
            'unassigned' => $this->visible()->whereIn('status', ['open', 'in_progress'])->whereNull('assigned_to')->count(),
            'resolved_month' => $this->visible()->where('resolved_at', '>=', now()->startOfMonth())->count(),
        ];

        return response()->json([
            'tickets' => $tickets,
            'counts' => $counts,
            'stats' => $stats,
            'tab' => $tab,
            'perPage' => $perPage,
            'staff' => $this->manager() ? $this->staff() : collect(),
            'isManager' => $this->manager(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(HelpdeskTicket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(HelpdeskTicket::PRIORITIES))],
            'description' => ['required', 'string', 'max:5000'],
            'attachment' => self::FILE_RULE,
        ]);

        $ticket = DB::transaction(function () use ($data, $request) {
            return HelpdeskTicket::create([
                'ticket_no' => HelpdeskTicket::nextNumber(),
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'description' => $data['description'],
                'status' => 'open',
                'raised_by' => auth()->id(),
                'attachment_path' => $request->file('attachment')?->store('helpdesk', 'public'),
                'last_activity_at' => now(),
            ]);
        });

        return response()->json(["message" => "Ticket {$ticket->ticket_no} raised. We'll get back to you soon."]);
    }

    public function show(HelpdeskTicket $ticket)
    {
        abort_unless($this->manager() || $ticket->raised_by === auth()->id(), 403);

        $ticket->load(['raiser', 'assignee']);

        $replies = $ticket->replies()->with('user')
            ->when(! $this->manager(), fn ($q) => $q->where('is_internal', false))
            ->get();

        return response()->json([
            'ticket' => $ticket,
            'replies' => $replies,
            'staff' => $this->manager() ? $this->staff() : collect(),
            'isManager' => $this->manager(),
            'names' => fn ($u) => $this->userName($u),
        ]);
    }

    /** Staff only: change status, priority and assignee. Every change is written to the thread. */
    public function update(Request $request, HelpdeskTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(HelpdeskTicket::STATUSES)],
            'priority' => ['required', Rule::in(array_keys(HelpdeskTicket::PRIORITIES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $assigned = $data['assigned_to'] ?? null;
        $notes = [];

        // Picking up an unassigned open ticket moves it to "in progress" unless staff chose a status themselves.
        if ($assigned && ! $ticket->assigned_to && $ticket->status === 'open' && $data['status'] === 'open') {
            $data['status'] = 'in_progress';
        }

        if ($data['status'] !== $ticket->status) {
            $notes[] = 'Status changed from '.HelpdeskTicket::STATUS_LABELS[$ticket->status].' to '.HelpdeskTicket::STATUS_LABELS[$data['status']].'.';
        }
        if ($data['priority'] !== $ticket->priority) {
            $notes[] = 'Priority changed from '.HelpdeskTicket::PRIORITIES[$ticket->priority].' to '.HelpdeskTicket::PRIORITIES[$data['priority']].'.';
        }
        if ((int) $assigned !== (int) $ticket->assigned_to) {
            $notes[] = $assigned ? 'Assigned to '.$this->userName(User::find($assigned)).'.' : 'Ticket unassigned.';
        }

        if (! $notes) {
            return response()->json(["message" => 'Nothing changed.']);
        }

        DB::transaction(function () use ($ticket, $data, $assigned, $notes) {
            $ticket->update([
                'status' => $data['status'],
                'priority' => $data['priority'],
                'assigned_to' => $assigned,
                'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null,
                'closed_at' => $data['status'] === 'closed' ? ($ticket->closed_at ?? now()) : null,
                'last_activity_at' => now(),
            ]);

            foreach ($notes as $note) {
                $ticket->replies()->create(['user_id' => auth()->id(), 'type' => 'system', 'body' => $note]);
            }
        });

        return response()->json(["message" => 'Ticket updated.']);
    }

    public function reply(Request $request, HelpdeskTicket $ticket)
    {
        abort_unless($this->manager() || $ticket->raised_by === auth()->id(), 403);

        if ($ticket->status === 'closed') {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'attachment' => self::FILE_RULE,
        ]);

        $internal = $this->manager() && $request->boolean('is_internal');

        DB::transaction(function () use ($ticket, $data, $internal, $request) {
            $ticket->replies()->create([
                'user_id' => auth()->id(),
                'type' => 'reply',
                'body' => $data['body'],
                'is_internal' => $internal,
                'attachment_path' => $request->file('attachment')?->store('helpdesk', 'public'),
            ]);

            $update = ['last_activity_at' => now()];

            if (! $internal) {
                if ($this->manager() && $ticket->status === 'open') {
                    $update['status'] = 'in_progress';
                    $update['assigned_to'] = $ticket->assigned_to ?? auth()->id();
                } elseif (! $this->manager() && $ticket->status === 'resolved') {
                    // The requester answered after we marked it resolved, so it was not resolved.
                    $update += ['status' => 'open', 'resolved_at' => null];
                }
            }

            $ticket->update($update);
        });

        return response()->json(["message" => $internal ? 'Internal note added.' : 'Reply sent.']);
    }

    public function destroy(HelpdeskTicket $ticket)
    {
        $ticket->delete(); // soft delete: the ticket number is never reused

        return response()->json(["message" => "Ticket {$ticket->ticket_no} deleted."]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->applyTab($this->filtered($request), $request->get('tab', 'all'))
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, ['Ticket No', 'Subject', 'Category', 'Raised By', 'Priority', 'Assigned To', 'Status',
                'Created', 'Resolved', 'Overdue']);

            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->ticket_no,
                    $t->subject,
                    HelpdeskTicket::CATEGORIES[$t->category] ?? $t->category,
                    $this->userName($t->raiser),
                    $t->priority,
                    $this->userName($t->assignee),
                    HelpdeskTicket::STATUS_LABELS[$t->status] ?? $t->status,
                    $t->created_at->format('Y-m-d H:i'),
                    $t->resolved_at?->format('Y-m-d H:i'),
                    $t->isOverdue() ? 'Yes' : 'No',
                ]);
            }

            fclose($out);
        }, 'helpdesk-tickets-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
