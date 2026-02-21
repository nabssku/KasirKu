<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(array $credentials): ?array
    {
        // For JWT auth with tymon/jwt-auth
        $token = auth('api')->attempt($credentials);

        if (!$token) {
            return null;
        }

        return $this->respondWithToken($token);
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    public function refresh(): array
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    protected function respondWithToken($token): array
    {
        return [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => auth('api')->user()->load(['roles', 'outlet']),
        ];
    }
}
