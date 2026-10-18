<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index()
{
    // Fetch all classes directly
    $classes = SchoolClass::all();
    return view('admin.classes.index', compact('classes'));
}

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'section' => 'required|string|max:50',
        ]);

        SchoolClass::create([
            'name' => $request->name,
            'section' => $request->section,
        ]);

        return redirect()->back()->with('success', 'Class added successfully!');
    }

    public function destroy($id)
    {
        $class = SchoolClass::findOrFail($id);
        $class->delete();

        return redirect()->back()->with('success', 'Class removed successfully!');
    }
}