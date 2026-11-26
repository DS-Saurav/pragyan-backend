<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(["ok" => true]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(["message" => 'verification-link-sent']);
    }
}
