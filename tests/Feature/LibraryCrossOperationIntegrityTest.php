<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryCrossOperationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function librarian(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'librarian']);

        return $user;
    }

    private function book(array $overrides = []): Book
    {
        $id = (Book::withTrashed()->max('id') ?? 0) + 1;

        return Book::create(array_merge([
            'acc_no' => 'LIB-'.str_pad($id, 5, '0', STR_PAD_LEFT),
            'title' => 'Integrity Book',
            'author' => 'Author',
            'category' => 'Fiction',
            'shelf' => 'S-1',
            'total_copies' => 3,
            'available_copies' => 3,
        ], $overrides));
    }

    private function student(string $adm = 'ADM-INT-1'): Student
    {
        return Student::create([
            'first_name' => 'Integrity',
            'last_name' => 'Borrower',
            'gender' => 'male',
            'dob' => '2014-05-01',
            'class_applied' => 'Grade 6',
            'adm_number' => $adm,
            'status' => 'active',
        ]);
    }

    private function issuePayload(Book $book, Student $student): array
    {
        return [
            'book_id' => $book->id,
            'member_type' => 'student',
            'student_id' => $student->id,
            'days' => 14,
        ];
    }

    private function activeIssued(Book $book): int
    {
        return BookIssue::where('book_id', $book->id)->where('status', 'issued')->count();
    }

    public function test_lifecycle_keeps_copy_counts_and_issued_count_consistent(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(['total_copies' => 4, 'available_copies' => 4]);

        // Issue two copies.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->issuePayload($book, $student))
            ->assertSessionHas('success');
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->issuePayload($book, $student))
            ->assertSessionHas('success');

        $book->refresh();
        $this->assertSame(2, $book->available_copies);
        $this->assertSame($book->total_copies - $book->available_copies, $this->activeIssued($book));

        // Increase total and confirm the delta is added to availability.
        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 6,
            ])
            ->assertSessionHas('success');

        $book->refresh();
        $this->assertSame(6, $book->total_copies);
        $this->assertSame(4, $book->available_copies);
        $this->assertSame($book->total_copies - $book->available_copies, $this->activeIssued($book));

        // Return both loans.
        foreach (BookIssue::where('book_id', $book->id)->where('status', 'issued')->get() as $loan) {
            $this->actingAs($librarian)
                ->post(route('admin.library.circulation.return', $loan))
                ->assertSessionHas('success');
        }

        $book->refresh();
        $this->assertSame(6, $book->available_copies);
        $this->assertSame(0, $this->activeIssued($book));
        $this->assertTrue($book->available_copies <= $book->total_copies);
        $this->assertTrue($book->available_copies >= 0);
    }

    public function test_active_loan_blocks_deletion_even_after_copy_count_edits(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);

        // Issue, then bump total copies upward while a copy is out.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->issuePayload($book, $student))
            ->assertSessionHas('success');

        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 5,
            ])
            ->assertSessionHas('success');

        // Deletion must still be refused while the loan is active.
        $this->actingAs($librarian)
            ->delete(route('admin.library.books.destroy', $book))
            ->assertSessionHasErrors('book');

        $this->assertNotSoftDeleted('books', ['id' => $book->id]);
        $this->assertSame(1, $this->activeIssued($book));
    }

    public function test_copy_count_reduction_cannot_undercut_active_loans_after_partial_returns(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);

        // Two loans out, one returned.
        foreach ([0, 1] as $i) {
            $this->actingAs($librarian)
                ->post(route('admin.library.circulation.issue'), $this->issuePayload($book, $student))
                ->assertSessionHas('success');
        }
        $loanA = BookIssue::where('book_id', $book->id)->orderBy('id')->firstOrFail();

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $loanA))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->activeIssued($book));

        // The form enforces total_copies >= 1, so with one copy still out the
        // smallest allowed total is exactly the issued count. That edit is
        // permitted and must leave the counts consistent.
        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 1,
            ])
            ->assertSessionHas('success');

        $book->refresh();
        $this->assertSame(1, $book->total_copies);
        $this->assertTrue($book->available_copies >= 0);
        $this->assertTrue($book->available_copies <= $book->total_copies);
        $this->assertSame($book->total_copies - $book->available_copies, $this->activeIssued($book));
    }

    public function test_accession_numbers_unique_including_soft_deleted_books(): void
    {
        $librarian = $this->librarian();

        foreach (['A', 'B', 'C'] as $t) {
            $this->actingAs($librarian)
                ->post(route('admin.library.books.store'), [
                    'title' => "Book $t",
                    'author' => 'Author',
                    'category' => 'Science',
                    'shelf' => 'N-1',
                    'total_copies' => 2,
                ])
                ->assertSessionHas('success');
        }

        // Soft-delete the newest one.
        Book::orderByDesc('id')->firstOrFail()->delete();

        // A new book must not reuse any accession number, active or trashed.
        $this->actingAs($librarian)
            ->post(route('admin.library.books.store'), [
                'title' => 'Book D',
                'author' => 'Author',
                'category' => 'Science',
                'shelf' => 'N-1',
                'total_copies' => 2,
            ])
            ->assertSessionHas('success');

        $all = Book::withTrashed()->pluck('acc_no');
        $this->assertCount($all->count(), $all->unique());
        $this->assertSame(4, $all->count());
    }

    public function test_failed_deletion_rolls_back_soft_delete(): void
    {
        $librarian = $this->librarian();
        $book = $this->book();

        Book::deleting(function () {
            throw new \RuntimeException('forced failure');
        });

        try {
            $this->actingAs($librarian)
                ->withoutExceptionHandling()
                ->delete(route('admin.library.books.destroy', $book));
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        } finally {
            Book::flushEventListeners();
        }

        $this->assertNotSoftDeleted('books', ['id' => $book->id]);
    }

    public function test_failed_issue_leaves_no_loan_and_no_stock_change(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(['total_copies' => 2, 'available_copies' => 2]);

        BookIssue::creating(function () {
            throw new \RuntimeException('forced failure');
        });

        try {
            $this->actingAs($librarian)
                ->withoutExceptionHandling()
                ->post(route('admin.library.circulation.issue'), $this->issuePayload($book, $student));
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        } finally {
            BookIssue::flushEventListeners();
        }

        $this->assertSame(0, BookIssue::count());
        $this->assertSame(2, $book->fresh()->available_copies);
    }
}
