<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $base = Exam::query()
            ->when($request->filled('year'), fn ($q) => $q->where('academic_year', $request->year))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'));

        $raw = (clone $base)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        $counts = ['all' => (int) $raw->sum()];
        foreach (Exam::STATUSES as $s) {
            $counts[$s] = (int) ($raw[$s] ?? 0);
        }

        $exams = (clone $base)
            ->when(in_array($tab, Exam::STATUSES, true), fn ($q) => $q->where('status', $tab))
            ->withCount([
                'examSubjects',
                'examSubjects as submitted_count' => fn ($q) => $q->whereIn('marks_status', ['submitted', 'locked']),
            ])
            ->orderByDesc('start_date')
            ->paginate($perPage)
            ->withQueryString();

        $y = now()->month >= 4 ? now()->year : now()->year - 1; // school year starts around April

        return response()->json([
            'exams' => $exams,
            'counts' => $counts,
            'tab' => $tab,
            'perPage' => $perPage,
            'years' => Exam::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'defaultYear' => $y.'-'.($y + 1),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['status'] = $data['status'] ?? 'scheduled';
        Exam::create($data);

        return response()->json(["message" => 'Exam created. Next: click ⚙️ Setup to add subjects and marks.']);
    }

    public function update(Request $request, Exam $exam)
    {
        $data = $request->validate($this->rules());

        if ($exam->status === 'published' || empty($data['status'])) {
            unset($data['status']); // publishing is controlled only by Publish / Unpublish
        }

        $exam->update($data);

        return response()->json(["message" => 'Exam updated.']);
    }

    public function destroy(Exam $exam)
    {
        if ($exam->status === 'published') {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $exam->delete(); // also removes its setup and marks

        return response()->json(["message" => 'Exam deleted.']);
    }

    public function publish(Exam $exam)
    {
        $total = $exam->examSubjects()->count();
        if ($total === 0) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $open = $exam->examSubjects()->whereIn('marks_status', ['pending', 'draft'])->count();
        if ($open > 0) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $exam->update(['status' => 'published', 'published_at' => now()]);

        return response()->json(["message" => 'Results published.']);
    }

    public function unpublish(Exam $exam)
    {
        $exam->update(['status' => 'completed', 'published_at' => null]);

        return response()->json(["message" => 'Results unpublished.']);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Exam::TYPES))],
            'academic_year' => ['required', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['scheduled', 'ongoing', 'completed'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
