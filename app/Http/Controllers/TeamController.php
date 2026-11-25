<?php

namespace App\Http\Controllers;

use App\Services\PublicTeam\PublicTeamVisibility;
use Illuminate\Contracts\View\View;

class TeamController extends Controller
{
    public function __construct(private PublicTeamVisibility $visibility) {}

    public function index(): View
    {
        $groups = $this->visibility->profiles()->groupBy('teamGroup');

        return view('frontend.team', compact('groups'));
    }
}
