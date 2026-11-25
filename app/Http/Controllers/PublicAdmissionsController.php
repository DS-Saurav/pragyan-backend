<?php

namespace App\Http\Controllers;

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

    public function index(): View
    {
        return view('frontend.admissions', [
            'identity' => SiteIdentity::publicProfile(),
            'cycle' => $this->admissionCycleResolver->current(),
            'heroPhoto' => $this->publicPhotoSlots->resolve('admissions_hero'),
        ]);
    }
}
