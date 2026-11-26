<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

use App\Services\PublicTeam\PublicTeamVisibility;
use Illuminate\Contracts\View\View;

class TeamController extends Controller
{
    public function __construct(private PublicTeamVisibility $visibility) {}

    public function index(): JsonResponse
    {
        $groups = $this->visibility->profiles()->groupBy('teamGroup');

        return response()->json(compact('groups'));
    }
}
