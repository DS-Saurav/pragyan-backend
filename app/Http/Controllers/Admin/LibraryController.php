<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

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

        return response()->json(compact(
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

        $validated['available_copies'] = $validated['total_copies'];

        // Guard: the books table currently has no cover_image column and Book
        // does not accept the attribute, so a submitted cover could never be
        // persisted. Reject the upload BEFORE writing any file (no orphan
        // files) and before the book is created — ordinary book creation
        // without a cover keeps working. The error surfaces beside the cover
        // input inside the reopened add-book modal (default bag + old input).
        if ($request->hasFile('cover_image') && ! Schema::hasColumn('books', 'cover_image')) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('books', 'public');

            try {
                $this->createWithUniqueAccNo($validated);
            } catch (\Throwable $e) {
                // The book could not be created with a persistable cover —
                // never leave the stored file behind.
                Storage::disk('public')->delete($validated['cover_image']);
                throw $e;
            }

            return response()->json(["message" => 'Book added to catalog successfully!']);
        }

        // Generate the accession number and insert inside a transaction so
        // concurrent creators cannot reuse the same number.
        $this->createWithUniqueAccNo($validated);

        return response()->json(["message" => 'Book added to catalog successfully!']);
    }

    /**
     * Insert a book with the next accession number (LIB-#####). The lookup and
     * insert run under a row lock, and the transaction is retried on a unique
     * collision to cover races on an empty table or a still-racing writer.
     *
     * @throws \Illuminate\Database\QueryException when retries are exhausted
     */
    private function createWithUniqueAccNo(array $attributes): Book
    {
        $attempts = 3;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes) {
                    // Lock the current highest-id row (if any) to serialise
                    // concurrent creators. max() alone is not a guarantee.
                    // Include trashed books so a soft-deleted accession number
                    // is never reused (its unique row still exists).
                    Book::withTrashed()->orderByDesc('id')->lockForUpdate()->first();

                    $next = (Book::withTrashed()->max('id') ?? 0) + 1;
                    $attributes['acc_no'] = 'LIB-'.str_pad($next, 5, '0', STR_PAD_LEFT);

                    $book = Book::create($attributes);

                    // cover_image is deliberately NOT in $fillable (the books
                    // table currently has no such column); when the owner
                    // approves the schema change the column is written
                    // explicitly inside this same transaction so a failing
                    // path-write rolls the creation back completely.
                    if (array_key_exists('cover_image', $attributes) && Schema::hasColumn('books', 'cover_image')) {
                        $book->forceFill(['cover_image' => $attributes['cover_image']])->save();
                    }

                    return $book;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if ($attempt >= $attempts || ! $this->isUniqueViolation($e)) {
                    throw $e;
                }
            }
        }
    }

    private function isUniqueViolation(\Illuminate\Database\QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true)
            || str_contains($e->getMessage(), 'UNIQUE');
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

        // Guard: the books table currently has no cover_image column and Book
        // does not accept the attribute, so a submitted replacement cover
        // could never be persisted. Reject BEFORE writing any file (no orphan
        // files, and the previous cover is never deleted). Updating ordinary
        // book details without a replacement keeps working.
        if ($request->hasFile('cover_image') && ! Schema::hasColumn('books', 'cover_image')) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $newPath = null;
        if ($request->hasFile('cover_image')) {
            // The replacement is stored first, so the previous file remains
            // intact while the database update runs.
            $newPath = $request->file('cover_image')->store('books', 'public');
            $validated['cover_image'] = $newPath;
        } else {
            unset($validated['cover_image']); // keep the existing cover
        }

        $oldPath = $book->cover_image;

        try {
            // Update copy counts under a lock, and refuse a reduction that
            // would leave fewer total copies than are currently out on loan.
            $failed = DB::transaction(function () use ($book, $validated, $newPath) {
                $locked = Book::whereKey($book->id)->lockForUpdate()->firstOrFail();

                $issued = BookIssue::where('book_id', $locked->id)
                    ->where('status', 'issued')
                    ->count();

                if ($validated['total_copies'] < $issued) {
                    return true; // conflict: cannot drop below active loans
                }

                $diff = $validated['total_copies'] - $locked->total_copies;
                $locked->available_copies = max(0, $locked->available_copies + $diff);
                $locked->fill($validated)->save();

                // cover_image is deliberately NOT in $fillable (the books
                // table currently has no such column); when the owner approves
                // the schema change, the replacement path is written
                // explicitly inside this same transaction.
                if ($newPath && Schema::hasColumn('books', 'cover_image')) {
                    $locked->forceFill(['cover_image' => $newPath])->save();
                }

                return false;
            });
        } catch (\Throwable $e) {
            if ($newPath && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath); // metadata was never saved
            }

            throw $e;
        }

        if ($failed) {
            if ($newPath && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath); // nothing was persisted
            }

            return response()->json(["message" => "Validation failed."], 422);
        }

        // Persistence succeeded: now it is safe to retire the previous cover.
        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(["message" => 'Book catalog updated successfully!']);
    }

    public function destroyBook(Book $book)
    {
        // Refuse to delete a book while any of its copies are still on loan,
        // and soft-delete so historical loan records are preserved.
        $blocked = DB::transaction(function () use ($book) {
            $locked = Book::withTrashed()->whereKey($book->id)->lockForUpdate()->firstOrFail();

            if (BookIssue::where('book_id', $locked->id)->where('status', 'issued')->exists()) {
                return true;
            }

            if ($locked->cover_image) {
                Storage::disk('public')->delete($locked->cover_image);
            }

            $locked->delete();

            return false;
        });

        if ($blocked) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        return response()->json(["message" => 'Book removed from catalog!']);
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

        return response()->json(compact(
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
            return response()->json(["message" => "Validation failed."], 422);
        }

        // Issue atomically: lock the book row so concurrent requests cannot
        // both claim the final available copy, and write the loan + count
        // decrement inside one transaction so they succeed or fail together.
        $issued = DB::transaction(function () use ($validated, $memberName) {
            $book = Book::whereKey($validated['book_id'])->lockForUpdate()->first();

            if (! $book || $book->available_copies <= 0) {
                return false;
            }

            BookIssue::create([
                'book_id' => $book->id,
                'member_type' => $validated['member_type'],
                'member_name' => $memberName,
                'issue_date' => Carbon::today(),
                'due_date' => Carbon::today()->addDays((int) $validated['days']),
                'status' => 'issued',
            ]);

            $book->decrement('available_copies');

            return true;
        });

        if (! $issued) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        return response()->json(["message" => 'Book issued successfully!']);
    }

    public function returnBook(BookIssue $issue)
    {
        $today = Carbon::today();

        // Return atomically and idempotently: lock the loan row, confirm it is
        // still active, then update it and restore availability together.
        $result = DB::transaction(function () use ($issue, $today) {
            $loan = BookIssue::whereKey($issue->id)->lockForUpdate()->first();

            if (! $loan || $loan->status === 'returned') {
                return ['status' => 'already_returned', 'fine' => (int) ($loan->fine ?? 0)];
            }

            $dueDate = Carbon::parse($loan->due_date);
            $fine = 0;

            if ($today->gt($dueDate)) {
                // due date first, so the result is a positive number of late days
                $daysLate = (int) $dueDate->diffInDays($today);
                $fine = $daysLate * self::FINE_PER_DAY;
            }

            $loan->update([
                'returned_date' => $today,
                'fine' => $fine,
                'status' => 'returned',
            ]);

            $book = Book::whereKey($loan->book_id)->lockForUpdate()->first();

            if ($book && $book->available_copies < $book->total_copies) {
                $book->increment('available_copies');
            }

            return ['status' => 'returned', 'fine' => $fine];
        });

        if ($result['status'] === 'already_returned') {
            return response()->json(["message" => 'This book has already been returned.']);
        }

        return response()->json(["message" => "Book returned successfully! Fine collected: ₹{$result['fine']}"]);
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
