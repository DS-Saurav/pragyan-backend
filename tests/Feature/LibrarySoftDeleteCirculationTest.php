<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LibrarySoftDeleteCirculationTest extends TestCase
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
            'title' => 'Test Book',
            'author' => 'Author',
            'category' => 'Fiction',
            'shelf' => 'S-1',
            'total_copies' => 3,
            'available_copies' => 3,
        ], $overrides));
    }

    private function issue(Book $book, string $status = 'issued'): BookIssue
    {
        if ($status === 'issued') {
            $book->decrement('available_copies');
        }

        return BookIssue::create([
            'book_id' => $book->id,
            'member_type' => 'student',
            'member_name' => 'Library Borrower',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => $status,
            'returned_date' => $status === 'returned' ? now()->toDateString() : null,
        ]);
    }

    public function test_active_loan_book_cannot_be_soft_deleted_via_route(): void
    {
        $librarian = $this->librarian();
        $book = $this->book();
        $this->issue($book);

        $this->actingAs($librarian)
            ->delete(route('admin.library.books.destroy', $book))
            ->assertSessionHasErrors('book');

        $this->assertNotSoftDeleted('books', ['id' => $book->id]);
    }

    public function test_relaxed_book_with_active_loan_can_still_be_returned(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 2]);
        $loan = $this->issue($book); // active loan, availability already decremented

        // Simulate an out-of-band soft delete of the book while a loan is out.
        $book->delete();
        $this->assertSoftDeleted('books', ['id' => $book->id]);

        // The loan must remain returnable and must not corrupt state.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $loan))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('returned', $loan->fresh()->status);

        // The trashed book's copy count is intentionally left untouched: the
        // return succeeds, but availability is not restored onto a book that is
        // out of the catalog. The important invariant is that no error occurs
        // and the count is never pushed above total_copies.
        $available = Book::withTrashed()->find($book->id)->available_copies;
        $this->assertSame(1, $available);
        $this->assertLessThanOrEqual(Book::withTrashed()->find($book->id)->total_copies, $available);
    }

    public function test_returned_history_visible_when_book_is_trashed(): void
    {
        $librarian = $this->librarian();
        $book = $this->book();
        $loan = $this->issue($book, 'returned');

        $book->delete();

        $this->actingAs($librarian)
            ->get(route('admin.library.circulation.index', ['tab' => 'returned']))
            ->assertOk()
            ->assertSee('Deleted book')
            ->assertDontSee($book->acc_no);

        $this->assertDatabaseHas('book_issues', ['id' => $loan->id, 'status' => 'returned']);
    }

    public function test_trashed_book_is_not_offered_for_issue(): void
    {
        $librarian = $this->librarian();
        $book = $this->book();
        $book->delete();

        $this->actingAs($librarian)
            ->get(route('admin.library.circulation.index'))
            ->assertOk()
            ->assertDontSee($book->title);
    }

    public function test_crafted_issue_request_for_trashed_book_is_rejected(): void
    {
        $librarian = $this->librarian();
        $student = Student::create([
            'first_name' => 'Library',
            'last_name' => 'Borrower',
            'gender' => 'male',
            'dob' => '2014-05-01',
            'class_applied' => 'Grade 6',
            'adm_number' => 'ADM-LIB-1',
            'status' => 'active',
        ]);

        $book = $this->book();
        $book->delete();

        // The exists rule does not apply the soft-delete scope, so a trashed id
        // passes validation. The controller's scoped, locked lookup is the real
        // safeguard against issuing a loan for a trashed book.
        $this->assertTrue(
            Validator::make(['book_id' => $book->id], ['book_id' => 'required|exists:books,id'])->passes()
        );

        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), [
                'book_id' => $book->id,
                'member_type' => 'student',
                'student_id' => $student->id,
                'days' => 14,
            ])
            ->assertSessionHasErrors('book_id');

        $this->assertSame(0, BookIssue::count());
    }

    public function test_issue_and_return_safeguards_remain_intact_with_soft_deletes(): void
    {
        $librarian = $this->librarian();
        $student = Student::create([
            'first_name' => 'Active',
            'last_name' => 'Borrower',
            'gender' => 'female',
            'dob' => '2013-03-03',
            'class_applied' => 'Grade 7',
            'adm_number' => 'ADM-LIB-2',
            'status' => 'active',
        ]);

        $book = $this->book(['total_copies' => 1, 'available_copies' => 1]);
        $payload = [
            'book_id' => $book->id,
            'member_type' => 'student',
            'student_id' => $student->id,
            'days' => 14,
        ];

        // Issue once.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $payload)
            ->assertSessionHas('success');

        $this->assertSame(0, $book->fresh()->available_copies);

        // Over-issue blocked.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.issue'), $payload)
            ->assertSessionHasErrors('book_id');

        $this->assertSame(1, BookIssue::count());

        $loan = $book->issues()->first();

        // Return restores stock.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $loan))
            ->assertSessionHas('success');

        $this->assertSame(1, $book->fresh()->available_copies);

        // Duplicate return is idempotent.
        $this->actingAs($librarian)
            ->post(route('admin.library.circulation.return', $loan))
            ->assertSessionHas('success');

        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertSame('returned', $loan->fresh()->status);
    }
}
