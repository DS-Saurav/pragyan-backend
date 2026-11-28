<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Models\SalarySlip;
use App\Models\StaffMember;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index()
    {
        $staff = StaffMember::with('components')->orderBy('status')->orderBy('name')->get();
        $components = SalaryComponent::orderBy('type')->orderBy('id')->get();

        $usedComponentIds = DB::table('staff_salary_components')->distinct()->pluck('salary_component_id');

        $notImported = Teacher::whereNotIn('id', StaffMember::whereNotNull('teacher_id')->pluck('teacher_id'))->count();

        return response()->json(compact('staff', 'components', 'usedComponentIds', 'notImported'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        DB::transaction(function () use ($data) {
            $staff = StaffMember::create(collect($data)->except('components')->all());
            $staff->components()->sync($this->syncData($data['components'] ?? []));
        });

        return response()->json(["message" => 'Staff member added.']);
    }

    public function update(Request $request, StaffMember $staff)
    {
        $data = $request->validate($this->rules());

        DB::transaction(function () use ($staff, $data) {
            $staff->update(collect($data)->except('components')->all());
            $staff->components()->sync($this->syncData($data['components'] ?? []));
        });

        return response()->json(["message" => 'Staff member updated. Slips that already exist are not changed.']);
    }

    public function destroy(StaffMember $staff)
    {
        if (SalarySlip::withTrashed()->where('staff_member_id', $staff->id)->exists()) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $staff->delete();

        return response()->json(["message" => 'Staff member deleted.']);
    }

    /** Copy teachers that are not in payroll yet. Basic salary stays 0 until you set it. */
    public function importTeachers()
    {
        $linked = StaffMember::whereNotNull('teacher_id')->pluck('teacher_id');
        $count = 0;

        foreach (Teacher::whereNotIn('id', $linked)->get() as $t) {
            StaffMember::create([
                'teacher_id' => $t->id,
                'name' => trim(($t->first_name ?? '').' '.($t->last_name ?? '')) ?: 'Teacher #'.$t->id,
                'designation' => $t->specialization ?: ($t->qualification ?: 'Teacher'),
                'type' => 'teaching',
                'phone' => $t->phone ?? null,
                'basic_salary' => 0,
                'status' => ($t->status ?? 'active') === 'active' ? 'active' : 'inactive',
            ]);
            $count++;
        }

        return response()->json(["message" => "{$count} teacher(s) imported. Now set each person's basic salary before running payroll."]);
    }

    // ---------------- Salary components ----------------

    public function storeComponent(Request $request)
    {
        SalaryComponent::create($request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:salary_components,name'],
            'type' => ['required', Rule::in(array_keys(SalaryComponent::TYPES))],
            'calc' => ['required', Rule::in(array_keys(SalaryComponent::CALCS))],
            'default_value' => ['nullable', 'numeric', 'min:0'],
        ]) + ['default_value' => $request->input('default_value') ?: 0]);

        return response()->json(["message" => 'Component added.']);
    }

    public function updateComponent(Request $request, SalaryComponent $component)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('salary_components', 'name')->ignore($component->id)],
            'type' => ['required', Rule::in(array_keys(SalaryComponent::TYPES))],
            'calc' => ['required', Rule::in(array_keys(SalaryComponent::CALCS))],
            'default_value' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['default_value'] = $data['default_value'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        $component->update($data);

        return response()->json(["message" => 'Component updated.']);
    }

    public function destroyComponent(SalaryComponent $component)
    {
        if (DB::table('staff_salary_components')->where('salary_component_id', $component->id)->exists()) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $component->delete();

        return response()->json(["message" => 'Component deleted.']);
    }

    // ---------------------------------------------------------------------

    private function syncData(array $rows): array
    {
        $sync = [];

        foreach ($rows as $row) {
            if (empty($row['id']) || ! isset($row['value']) || $row['value'] === '') {
                continue;
            }
            $sync[(int) $row['id']] = ['value' => round((float) $row['value'], 2)];
        }

        return $sync;
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(StaffMember::TYPES))],
            'phone' => ['nullable', 'string', 'max:30'],
            'joined_on' => ['nullable', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'bank_account' => ['nullable', 'string', 'max:40'],
            'pan_no' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'components' => ['nullable', 'array'],
            'components.*.id' => ['nullable', 'exists:salary_components,id'],
            'components.*.value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}
