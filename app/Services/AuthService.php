<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(array $credentials): ?array
    {
        $remember = $credentials['remember_me'] ?? false;
        unset($credentials['remember_me']);

        // For JWT auth with tymon/jwt-auth
        $credentials['is_active'] = true;

        if ($remember) {
            // Set TTL to 2 weeks (60 * 24 * 14)
            auth('api')->setTTL(20160);
        }

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
        try {
            return $this->respondWithToken(auth('api')->refresh());
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e) {
            throw new \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException('jwt-auth', $e->getMessage(), $e);
        }
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
