<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;

class AboutController extends Controller
{
    public function index()
    {
        Gate::authorize('settings.manage');

        $settings = Setting::pluck('value', 'key')->all();

        return response()->json(compact('settings'));
    }
}
