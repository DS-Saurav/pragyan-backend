<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $query = Notice::orderBy('date', 'desc');

        if ($tab === 'published') {
            $query->where('status', 'published');
        } elseif ($tab === 'draft') {
            $query->where('status', 'draft');
        }

        $notices = $query->get();
        $totalCount = Notice::count();
        $publishedCount = Notice::where('status', 'published')->count();
        $draftCount = Notice::where('status', 'draft')->count();

        return view('admin.notices.index', compact(
            'notices', 'tab', 'totalCount', 'publishedCount', 'draftCount'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'audience' => 'required|string',
            'date' => 'required|date',
            'status' => 'required|in:published,draft',
        ]);

        Notice::create([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? '',
            'audience' => strtolower($validated['audience']),
            'posted_by' => auth()->user()->name ?? 'Admin User',
            'date' => $validated['date'],
            'status' => strtolower($validated['status']),
        ]);

        return back()->with('success', 'Notice created successfully!');
    }

    public function destroy(Notice $notice)
    {
        $notice->delete();

        return back()->with('success', 'Notice deleted successfully!');
    }
}
