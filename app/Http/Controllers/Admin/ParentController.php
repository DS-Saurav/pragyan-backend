<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    /**
     * Shared query so the table, CSV and PDF/print views always agree on what is "filtered".
     * The OR conditions are grouped so any extra filter added later can't be bypassed.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = ParentModel::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $parents = $this->filteredQuery($request)
            ->with('students')
            ->latest()
            ->paginate(10)
            ->withQueryString(); // keeps ?search= when moving between pages

        $students = Student::all(); // For linking children modal dropdown

        return response()->json(compact('parents', 'students'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relation' => 'required|string|max:50',
            'mobile' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:255',
            'status' => 'required|string|in:active,inactive',
            'address' => 'nullable|string|max:255',
        ]);

        ParentModel::create($validated);

        return response()->json(["message" => 'Parent added successfully!']);
    }

    public function update(Request $request, ParentModel $parent)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relation' => 'required|string|max:50',
            'mobile' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:255',
            'status' => 'required|string|in:active,inactive',
            'address' => 'nullable|string|max:255',
        ]);

        $parent->update($validated);

        return response()->json(["message" => 'Parent updated successfully!']);
    }

    public function destroy(ParentModel $parent)
    {
        $parent->delete();

        return response()->json(["message" => 'Parent deleted successfully!']);
    }

    public function linkChild(Request $request, ParentModel $parent)
    {
        $request->validate(['student_id' => 'required|exists:students,id']);
        $parent->students()->syncWithoutDetaching([$request->student_id]);

        return response()->json(["message" => 'Child linked successfully!']);
    }

    public function unlinkChild(ParentModel $parent, Student $student)
    {
        $parent->students()->detach($student->id);

        return response()->json(["message" => 'Child unlinked successfully!']);
    }

    /**
     * Export CSV (respects the current ?search= filter).
     * Streams in chunks so it stays fast and low-memory even with thousands of rows.
     */
    public function exportCsv(Request $request)
    {
        $filename = 'parents_'.now()->format('Y-m-d').'.csv';
        $query = $this->filteredQuery($request)->with('students');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel shows Nepali / non-English text correctly
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['ID', 'Name', 'Relation', 'Mobile', 'Email', 'Occupation', 'Status', 'Address', 'Children'], ',', '"', '\\');

            // chunkById is safe here because the query has no custom ordering
            $query->chunkById(200, function ($parents) use ($out) {
                foreach ($parents as $parent) {
                    $children = $parent->students
                        ->map(fn ($s) => trim($s->first_name.' '.$s->last_name))
                        ->implode('; ');

                    fputcsv($out, [
                        $parent->id,
                        $this->csvSafe($parent->name),
                        $this->csvSafe($parent->relation),
                        $this->csvSafe($parent->mobile),
                        $this->csvSafe($parent->email),
                        $this->csvSafe($parent->occupation),
                        $this->csvSafe($parent->status),
                        $this->csvSafe($parent->address),
                        $this->csvSafe($children),
                    ], ',', '"', '\\');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Print-friendly page. It opens the browser's print dialog,
     * where the user can choose "Save as PDF" (no PDF package needed).
     */
    public function exportPdf(Request $request)
    {
        $parents = $this->filteredQuery($request)
            ->with('students')
            ->latest()
            ->get();

        return response()->json(compact('parents'));
    }

    /**
     * Stops spreadsheet formula injection: a cell starting with = + - @ is
     * executed as a formula by Excel/Sheets, so prefix it with an apostrophe.
     */
    private function csvSafe($value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
