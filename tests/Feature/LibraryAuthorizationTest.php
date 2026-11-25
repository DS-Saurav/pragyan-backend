<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function book(): Book
    {
        $id = (Book::withTrashed()->max('id') ?? 0) + 1;

        return Book::create([
            'acc_no' => 'LIB-'.str_pad($id, 5, '0', STR_PAD_LEFT),
            'title' => 'Auth Test Book',
            'author' => 'Author',
            'category' => 'Fiction',
            'shelf' => 'S-1',
            'total_copies' => 3,
            'available_copies' => 3,
        ]);
    }

    private function loan(Book $book): BookIssue
    {
        return BookIssue::create([
            'book_id' => $book->id,
            'member_type' => 'student',
            'member_name' => 'Auth Borrower',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'issued',
        ]);
    }

    /**
     * Every Library endpoint with its HTTP method and parameter source.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function endpoints(): array
    {
        return [
            'books.index' => ['GET', 'admin.library.books.index'],
            'books.store' => ['POST', 'admin.library.books.store'],
            'books.update' => ['PUT', 'admin.library.books.update'],
            'books.destroy' => ['DELETE', 'admin.library.books.destroy'],
            'circulation.index' => ['GET', 'admin.library.circulation.index'],
            'circulation.issue' => ['POST', 'admin.library.circulation.issue'],
            'circulation.return' => ['POST', 'admin.library.circulation.return'],
        ];
    }

    private function urlFor(string $name, Book $book, BookIssue $loan): string
    {
        return match ($name) {
            'admin.library.books.update', 'admin.library.books.destroy' => route($name, $book),
            'admin.library.circulation.return' => route($name, $loan),
            default => route($name),
        };
    }

    public function test_every_endpoint_requires_authentication(): void
    {
        $book = $this->book();
        $loan = $this->loan($book);

        foreach ($this->endpoints() as $name => [$method, $route]) {
            $response = $this->{$method}($this->urlFor($route, $book, $loan));
            $response->assertRedirect(route('login'));
        }
    }

    public function test_staff_without_library_manage_is_forbidden_on_every_endpoint(): void
    {
        $teacher = $this->userWithRole('teacher');
        $book = $this->book();
        $loan = $this->loan($book);

        $this->assertTrue($teacher->hasAnyRole(['teacher']));
        $this->assertFalse($teacher->hasPermission('library.manage'));

        foreach ($this->endpoints() as $name => [$method, $route]) {
            $response = $this->actingAs($teacher)->{$method}($this->urlFor($route, $book, $loan));
            $response->assertForbidden();
        }
    }

    public function test_student_and_parent_accounts_are_denied_by_staff_middleware(): void
    {
        $book = $this->book();
        $loan = $this->loan($book);

        foreach (['student', 'parent'] as $role) {
            $actor = $this->userWithRole($role);
            $this->assertFalse($actor->hasAnyRole(['librarian', 'teacher', 'admin']));

            foreach ($this->endpoints() as $name => [$method, $route]) {
                $this->actingAs($actor)
                    ->{$method}($this->urlFor($route, $book, $loan))
                    ->assertForbidden();
            }
        }
    }

    public function test_librarian_is_allowed_and_unauthorized_attempts_change_nothing(): void
    {
        $librarian = $this->userWithRole('librarian');
        $book = $this->book();
        $loan = $this->loan($book);
        $book->decrement('available_copies');

        $this->actingAs($librarian)
            ->get(route('admin.library.books.index'))
            ->assertOk();

        $this->actingAs($librarian)
            ->get(route('admin.library.circulation.index'))
            ->assertOk();
    }

    public function test_unauthorized_book_creation_leaves_catalog_unchanged(): void
    {
        $teacher = $this->userWithRole('teacher');
        $before = Book::withTrashed()->count();

        $this->actingAs($teacher)
            ->post(route('admin.library.books.store'), [
                'acc_no' => 'LIB-99999',
                'title' => 'Injected',
                'author' => 'Nobody',
                'category' => 'Fiction',
                'shelf' => 'S-9',
                'total_copies' => 1,
                'available_copies' => 1,
            ])
            ->assertForbidden();

        $this->assertSame($before, Book::withTrashed()->count());
        $this->assertDatabaseMissing('books', ['acc_no' => 'LIB-99999']);
    }

    public function test_unauthorized_update_and_delete_leave_book_unchanged(): void
    {
        $teacher = $this->userWithRole('teacher');
        $book = $this->book();
        $originalTitle = $book->title;

        $this->actingAs($teacher)
            ->put(route('admin.library.books.update', $book), [
                'acc_no' => $book->acc_no,
                'title' => 'Hacked',
                'author' => 'Nobody',
                'category' => 'Fiction',
                'shelf' => 'S-9',
                'total_copies' => 99,
                'available_copies' => 99,
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->delete(route('admin.library.books.destroy', $book))
            ->assertForbidden();

        $fresh = $book->fresh();
        $this->assertSame($originalTitle, $fresh->title);
        $this->assertSame(3, $fresh->total_copies);
        $this->assertNotSoftDeleted('books', ['id' => $book->id]);
    }

    public function test_unauthorized_issue_and_return_leave_records_and_stock_unchanged(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::create([
            'first_name' => 'Auth',
            'last_name' => 'Borrower',
            'gender' => 'male',
            'dob' => '2014-05-01',
            'class_applied' => 'Grade 6',
            'adm_number' => 'ADM-AUTH-1',
            'status' => 'active',
        ]);
        $book = $this->book();
        $loan = $this->loan($book);

        $issuesBefore = BookIssue::count();
        $availableBefore = $book->available_copies;

        $this->actingAs($teacher)
            ->post(route('admin.library.circulation.issue'), [
                'book_id' => $book->id,
                'member_type' => 'student',
                'student_id' => $student->id,
                'days' => 14,
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('admin.library.circulation.return', $loan))
            ->assertForbidden();

        $this->assertSame($issuesBefore, BookIssue::count());
        $this->assertSame($availableBefore, $book->fresh()->available_copies);
        $this->assertSame('issued', $loan->fresh()->status);
    }
}
