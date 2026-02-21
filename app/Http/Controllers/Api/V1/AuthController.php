<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\RegisterTenantDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Services\AuthService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        protected TenantService $tenantService,
        protected AuthService $authService
    ) {}

    public function register(RegisterTenantRequest $request): JsonResponse
    {
        $dto = RegisterTenantDTO::fromRequest($request->validated());
        $result = $this->tenantService->register($dto);

        return response()->json([
            'message' => 'Tenant registered successfully.',
            'data' => $result,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if (!$result) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful.',
            'data' => $result,
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'data' => auth('api')->user()->load(['roles', 'outlet']),
        ]);
    }

    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh();

        return response()->json([
            'message' => 'Token refreshed.',
            'data' => $result,
        ]);
    }
}
