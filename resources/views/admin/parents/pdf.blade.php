<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Parents List</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 16px; margin: 0 0 2px 0; }
        .meta { color: #6b7280; font-size: 9px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f172a; color: #fff; text-transform: uppercase; font-size: 8px;
             letter-spacing: .5px; text-align: left; padding: 7px 6px; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        .badge { font-weight: bold; font-size: 8px; }
        .active { color: #047857; }
        .inactive { color: #6b7280; }
        .footer { position: fixed; bottom: -8px; left: 0; right: 0; text-align: center;
                  font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <h1>Parents List</h1>
    <div class="meta">
        Generated {{ now()->format('d M Y, h:i A') }}
        &middot; Total: {{ $parents->count() }}
        @if (!empty($search))
            &middot; Filter: "{{ $search }}"
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th>Parent</th>
                <th>Relation</th>
                <th>Mobile</th>
                <th>Email</th>
                <th>Occupation</th>
                <th>Children</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($parents as $i => $parent)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $parent->name }}</strong></td>
                    <td>{{ strtoupper($parent->relation) }}</td>
                    <td>{{ $parent->mobile }}</td>
                    <td>{{ $parent->email ?? 'N/A' }}</td>
                    <td>{{ $parent->occupation ?? 'N/A' }}</td>
                    <td>
                        @forelse ($parent->students as $student)
                            {{ $student->first_name }} {{ $student->last_name }}@if (!$loop->last), @endif
                        @empty
                            &mdash;
                        @endforelse
                    </td>
                    <td>
                        <span class="badge {{ $parent->status == 'active' ? 'active' : 'inactive' }}">
                            {{ strtoupper($parent->status) }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; padding:20px; color:#9ca3af;">No parents found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Parents List &middot; Pragyan Pathashala</div>
</body>
</html>