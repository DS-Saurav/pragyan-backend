<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'These credentials do not match our records.',
            ], 422);
        }

        if (($user->status ?? 'active') !== 'active') {
            return response()->json([
                'message' => 'Your account is not active. Please contact an administrator.',
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('angular-admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function formatUser(User $user): array
    {
        $roles = method_exists($user, 'roleNames') ? $user->roleNames() : [];
        $roleLabel = $roles
            ? collect($roles)
                ->map(fn ($role) => config("permissions.role_labels.{$role}", ucfirst((string) $role)))
                ->join(', ')
            : 'Administrator';

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roleLabel' => $roleLabel,
            'avatarUrl' => method_exists($user, 'avatarUrl') ? $user->avatarUrl() : null,
        ];
    }
}
