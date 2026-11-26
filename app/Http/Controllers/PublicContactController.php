<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

use App\Support\SiteIdentity;
use App\Services\PublicPhotos\PublicPhotoSlots;
use Illuminate\Contracts\View\View;

class PublicContactController extends Controller
{
    public function __construct(private PublicPhotoSlots $publicPhotoSlots) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'identity' => SiteIdentity::publicProfile(),
            'visitPhoto' => $this->publicPhotoSlots->resolve('contact_visit'),
        ]);
    }
}
