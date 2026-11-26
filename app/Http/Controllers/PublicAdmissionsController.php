<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

use App\Services\PublicAdmissions\AdmissionCycleResolver;
use App\Services\PublicPhotos\PublicPhotoSlots;
use App\Support\SiteIdentity;
use Illuminate\Contracts\View\View;

class PublicAdmissionsController extends Controller
{
    public function __construct(
        private AdmissionCycleResolver $admissionCycleResolver,
        private PublicPhotoSlots $publicPhotoSlots,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'identity' => SiteIdentity::publicProfile(),
            'cycle' => $this->admissionCycleResolver->current(),
            'heroPhoto' => $this->publicPhotoSlots->resolve('admissions_hero'),
        ]);
    }
}
