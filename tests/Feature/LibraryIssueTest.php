<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LibraryIssueTest extends TestCase
{
    use RefreshDatabase;

    private function librarian(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'librarian']);

        return $user;
    }

    private function student(string $adm = 'ADM-LIB-1'): Student
    {
        return Student::create([
            'first_name' => 'Library',
            'last_name' => 'Borrower',
            'gender' => 'male',
            'dob' => '2014-05-01',
            'class_applied' => 'Grade 6',
            'adm_number' => $adm,
            'status' => 'active',
        ]);
    }

    private function book(int $available, int $total = 3): Book
    {
        $id = (Book::max('id') ?? 0) + 1;

        return Book::create([
            'acc_no' => 'LIB-'.str_pad($id, 5, '0', STR_PAD_LEFT),
            'title' => 'Test Book',
            'author' => 'Author',
            'category' => 'Fiction',
            'shelf' => 'S-1',
            'total_copies' => $total,
            'available_copies' => $available,
        ]);
    }

    private function payload(Book $book, Student $student, array $overrides = []): array
    {
        return array_merge([
            'book_id' => $book->id,
            'member_type' => 'student',
            'student_id' => $student->id,
            'days' => 14,
        ], $overrides);
    }

    private function issue(Book $book, string $memberName = 'Library Borrower', ?string $dueDate = null): BookIssue
    {
        return BookIssue::create([
            'book_id' => $book->id,
            'member_type' => 'student',
            'member_name' => $memberName,
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate ?? now()->addDays(14)->toDateString(),
            'status' => 'issued',
        ]);
    }

    public function test_valid_issue_creates_record_and_updates_availability(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(available: 2, total: 3);

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->payload($book, $student))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('book_issues', [
            'book_id' => $book->id,
            'member_type' => 'student',
            'member_name' => 'Library Borrower',
            'status' => 'issued',
        ]);

        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_unavailable_book_cannot_be_issued(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(available: 0, total: 2);

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->payload($book, $student))
            ->assertRedirect()
            ->assertSessionHasErrors('book_id');

        $this->assertSame(0, BookIssue::count());
        $this->assertSame(0, $book->fresh()->available_copies);
    }

    public function test_repeated_issuance_cannot_make_stock_negative(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(available: 1, total: 1);

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->payload($book, $student))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Second attempt against the now-exhausted single copy must fail.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->payload($book, $student))
            ->assertRedirect()
            ->assertSessionHasErrors('book_id');

        $this->assertSame(1, BookIssue::count());
        $this->assertSame(0, $book->fresh()->available_copies);
    }

    public function test_issue_failure_leaves_no_partial_changes(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(available: 1, total: 1);

        // Force the loan insert to fail after the book row is locked/checked so
        // we can prove the transaction rolls back and availability is untouched.
        BookIssue::creating(function () {
            throw new \RuntimeException('forced failure');
        });

        try {
            $this->actingAs($librarian)
                ->withoutExceptionHandling()
                ->post(route('admin.library.circulation.issue'), $this->payload($book, $student));
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        } finally {
            BookIssue::flushEventListeners();
        }

        $this->assertSame(0, BookIssue::count());
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_invalid_borrower_and_dates_are_rejected(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(available: 2, total: 3);

        // Missing member entirely
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), [
                'book_id' => $book->id,
                'member_type' => 'student',
                'days' => 14,
            ])
            ->assertSessionHasErrors('member_name');

        // Non-existent student
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->payload($book, $student, ['student_id' => 999999]))
            ->assertSessionHasErrors('student_id');

        // Out-of-range loan period
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->payload($book, $student, ['days' => 0]))
            ->assertSessionHasErrors('days');

        // Non-existent book
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $this->payload($book, $student, ['book_id' => 999999]))
            ->assertSessionHasErrors('book_id');

        $this->assertSame(0, BookIssue::count());
        $this->assertSame(2, $book->fresh()->available_copies);
    }

    public function test_issue_writes_loan_and_decrement_within_one_transaction(): void
    {
        $librarian = $this->librarian();
        $student = $this->student();
        $book = $this->book(available: 2, total: 3);

        $baselineLevel = DB::transactionLevel();
        $insertWasInTransaction = null;
        BookIssue::creating(function () use (&$insertWasInTransaction, $baselineLevel) {
            $insertWasInTransaction = DB::transactionLevel() > $baselineLevel;
        });

        try {
            $this->actingAs($librarian)
                ->post(route('admin.library.circulation.issue'), $this->payload($book, $student))
                ->assertSessionHas('success');
        } finally {
            BookIssue::flushEventListeners();
        }

        $this->assertTrue($insertWasInTransaction, 'Book loan should be created inside a DB transaction.');
        $this->assertSame($baselineLevel, DB::transactionLevel(), 'The controller transaction should have committed.');
        $this->assertSame(1, BookIssue::count());
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_valid_return_updates_loan_and_availability(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(available: 1, total: 3);
        $issue = $this->issue($book);

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $issue))
            ->assertRedirect()
            ->assertSessionHas('success');

        $issue->refresh();
        $this->assertSame('returned', $issue->status);
        $this->assertNotNull($issue->returned_date);
        $this->assertSame(0, (int) $issue->fine);
        $this->assertSame(2, $book->fresh()->available_copies);
    }

    public function test_returning_twice_does_not_increment_availability_again(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(available: 1, total: 3);
        $issue = $this->issue($book);

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $issue))
            ->assertSessionHas('success');

        $this->assertSame(2, $book->fresh()->available_copies);

        // Duplicate submission must be a no-op for availability.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $issue))
            ->assertSessionHas('success');

        $this->assertSame(2, $book->fresh()->available_copies);
        $this->assertSame('returned', $issue->fresh()->status);
    }

    public function test_return_failure_rolls_back_all_writes(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(available: 1, total: 3);
        $issue = $this->issue($book);

        // Force the book increment to fail after the loan row has been updated
        // inside the transaction to prove both writes roll back together.
        Book::updating(function () {
            throw new \RuntimeException('forced failure');
        });

        try {
            $this->actingAs($librarian)
                ->withoutExceptionHandling()
                ->post(route('admin.library.circulation.return', $issue));
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        } finally {
            Book::flushEventListeners();
        }

        $this->assertSame('issued', $issue->fresh()->status);
        $this->assertNull($issue->fresh()->returned_date);
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_nonexistent_loan_is_handled_safely(): void
    {
        $librarian = $this->librarian();

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', 999999))
            ->assertNotFound();
    }

    public function test_availability_never_exceeds_total_copies(): void
    {
        $librarian = $this->librarian();
        // Pre-existing corrupt data: availability already equals total.
        $book = $this->book(available: 3, total: 3);
        $issue = $this->issue($book);

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $issue))
            ->assertSessionHas('success');

        // Return still recorded, but availability is not inflated past total.
        $this->assertSame('returned', $issue->fresh()->status);
        $this->assertSame(3, $book->fresh()->available_copies);
    }

    public function test_overdue_return_calculates_fine(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(available: 1, total: 3);
        $issue = $this->issue($book, dueDate: now()->subDays(3)->toDateString());

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $issue))
            ->assertSessionHas('success');

        $issue->refresh();
        $this->assertSame('returned', $issue->status);
        // 3 days late * FINE_PER_DAY (5)
        $this->assertSame(15, (int) $issue->fine);
        $this->assertSame(2, $book->fresh()->available_copies);
    }

    public function test_return_writes_loan_and_increment_within_one_transaction(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(available: 1, total: 3);
        $issue = $this->issue($book);

        $baselineLevel = DB::transactionLevel();
        $updateWasInTransaction = null;
        Book::updating(function () use (&$updateWasInTransaction, $baselineLevel) {
            $updateWasInTransaction = DB::transactionLevel() > $baselineLevel;
        });

        try {
            $this->actingAs($librarian)
                ->post(route('admin.library.circulation.return', $issue))
                ->assertSessionHas('success');
        } finally {
            Book::flushEventListeners();
        }

        $this->assertTrue($updateWasInTransaction, 'Availability increment should run inside a DB transaction.');
        $this->assertSame($baselineLevel, DB::transactionLevel(), 'The controller transaction should have committed.');
        $this->assertSame('returned', $issue->fresh()->status);
        $this->assertSame(2, $book->fresh()->available_copies);
    }
}