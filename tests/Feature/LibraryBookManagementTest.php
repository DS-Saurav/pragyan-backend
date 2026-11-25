<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookIssue;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryBookManagementTest extends TestCase
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
        $id = (Book::max('id') ?? 0) + 1;

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

    private function issue(Book $book): BookIssue
    {
        $book->decrement('available_copies');

        return BookIssue::create([
            'book_id' => $book->id,
            'member_type' => 'student',
            'member_name' => 'Library Borrower',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'issued',
        ]);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'New Book',
            'author' => 'New Author',
            'category' => 'Science',
            'shelf' => 'N-1',
            'total_copies' => 4,
        ], $overrides);
    }

    public function test_store_creates_book_with_next_accession_number(): void
    {
        $librarian = $this->librarian();

        $this->actingAs($librarian)
            ->post(route('admin.library.books.store'), $this->storePayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $book = Book::firstWhere('title', 'New Book');
        $this->assertNotNull($book);
        $this->assertSame('LIB-00001', $book->acc_no);
        $this->assertSame(4, $book->total_copies);
        $this->assertSame(4, $book->available_copies);
    }

    public function test_store_accession_numbers_are_unique_across_creations(): void
    {
        $librarian = $this->librarian();

        foreach (['Book A', 'Book B', 'Book C'] as $title) {
            $this->actingAs($librarian)
                ->post(route('admin.library.books.store'), $this->storePayload(['title' => $title]))
                ->assertSessionHas('success');
        }

        $numbers = Book::pluck('acc_no');
        $this->assertCount(3, $numbers);
        $this->assertCount(3, $numbers->unique());
    }

    public function test_store_accession_number_does_not_collide_after_deletion(): void
    {
        $librarian = $this->librarian();

        // Create three books (LIB-00001..LIB-00003), then delete the last one.
        foreach (['Book A', 'Book B', 'Book C'] as $title) {
            $this->actingAs($librarian)
                ->post(route('admin.library.books.store'), $this->storePayload(['title' => $title]))
                ->assertSessionHas('success');
        }

        Book::where('acc_no', 'LIB-00003')->firstOrFail()->delete();

        // Next creation must land on a fresh, unused number.
        $this->actingAs($librarian)
            ->post(route('admin.library.books.store'), $this->storePayload(['title' => 'Book D']))
            ->assertSessionHas('success');

        $this->assertSame(3, Book::count());
        $this->assertCount(3, Book::pluck('acc_no')->unique());
    }

    public function test_increasing_total_copies_keeps_issued_count_consistent(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);
        $this->issue($book); // 1 issued, 2 available

        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $book->refresh();
        $this->assertSame(5, $book->total_copies);
        $this->assertSame(4, $book->available_copies);
        $this->assertSame(1, $book->total_copies - $book->available_copies);
    }

    public function test_reducing_total_copies_within_issued_count_is_allowed(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 5, 'available_copies' => 5]);
        $this->issue($book);
        $this->issue($book); // 2 issued, 3 available

        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 3, // still >= 2 issued
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $book->refresh();
        $this->assertSame(3, $book->total_copies);
        $this->assertSame(1, $book->available_copies);
        $this->assertSame(2, $book->total_copies - $book->available_copies);
        $this->assertSame(2, BookIssue::where('book_id', $book->id)->where('status', 'issued')->count());
    }

    public function test_reducing_below_issued_count_is_rejected(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);
        $this->issue($book);
        $this->issue($book); // 2 issued, 1 available

        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 1, // below 2 issued
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('total_copies');

        $book->refresh();
        $this->assertSame(3, $book->total_copies);
        $this->assertSame(1, $book->available_copies);
        $this->assertSame(2, BookIssue::where('book_id', $book->id)->where('status', 'issued')->count());
    }

    public function test_update_failure_rolls_back_all_writes(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);

        Book::updating(function () {
            throw new \RuntimeException('forced failure');
        });

        try {
            $this->actingAs($librarian)
                ->withoutExceptionHandling()
                ->put(route('admin.library.books.update', $book), [
                    'title' => 'Changed',
                    'author' => $book->author,
                    'category' => $book->category,
                    'shelf' => $book->shelf,
                    'total_copies' => 5,
                ]);
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        } finally {
            Book::flushEventListeners();
        }

        $book->refresh();
        $this->assertSame('Test Book', $book->title);
        $this->assertSame(3, $book->total_copies);
        $this->assertSame(3, $book->available_copies);
    }

    public function test_update_writes_are_wrapped_in_one_transaction(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);

        $baselineLevel = DB::transactionLevel();
        $updateWasInTransaction = null;
        Book::updating(function () use (&$updateWasInTransaction, $baselineLevel) {
            $updateWasInTransaction = DB::transactionLevel() > $baselineLevel;
        });

        try {
            $this->actingAs($librarian)
                ->put(route('admin.library.books.update', $book), [
                    'title' => $book->title,
                    'author' => $book->author,
                    'category' => $book->category,
                    'shelf' => $book->shelf,
                    'total_copies' => 4,
                ])
                ->assertSessionHas('success');
        } finally {
            Book::flushEventListeners();
        }

        $this->assertTrue($updateWasInTransaction, 'Book update should run inside a DB transaction.');
        $this->assertSame($baselineLevel, DB::transactionLevel(), 'The controller transaction should have committed.');
    }

    public function test_store_and_update_require_authorization(): void
    {
        $book = $this->book();
        $other = User::factory()->create();
        UserRole::create(['user_id' => $other->id, 'role' => 'teacher']);

        $this->actingAs($other)
            ->post(route('admin.library.books.store'), $this->storePayload())
            ->assertForbidden();

        $this->actingAs($other)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 2,
            ])
            ->assertForbidden();
    }

    public function test_deleting_book_with_no_loan_history_soft_deletes_it(): void
    {
        $librarian = $this->librarian();
        $book = $this->book();

        $this->actingAs($librarian)
            ->delete(route('admin.library.books.destroy', $book))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('books', ['id' => $book->id]);
        $this->assertSame(0, Book::count());
        $this->assertSame(1, Book::withTrashed()->count());
    }

    public function test_deleting_book_with_active_loan_is_rejected(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);
        $this->issue($book); // active issued loan

        $this->actingAs($librarian)
            ->delete(route('admin.library.books.destroy', $book))
            ->assertRedirect()
            ->assertSessionHasErrors('book');

        $this->assertNotSoftDeleted('books', ['id' => $book->id]);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'deleted_at' => null]);
        $this->assertSame(1, BookIssue::where('book_id', $book->id)->count());
    }

    public function test_deleting_book_preserves_returned_loan_history(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);

        $issue = $this->issue($book);
        $issue->update([
            'status' => 'returned',
            'returned_date' => now()->toDateString(),
        ]);

        $this->actingAs($librarian)
            ->delete(route('admin.library.books.destroy', $book))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('books', ['id' => $book->id]);
        // Historical loan row survives the soft delete (not cascaded away).
        $this->assertDatabaseHas('book_issues', ['id' => $issue->id, 'status' => 'returned']);
        $this->assertSame(1, BookIssue::where('book_id', $book->id)->count());
    }

    public function test_rejected_deletion_preserves_all_loan_records(): void
    {
        $librarian = $this->librarian();
        $book = $this->book(['total_copies' => 3, 'available_copies' => 3]);

        $active = $this->issue($book);
        $returned = $this->issue($book);
        $returned->update(['status' => 'returned', 'returned_date' => now()->toDateString()]);

        $this->actingAs($librarian)
            ->delete(route('admin.library.books.destroy', $book))
            ->assertSessionHasErrors('book');

        $this->assertNotSoftDeleted('books', ['id' => $book->id]);
        $this->assertSame(2, BookIssue::where('book_id', $book->id)->count());
        $this->assertDatabaseHas('book_issues', ['id' => $active->id, 'status' => 'issued']);
        $this->assertDatabaseHas('book_issues', ['id' => $returned->id, 'status' => 'returned']);
    }

    public function test_deleting_book_requires_authorization(): void
    {
        $book = $this->book();
        $other = User::factory()->create();
        UserRole::create(['user_id' => $other->id, 'role' => 'teacher']);

        $this->actingAs($other)
            ->delete(route('admin.library.books.destroy', $book))
            ->assertForbidden();

        $this->assertNotSoftDeleted('books', ['id' => $book->id]);
    }

    public function test_store_book_cover_image_must_be_an_image(): void
    {
        // NOTE: the books table currently has no cover_image column and the
        // Book model does not accept it, so uploaded covers cannot persist —
        // a migration decision is deferred to the owner (Stage U1 safeguards
        // forbid schema changes). These tests cover upload validation only.
        Storage::fake('public');
        $librarian = $this->librarian();

        $this->actingAs($librarian)
            ->post(route('admin.library.books.store'), $this->storePayload([
                'cover_image' => UploadedFile::fake()->create('cover.pdf', 50, 'application/pdf'),
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('cover_image');

        $this->assertNull(Book::firstWhere('title', 'New Book'));
    }

    public function test_store_book_with_an_oversized_cover_image_is_rejected(): void
    {
        Storage::fake('public');
        $librarian = $this->librarian();

        $this->actingAs($librarian)
            ->post(route('admin.library.books.store'), $this->storePayload([
                'cover_image' => UploadedFile::fake()->image('cover.jpg')->size(3000), // max is 2048 KB
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('cover_image');

        $this->assertNull(Book::firstWhere('title', 'New Book'));
    }

    public function test_updating_book_without_a_new_file_writes_nothing_to_storage(): void
    {
        Storage::fake('public');
        $librarian = $this->librarian();
        $book = $this->book();

        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => 'Renamed Book',
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame([], Storage::disk('public')->allFiles(), 'A plain update must not touch storage.');
    }

    public function test_book_deletion_runs_inside_a_transaction(): void
    {
        $librarian = $this->librarian();
        $book = $this->book();

        $baselineLevel = DB::transactionLevel();
        $deleteWasInTransaction = null;
        Book::deleting(function () use (&$deleteWasInTransaction, $baselineLevel) {
            $deleteWasInTransaction = DB::transactionLevel() > $baselineLevel;
        });

        try {
            $this->actingAs($librarian)
                ->delete(route('admin.library.books.destroy', $book))
                ->assertSessionHas('success');
        } finally {
            Book::flushEventListeners();
        }

        $this->assertTrue($deleteWasInTransaction, 'Book deletion should run inside a DB transaction.');
        $this->assertSame($baselineLevel, DB::transactionLevel(), 'The controller transaction should have committed.');
    }

    // ------------------------------------------------- cover-image schema guard
    // The books table has no cover_image column (deferred schema decision), so
    // an uploaded cover can never be persisted. The controller must reject the
    // upload before writing any file, but leave ordinary book CRUD untouched.

    /** Temporarily adds the cover_image column so the "column exists" paths can be exercised. */
    private function addCoverImageColumn(): void
    {
        Schema::table('books', fn ($table) => $table->string('cover_image')->nullable());
    }

    public function test_cover_upload_is_rejected_when_the_column_is_absent_and_writes_no_file(): void
    {
        Storage::fake('public');
        $librarian = $this->librarian();

        $this->actingAs($librarian)
            ->post(route('admin.library.books.store'), $this->storePayload([
                'cover_image' => UploadedFile::fake()->image('cover.jpg', 400, 400),
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('cover_image');

        $this->assertNull(Book::firstWhere('title', 'New Book'), 'No book may be created from a rejected cover upload.');
        $this->assertSame([], Storage::disk('public')->allFiles(), 'A rejected cover upload must not write any file.');
    }

    public function test_cover_replacement_is_rejected_when_the_column_is_absent(): void
    {
        Storage::fake('public');
        $librarian = $this->librarian();
        $book = $this->book();

        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => $book->total_copies,
                'cover_image' => UploadedFile::fake()->image('cover.jpg', 400, 400),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cover_image');

        $book->refresh();
        $this->assertSame('Test Book', $book->title, 'The book must be untouched when the cover upload is rejected.');
        $this->assertSame(3, $book->total_copies);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'A rejected cover upload must not write any file.');
    }

    public function test_cover_upload_persists_when_the_schema_supports_it(): void
    {
        Storage::fake('public');
        $this->addCoverImageColumn();
        $librarian = $this->librarian();

        $this->actingAs($librarian)
            ->post(route('admin.library.books.store'), $this->storePayload([
                'cover_image' => UploadedFile::fake()->image('cover.jpg', 400, 400),
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Book added to catalog successfully!');

        $book = Book::firstWhere('title', 'New Book');
        $this->assertNotNull($book);
        $this->assertNotNull($book->cover_image, 'The cover path must be persisted when the column exists.');
        Storage::disk('public')->assertExists($book->cover_image);
    }

    public function test_successful_cover_replacement_persists_the_new_file_then_removes_the_old(): void
    {
        Storage::fake('public');
        $this->addCoverImageColumn();
        $book = $this->bookWithCover();
        $librarian = $this->librarian();
        $original = $book->cover_image;

        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => $book->total_copies,
                'cover_image' => UploadedFile::fake()->image('cover2.jpg', 400, 400),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $book->refresh();
        $this->assertNotNull($book->cover_image);
        $this->assertNotSame($original, $book->cover_image);
        Storage::disk('public')->assertExists($book->cover_image); // the new cover is on disk
        Storage::disk('public')->assertMissing($original); // the old cover is only deleted after persisting
    }

    public function test_failed_update_with_a_new_cover_preserves_the_previous_cover(): void
    {
        Storage::fake('public');
        $this->addCoverImageColumn();
        $librarian = $this->librarian();
        $book = $this->bookWithCover(['total_copies' => 3, 'available_copies' => 3]);
        $original = $book->cover_image;

        $this->issue($book);
        $this->issue($book); // 2 issued → total_copies below that is refused

        $this->actingAs($librarian)
            ->put(route('admin.library.books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'shelf' => $book->shelf,
                'total_copies' => 1, // below the 2 active loans
                'cover_image' => UploadedFile::fake()->image('cover2.jpg', 400, 400),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('total_copies');

        $book->refresh();
        $this->assertSame($original, $book->cover_image, 'The previous cover path must survive the failed update.');
        $this->assertSame([$original], Storage::disk('public')->allFiles(), 'The previous cover file must survive and the rejected replacement must be cleaned up (no orphans).');
    }

    public function test_unauthorized_cover_upload_is_forbidden_and_writes_nothing(): void
    {
        Storage::fake('public');
        $other = User::factory()->create();
        UserRole::create(['user_id' => $other->id, 'role' => 'teacher']);
        $file = UploadedFile::fake()->image('cover.jpg', 400, 400);

        $this->actingAs($other)
            ->post(route('admin.library.books.store'), $this->storePayload(['cover_image' => $file]))
            ->assertForbidden();

        $this->assertNull(Book::firstWhere('title', 'New Book'));
        $this->assertSame([], Storage::disk('public')->allFiles(), 'An unauthorized upload must not write any file.');
    }

    /** Creates a librarian book with an already-stored cover (requires the column). */
    private function bookWithCover(array $overrides = []): Book
    {
        $book = $this->book($overrides);
        $path = UploadedFile::fake()->image('seed.jpg', 400, 400)
            ->store('books', 'public');
        $book->forceFill(['cover_image' => $path])->save();

        return $book;
    }
}
