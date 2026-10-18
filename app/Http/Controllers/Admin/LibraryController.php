<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LibraryController extends Controller
{
    private const FINE_PER_DAY = 5;

    public function index(Request $request)
    {
        $viewMode = $request->get('view', 'shelf'); // shelf or table

        // Get ALL books: the view handles search / paging on the client side
        $books = Book::orderBy('title')->get();

        // Issue modal only lists books that still have copies
        $issueBooks = $books->where('available_copies', '>', 0)->values();

        // Overdue issues + fine accruing so far
        $overdueIssues = BookIssue::where('status', 'issued')
            ->where('due_date', '<', Carbon::today())
            ->get();

        $overdueFine = $overdueIssues->sum(function ($issue) {
            return (int) Carbon::parse($issue->due_date)->diffInDays(Carbon::today()) * self::FINE_PER_DAY;
        });

        $stats = [
            'titles' => $books->count(),
            'total_copies' => $books->sum('total_copies'),
            'available' => $books->sum('available_copies'),
            'issued' => BookIssue::where('status', 'issued')->count(),
            'overdue' => $overdueIssues->count(),
            'fines' => BookIssue::sum('fine'),
        ];

        $students = $this->memberList(Student::class);
        $teachers = $this->memberList(Teacher::class);
        $finePerDay = self::FINE_PER_DAY;

        return view('admin.library.index', compact(
            'books', 'issueBooks', 'stats', 'viewMode',
            'students', 'teachers', 'overdueFine', 'finePerDay'
        ));
    }

    public function storeBook(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'shelf' => 'required|string|max:50',
            'total_copies' => 'required|integer|min:1',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('books', 'public');
        }

        // Use max id so numbers never repeat after deletions
        $validated['acc_no'] = 'LIB-'.str_pad((Book::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT);
        $validated['available_copies'] = $validated['total_copies'];

        Book::create($validated);

        return back()->with('success', 'Book added to catalog successfully!');
    }

    public function updateBook(Request $request, Book $book)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'shelf' => 'required|string|max:50',
            'total_copies' => 'required|integer|min:1',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($book->cover_image) {
                Storage::disk('public')->delete($book->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')->store('books', 'public');
        } else {
            unset($validated['cover_image']); // keep the existing cover
        }

        $diff = $validated['total_copies'] - $book->total_copies;
        $book->available_copies = max(0, $book->available_copies + $diff);

        $book->update($validated);

        return back()->with('success', 'Book catalog updated successfully!');
    }

    public function destroyBook(Book $book)
    {
        if ($book->cover_image) {
            Storage::disk('public')->delete($book->cover_image);
        }
        $book->delete();

        return back()->with('success', 'Book removed from catalog!');
    }

    public function circulation(Request $request)
    {
        $statusTab = $request->get('tab', 'issued');
        $today = Carbon::today();

        $counts = [
            'issued' => BookIssue::where('status', 'issued')->count(),
            'overdue' => BookIssue::where('status', 'issued')->where('due_date', '<', $today)->count(),
            'returned' => BookIssue::where('status', 'returned')->count(),
            'all' => BookIssue::count(),
        ];

        $query = BookIssue::with('book');

        if ($statusTab === 'issued') {
            $query->where('status', 'issued');
        } elseif ($statusTab === 'overdue') {
            $query->where('status', 'issued')->where('due_date', '<', $today);
        } elseif ($statusTab === 'returned') {
            $query->where('status', 'returned');
        }

        $issues = $query->latest()->get(); // client-side search / paging in the view
        $issueBooks = Book::where('available_copies', '>', 0)->orderBy('title')->get();
        $students = $this->memberList(Student::class);
        $teachers = $this->memberList(Teacher::class);
        $finePerDay = self::FINE_PER_DAY;

        return view('admin.library.circulation', compact(
            'issues', 'issueBooks', 'students', 'teachers', 'statusTab', 'counts', 'finePerDay'
        ));
    }

    public function issueBook(Request $request)
    {
        // Accepts either a picked member (student_id / teacher_id) or a typed member_name
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
            'member_type' => 'required|string|in:student,teacher,staff',
            'student_id' => 'nullable|exists:students,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'member_name' => 'nullable|string|max:255',
            'days' => 'required|integer|min:1|max:90',
        ]);

        $memberName = $validated['member_name'] ?? null;

        if ($validated['member_type'] === 'student' && ! empty($validated['student_id'])) {
            $memberName = $this->personName(Student::findOrFail($validated['student_id']));
        } elseif ($validated['member_type'] !== 'student' && ! empty($validated['teacher_id'])) {
            $memberName = $this->personName(Teacher::findOrFail($validated['teacher_id']));
        }

        if (! $memberName) {
            return back()->withErrors(['member_name' => 'Please select a member.'])->withInput();
        }

        $book = Book::findOrFail($validated['book_id']);
        if ($book->available_copies <= 0) {
            return back()->withErrors(['book_id' => 'Selected book has no available copies left.']);
        }

        BookIssue::create([
            'book_id' => $book->id,
            'member_type' => $validated['member_type'],
            'member_name' => $memberName,
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays((int) $validated['days']), // <-- Cast to (int) here
            'status' => 'issued',
        ]);

        $book->decrement('available_copies');

        return back()->with('success', 'Book issued successfully!');
    }

    public function returnBook(BookIssue $issue)
    {
        $today = Carbon::today();
        $dueDate = Carbon::parse($issue->due_date);
        $fine = 0;

        if ($today->gt($dueDate)) {
            // due date first, so the result is a positive number of late days
            $daysLate = (int) $dueDate->diffInDays($today);
            $fine = $daysLate * self::FINE_PER_DAY;
        }

        $issue->update([
            'returned_date' => $today,
            'fine' => $fine,
            'status' => 'returned',
        ]);

        $issue->book()->increment('available_copies');

        return back()->with('success', "Book returned successfully! Fine collected: ₹{$fine}");
    }

    /**
     * Works whatever the name columns are called (name, full_name, first_name + last_name,
     * or a linked user record).
     */
    private function personName($model): string
    {
        $name = $model->name
            ?? $model->full_name
            ?? trim(($model->first_name ?? '').' '.($model->last_name ?? ''))
            ?: ($model->user->name ?? null);

        return $name ?: ('#'.$model->id);
    }

    /** id + name list for the member dropdowns, sorted by name. */
    private function memberList(string $modelClass)
    {
        return $modelClass::all()
            ->map(fn ($m) => (object) ['id' => $m->id, 'name' => $this->personName($m)])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
