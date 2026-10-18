<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');
        
        $query = Admission::query();
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $admissions = $query->latest()->get();
        
        $counts = [
            'all' => Admission::count(),
            'registered' => Admission::where('status', 'registered')->count(),
            'admitted' => Admission::where('status', 'admitted')->count(),
            'rejected' => Admission::where('status', 'rejected')->count(),
        ];

        $classes = \App\Models\SchoolClass::pluck('name');

        return view('admin.admissions.index', compact('admissions', 'counts', 'status', 'classes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string',
            'dob' => 'required|date',
            'class_applied' => 'required|string',
            'academic_year' => 'nullable|string',
            'address' => 'nullable|string|max:255', 
            'parent_name' => 'required|string|max:255',
            'relation' => 'nullable|string|max:50',   
            'mobile' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',      
        ]);
        $validated['academic_year'] = $request->input('academic_year') ?: current_academic_year();
        $validated['reg_number'] = 'REG-' . date('Y') . '-' . str_pad(Admission::count() + 1, 5, '0', STR_PAD_LEFT);
        $validated['status'] = 'registered';

        Admission::create($validated);

        return redirect()->route('admin.admissions.index')->with('success', 'New registration created successfully!');
    }

    public function updateStatus(Request $request, Admission $admission)
    {
        $admission->update([
            'status' => $request->status,
            'rejection_reason' => $request->rejection_reason
        ]);

        return back()->with('success', 'Admission status updated successfully!');
    }

    public function destroy(Admission $admission)
    {
        $admission->delete();
        return back()->with('success', 'Registration deleted successfully!');
    }
}